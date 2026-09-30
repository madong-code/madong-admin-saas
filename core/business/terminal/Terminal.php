<?php
declare(strict_types=1);

/**
 *+------------------
 * madong
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: http://www.madong.tech
 */
namespace core\business\terminal;

use app\enum\terminal\CommandEvent;
use core\foundation\tool\Sse;
use core\foundation\tool\Util;
use Illuminate\Filesystem\Filesystem;

/**
 * Terminal 执行终端命令并通过 SSE 实时输出结果。
 *
 * @author Mr.April
 * @since  1.0
 */
final class Terminal
{
/**
 * 插件临时目录基础路径
 */
const RUNTIME_PLUGIN_PATH = 'runtime/install/plugin';

/**
 * 插件终端输出目录
 */
const PLUGIN_TERMINAL_PATH = 'runtime/install/plugin/terminal';

    /**
     * @var string 当前执行的命令 $command 的 key
     */
    protected string $commandKey = '';

    /**
     * @var array proc_open 的参数
     */
    protected array $descriptorsSpec = [];

    /**
     * @var resource|bool proc_open 返回的 resource
     */
    protected $process = false;

    /**
     * @var array proc_open 的管道
     */
    protected array $pipes = [];

    /**
     * @var int proc执行状态:0=未执行,1=执行中,2=执行完毕
     */
    protected int $procStatusMark = 0;

    /**
     * @var array proc执行状态数据
     */
    protected array $procStatusData = [];

    /**
     * @var string 命令在前台的uuid
     */
    protected string $uuid = '';

    /**
     * @var string 扩展信息
     */
    protected string $extend = '';

    /**
     * @var string 命令执行输出文件
     */
    protected string $outputFile = '';

    /**
     * @var string 命令执行实时输出内容
     */
    protected string $outputContent = '';

    /**
     * @var array 命令变量替换
     */
    protected array $commandVariables = [];

    /**
     * @var array 当前执行的命令配置
     */
    protected array $commandConfig = [];

    /**
     * @var array 拦截器映射
     */
    protected static array $interceptMap = [
        'install' => 'core\business\terminal\intercept\InstallIntercept',
        'build' => 'core\business\terminal\intercept\BuildIntercept',
        'common' => 'core\business\terminal\intercept\CommonIntercept',
    ];

    /**
     * @var int 进程退出码
     */
    protected int $processExitCode = 0;

    /**
     * 私有构造函数，防止直接实例化
     * 
     * @param string $uuid 会话UUID
     * @param string $extend 扩展信息
     */
    private function __construct(string $uuid = '', string $extend = '')
    {
        $this->uuid   = $uuid;
        $this->extend = $extend;

        // 初始化命令变量（先初始化变量，再使用变量）
        $this->initCommandVariables();

        // 初始化输出目录和文件
        $this->initOutputFile();

        // 初始化管道描述符
        $this->initDescriptors();
    }

    /**
     * 初始化输出文件
     */
    protected function initOutputFile(): void
    {
        $config     = config('terminal.execution', []);
        $outputDir  = $config['output_dir'] ?? base_path(self::PLUGIN_TERMINAL_PATH);
        $outputFile = $config['output_file'] ?? 'exec.log';

        // 替换路径中的变量
        $outputDir = $this->replaceCommandVariables($outputDir);

        $this->outputFile = $outputDir . DIRECTORY_SEPARATOR . $outputFile;
        $filesystem       = new Filesystem();

        if (!is_dir($outputDir)) {
            $filesystem->makeDirectory($outputDir, 0755, true);
        }
        $filesystem->put($this->outputFile, '');
    }

    /**
     * 初始化管道描述符
     */
    protected function initDescriptors(): void
    {
        /**
         * 命令执行结果输出到文件而不是管道
         * 因为输出到管道时有延迟，而文件虽然需要频繁读取和对比内容，但是输出实时的
         */
        $this->descriptorsSpec = [
            0 => ['pipe', 'r'], // 标准输入
            1 => ['file', $this->outputFile, 'w'], // 标准输出
            2 => ['file', $this->outputFile, 'w'], // 标准错误
        ];
    }

    /**
     * 初始化命令变量
     */
    protected function initCommandVariables(): void
    {
        // 后端根目录（server目录）
        $backendRoot = base_path();
        // 项目根目录（包含server、admin、web等目录的父目录）
        $projectRoot = dirname($backendRoot);
        
        // 检查是否在命令行环境下执行
        if (php_sapi_name() === 'cli') {
            // 在命令行环境下，使用默认值
            $platform = 'h5';
            $env = 'production';
        } else {
            // 在Web环境下，使用request()函数获取参数
            $platform = request()->input('platform', 'h5');
            $env = request()->input('env', 'production');
        }
        
        $this->commandVariables = [
            '%PLATFORM%'        => $platform, // Uni-app平台变量
            '%ENV%'             => $env, // 环境变量
            '{package_manager}' => config('terminal.npm_package_manager', 'npm'), // 包管理器
            '{backend_root}'    => $backendRoot, // 后端根目录
            '{project_root}'    => $projectRoot, // 项目根目录
        ];
    }

    /**
     * 获取解析后的命令键（去掉包管理器后缀）
     *
     * @param string $key 原始命令键
     *
     * @return string
     */
    protected function getResolvedCommandKey(string $key): string
    {
        $keyParts = explode('.', $key);

        // 检查是否是安装或构建命令，尝试去掉包管理器后缀
        // 注意：install.server 是后端命令，不使用包管理器
        if (count($keyParts) > 2 && (in_array($keyParts[0], ['install', 'build']) && $keyParts[1] !== 'server')) {
            // 重新构建命令键，去掉最后一个部分（包管理器）
            return implode('.', array_slice($keyParts, 0, -1));
        }
        // 对于其他命令（如debug、custom等），也尝试去掉包管理器后缀
        if (count($keyParts) > 1) {
            // 检查最后一个部分是否是包管理器
            $lastPart = end($keyParts);
            $packageManagers = config('terminal.package_managers', []);
            if (isset($packageManagers[$lastPart])) {
                // 重新构建命令键，去掉最后一个部分（包管理器）
                return implode('.', array_slice($keyParts, 0, -1));
            }
        }

        return $key;
    }

    /**
     * 获取命令配置
     *
     * @param string $key 命令key
     *
     * @return array|bool
     */
    public static function getCommand(string $key): bool|array
    {
        if (!$key) {
            return false;
        }

        $commands = config('terminal.commands', []);

        // 支持多级命令解析（如：frontend.admin.npm）
        $keyParts = explode('.', $key);

        $currentLevel = $commands;
        foreach ($keyParts as $part) {
            if (!isset($currentLevel[$part])) {
                // 检查是否是安装或构建命令，尝试去掉包管理器后缀
                // 注意：install.server 是后端命令，不使用包管理器
                if (count($keyParts) > 2 && (in_array($keyParts[0], ['install', 'build']) && $keyParts[1] !== 'server')) {
                    // 重新构建命令键，去掉最后一个部分（包管理器）
                    $newKey = implode('.', array_slice($keyParts, 0, -1));
                    return self::getCommand($newKey);
                }
                // 对于其他命令（如debug、custom等），也尝试去掉包管理器后缀
                if (count($keyParts) > 1) {
                    // 检查最后一个部分是否是包管理器
                    $lastPart = end($keyParts);
                    $packageManagers = config('terminal.package_managers', []);
                    if (isset($packageManagers[$lastPart])) {
                        // 重新构建命令键，去掉最后一个部分（包管理器）
                        $newKey = implode('.', array_slice($keyParts, 0, -1));
                        return self::getCommand($newKey);
                    }
                }
                // 如果是test命令，尝试使用default
                if ($part === 'test' && isset($currentLevel['test']['default'])) {
                    $currentLevel = $currentLevel['test']['default'];
                    break;
                }
                return false;
            }
            $currentLevel = $currentLevel[$part];
        }

        // 如果最终结果不是数组，则包装为命令格式
        if (!is_array($currentLevel)) {
            $command = [
                'cwd'     => base_path(),
                'command' => $currentLevel,
            ];
        } else {
            // 检查是否包含命令配置
            if (isset($currentLevel['cwd']) && isset($currentLevel['command'])) {
                $command = $currentLevel;
            } else {
                // 如果没有明确的命令配置，尝试使用default
                if (isset($currentLevel['default'])) {
                    $command = $currentLevel['default'];
                } else {
                    // 如果没有明确的命令配置，返回false
                    return false;
                }
            }
        }

        // 确保必要的字段存在
        $command = array_merge([
            'cwd'         => base_path(),
            'command'     => '',
            'description' => '',
            'variables'   => [],
            'before'      => null,
            'after'       => null,
        ], $command);

        $command['cwd'] = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $command['cwd']);
        return $command;
    }

    /**
     * 替换命令中的变量
     *
     * @param string $command
     * @param array  $variables
     *
     * @return string
     */
    public function replaceCommandVariables(string $command, array $variables = []): string
    {
        // 合并变量，确保 $this->commandVariables 的优先级高于 $variables
        $allVariables = $this->commandVariables;
        foreach ($variables as $key => $value) {
            // 只有当 $this->commandVariables 中不存在该键时，才使用 $variables 中的值
            if (!isset($allVariables[$key])) {
                $allVariables[$key] = $value;
            }
        }

        foreach ($allVariables as $variable => $value) {
            // 如果值是配置键（如 'npm_package_manager'），则解析配置值
            if (is_string($value) && config($value) !== null) {
                $value = config($value);
            }
            $command = str_replace($variable, $value, $command);
        }

        return $command;
    }

    /**
     * 执行命令并通过输出-Generator（HTTP请求方式）
     *
     * @param string|null $commandKey 命令key
     * @param bool $authentication
     *
     * @return \Generator
     * @throws \Throwable
     */
    public function exec(?string $commandKey = null, bool $authentication = true): \Generator
    {
        $originalCommandKey = $commandKey ?? request()->input('command');
        $command          = self::getCommand($originalCommandKey);
        if (!$command) {
            yield Sse::error('命令不允许执行或不存在: ' . $originalCommandKey, null, $this->uuid);
            return;
        }

        // 存储命令配置
        $this->commandConfig = $command;
        // 使用解析后的命令键（去掉包管理器后缀）
        $this->commandKey = $this->getResolvedCommandKey($originalCommandKey);

        // 检查服务是否启用
        if (!config('terminal.enabled', false)) {
            yield Sse::error(date('Y-m-d H:i',time()).' WEB终端服务未启用', null, $this->uuid);
            return;
        }

        $this->beforeExecution();

        // 执行前阶段
        if (!empty($command['description'])) {
            yield Sse::progress($command['description'], 0, ['stage' => 'before'], $this->uuid);
        }

        yield Sse::progress('连接成功', 5, ['stage' => 'connected'], $this->uuid);

        // 执行前置钩子
        yield from $this->executeIntercept('before', $command);

        // 替换命令变量
        $finalCommand = $this->replaceCommandVariables($command['command'], $command['variables'] ?? []);
        // 替换 cwd 中的变量
        $finalCwd = $this->replaceCommandVariables($command['cwd'], $command['variables'] ?? []);
        yield Sse::progress($finalCommand, 10, ['stage' => 'before', 'command' => $finalCommand], $this->uuid);

        // 执行命令
        $success = $this->executeCommand($finalCommand, $finalCwd);
        if (!$success) {
            yield Sse::error('命令启动失败', null, $this->uuid);
            return;
        }

        yield Sse::progress('命令开始执行', 15, ['stage' => 'executing'], $this->uuid);

        // 实时输出处理
        yield from $this->handleRealTimeOutput();

        // 执行后处理
        yield from $this->afterExecution();
    }

    /**
     * 执行拦截器
     *
     * @param string $stage 阶段（before/after）
     * @param array $command 命令配置
     *
     * @return \Generator
     */
    protected function executeIntercept(string $stage, array $command): \Generator
    {
        // 如果配置了自定义拦截器，使用自定义拦截器
        if (isset($command[$stage])) {
            $hook = $command[$stage];
            if (is_array($hook) && isset($hook['class']) && isset($hook['method'])) {
                $class = $hook['class'];
                $method = $hook['method'];
                
                if (class_exists($class)) {
                    // 检查是否实现了 InterceptInterface 接口
                    if (in_array('core\business\terminal\intercept\InterceptInterface', class_implements($class))) {
                        if (method_exists($class, $method)) {
                            $instance = new $class($this->uuid);
                            $params = $stage === 'after' ? [$this->commandKey, $this->processExitCode ?? 0] : [];
                            $generator = call_user_func_array([$instance, $method], $params);
                            
                            foreach ($generator as $message) {
                                yield $message;
                            }
                            return;
                        }
                    }
                }
            }
        }
        
        // 根据命令类型选择默认拦截器
        $commandType = explode('.', $this->commandKey)[0] ?? 'common';
        $interceptClass = self::$interceptMap[$commandType] ?? self::$interceptMap['common'];
        
        if (class_exists($interceptClass)) {
            // 检查是否实现了 InterceptInterface 接口
            if (in_array('core\business\terminal\intercept\InterceptInterface', class_implements($interceptClass))) {
                $instance = new $interceptClass($this->uuid);
                $method = $stage;
                
                if (method_exists($instance, $method)) {
                    $params = $stage === 'after' ? [$this->commandKey, $this->processExitCode ?? 0] : [];
                    $generator = call_user_func_array([$instance, $method], $params);
                    
                    foreach ($generator as $message) {
                        yield $message;
                    }
                }
            }
        }
    }

    /**
     * 执行钩子方法
     *
     * @param mixed $hook   钩子配置
     * @param array $params 参数
     *
     * @return \Generator
     * @throws \Exception
     */
    protected function executeHook($hook, array $params = []): \Generator
    {
        try {
            if (is_callable($hook)) {
                $result = call_user_func_array($hook, $params);
                if ($result instanceof \Generator) {
                    yield from $result;
                } elseif (is_string($result)) {
                    yield Sse::progress($result, 8, ['stage' => 'hook'], $this->uuid);
                }
            } elseif (is_array($hook) && isset($hook['class']) && isset($hook['method'])) {
                $class = $hook['class'];
                $method = $hook['method'];

                if (class_exists($class)) {
                    if (method_exists($class, $method)) {
                        if ($class === get_class($this)) {
                            $result = call_user_func_array([$this, $method], $params);
                        } else {
                            $reflection = new \ReflectionClass($class);
                            $constructor = $reflection->getConstructor();
                            if ($constructor && $constructor->getNumberOfParameters() > 0) {
                                $instance = new $class($this->uuid);
                            } else {
                                $instance = new $class();
                            }
                            $result = call_user_func_array([$instance, $method], $params);
                        }
                        if ($result instanceof \Generator) {
                            yield from $result;
                        } elseif (is_string($result)) {
                            yield Sse::progress($result, 8, ['stage' => 'hook'], $this->uuid);
                        }
                    } else {
                        yield Sse::progress("方法 {$method} 不存在于类 {$class} 中", 8, ['stage' => 'hook_error'], $this->uuid);
                    }
                } else {
                    yield Sse::progress("类 {$class} 不存在", 8, ['stage' => 'hook_error'], $this->uuid);
                }
            }
        } catch (\Exception $e) {
            yield Sse::progress("执行钩子方法时出错: " . $e->getMessage(), 8, ['stage' => 'hook_error'], $this->uuid);
        }
    }

    /**
     * 执行命令并返回简单结果（用于非SSE上下文）
     *
     * @param string $commandKey  命令key
     * @param array  $variables   命令变量
     * @param string $sessionUuid 会话UUID
     *
     * @return array
     * @throws \Throwable
     */
    public function executeSimple(string $commandKey, array $variables = [], string $sessionUuid = ''): array
    {
        $this->commandKey = $commandKey;
        $this->uuid       = $sessionUuid ?: uniqid('terminal_', true);

        $command = self::getCommand($this->commandKey);
        if (!$command) {
            return ['success' => false, 'message' => '错误：命令不允许执行或不存在: ' . $this->commandKey];
        }

        // 检查服务是否启用
        if (!config('terminal.enabled', false)) {
            return ['success' => false, 'message' => 'Terminal服务未启用'];
        }

        $this->beforeExecution();

        // 合并命令变量
        $allVariables = array_merge($command['variables'] ?? [], $variables);
        $finalCommand = $this->replaceCommandVariables($command['command'], $allVariables);

        // 执行命令
        if (!$this->executeCommand($finalCommand, $command['cwd'])) {
            return ['success' => false, 'message' => '执行失败'];
        }

        // 等待命令执行完成
        $this->waitForCompletion();

        // 获取执行结果 - 优先使用保存的状态数据中的exitcode，因为在Linux环境下proc_close可能返回-1
        $exitCode = -1;
        if (!empty($this->procStatusData) && isset($this->procStatusData['exitcode'])) {
            $exitCode = $this->procStatusData['exitcode'];
        } else if (is_resource($this->process)) {
            $exitCode = proc_close($this->process);
            $this->process = false;
        }


        // 清理资源
        if (is_resource($this->process)) {
            proc_close($this->process);
            $this->process = false;
        }
        $this->pipes = [];
        $this->procStatusMark = 0;
        $this->procStatusData = [];

        return [
            'success' => $exitCode === 0,
            'message' => $exitCode === 0 ? '命令执行成功' : '命令执行失败，代码: ' . $exitCode,
            'exitCode' => $exitCode
        ];
    }

    /**
     * 等待命令执行完成
     */
    protected function waitForCompletion(): void
    {
        while ($this->getProcStatus()) {
            usleep(100000); // 100ms
        }
    }

    /**
     * 执行命令
     *
     * @param string $command
     * @param string $cwd
     *
     * @return bool
     */
    public function executeCommand(string $command, string $cwd): bool
    {
        // 在 Windows 环境下，需要使用 cmd.exe /c 来执行命令
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $command = 'cmd.exe /c ' . $command;
        }
        $this->process = proc_open($command, $this->descriptorsSpec, $this->pipes, $cwd);
        return is_resource($this->process);
    }

    /**
     * 处理实时输出
     *
     * @return \Generator
     * @throws \Exception
     */
    protected function handleRealTimeOutput(): \Generator
    {
        $config       = config('terminal.execution', []);
        $pollInterval = $config['poll_interval'] ?? 500000;

        $this->outputContent = file_get_contents($this->outputFile);
        $lineCount = 0;
        while ($this->getProcStatus()) {
            $contents = file_get_contents($this->outputFile);
            if ($contents !== $this->outputContent) {
                $newOutput = substr($contents, strlen($this->outputContent));
                // 只要有新输出就发送事件，不管是否包含换行符
                if (!empty($newOutput)) {
                    $lineCount++;
                    // 进度 15-90 之间
                    $progress = min(90, 15 + (int)($lineCount * 2));
                    yield Sse::progress($newOutput, $progress, ['stage' => 'output'], $this->uuid);
                    $this->outputContent = $contents;
                }
            }
            usleep($pollInterval);
        }
    }

    /**
     * 执行后处理
     *
     * @return \Generator
     * @throws \Exception
     */
    protected function afterExecution(): \Generator
    {
        // 发送剩余的输出
        $contents = file_get_contents($this->outputFile);
        if ($contents !== $this->outputContent) {
            $newOutput = substr($contents, strlen($this->outputContent));
            if (!empty($newOutput)) {
                yield Sse::progress($newOutput, 90, ['stage' => 'output'], $this->uuid);
            }
        }

        // 优先使用保存的状态数据中的exitcode，因为在Linux环境下proc_close可能返回-1
        $exitCode = -1;
        if (!empty($this->procStatusData) && isset($this->procStatusData['exitcode'])) {
            $exitCode = $this->procStatusData['exitcode'];
        } else if (is_resource($this->process)) {
            $exitCode = proc_close($this->process);
            $this->process = false;
        }
        
        yield Sse::progress('退出代码: ' . $exitCode, 95, ['stage' => 'after', 'exitCode' => $exitCode], $this->uuid);

        $success = $exitCode === 0;
        
        // 保存退出码供拦截器使用
        $this->processExitCode = $exitCode;
        
        // 执行后置方法（无论成功失败都执行）
        yield from $this->executeIntercept('after', $this->commandConfig);

        if ($success) {
            if ($this->successCallback()) {
                yield Sse::progress('命令执行成功', 99, ['stage' => 'after'], $this->uuid);
            } else {
                yield Sse::error('回调执行失败', ['exitCode' => $exitCode], $this->uuid);
                return;
            }
        }

        // 清理资源
        if (is_resource($this->process)) {
            proc_close($this->process);
            $this->process = false;
        }
        $this->pipes = [];
        $this->procStatusMark = 0;
        $this->procStatusData = [];

        // 重新加载Webman（如果是特定命令）
        if ($success && in_array(explode('.', $this->commandKey)[0], ['build', 'backend.composer'])) {
            Util::reloadWebman();
        }

        // 发送完成事件
        yield Sse::completed(
            $success ? 'Success' : '执行失败，退出代码: ' . $exitCode,
            [
                'success' => $success,
                'exitCode' => $exitCode,
                'command' => $this->commandKey,
            ],
            $this->uuid
        );
    }

    /**
     * 获取进程状态
     *
     * @return bool
     */
    public function getProcStatus(): bool
    {
        if (!$this->process) {
            return false;
        }

        $status = proc_get_status($this->process);
        if (!$status['running']) {
            $this->procStatusMark = 2;
            $this->procStatusData = $status; // 保存完整的状态数据，包括exitcode
            return false;
        }

        $this->procStatusMark = 1;
        $this->procStatusData = $status;
        return true;
    }

    /**
     * 获取输出文件路径
     *
     * @return string
     */
    public function getOutputFile(): string
    {
        return $this->outputFile;
    }

    /**
     * 获取进程退出代码
     *
     * @return int
     */
    public function getProcessExitCode(): int
    {
        if ($this->process) {
            $status = proc_get_status($this->process);
            if (isset($status['exitcode'])) {
                return $status['exitcode'];
            }
        }
        return $this->processExitCode;
    }

    /**
     * 成功回调
     *
     * @return bool
     */
    protected function successCallback(): bool
    {
        return true;
    }

    /**
     * 执行前处理
     */
    protected function beforeExecution(): void
    {
        // 初始化命令变量
        $this->initCommandVariables();
        // 初始化管道描述符
        $this->initDescriptors();
        // 可以在这里添加执行前的处理逻辑
    }

    /**
     * 输出过滤器
     *
     * @param string $output
     *
     * @return string
     */
    public static function outputFilter(string $output): string
    {
        // 过滤敏感信息或格式化输出
        return $output;
    }

    /**
     * 清理资源
     */
    public function cleanupResources(): void
    {
        if (is_resource($this->process)) {
            proc_close($this->process);
        }
        $this->process        = false;
        $this->pipes          = [];
        $this->procStatusMark = 0;
        $this->procStatusData = [];
    }

    /**
     * 创建新实例（工厂方法，每次请求创建新实例）
     *
     * @param string $uuid 会话UUID
     * @param string $extend 扩展信息
     * @return Terminal
     */
    public static function create(string $uuid = '', string $extend = ''): Terminal
    {
        return new self($uuid, $extend);
    }
    
    /**
     * 兼容旧代码的别名方法
     * @deprecated 请使用 create() 方法
     * @return Terminal
     */
    public static function instance(): Terminal
    {
        $uuid = request()->input('uuid', '');
        $extend = request()->input('extend', '');
        return self::create($uuid, $extend);
    }
}