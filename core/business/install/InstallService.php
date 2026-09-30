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
 * Official Website: https://madong.tech
 */

namespace core\business\install;

use core\business\terminal\Terminal;
use core\business\install\traits\InstallDatabaseTrait;
use core\business\install\traits\MenuTrait;
use core\business\install\traits\DictTrait;
use core\business\install\traits\MessageTrait;
use core\business\install\traits\ConfigTrait;
use core\business\install\traits\AdminTrait;
use core\foundation\tool\Sse;
use core\foundation\tool\Util;
use Exception;
use Throwable;
use PDO;


/**
 * 安装服务类
 * 基于SSE和生成器模式实现简化安装流程
 */
final class InstallService
{
    use InstallDatabaseTrait;
    use MenuTrait;
    use DictTrait;
    use MessageTrait;
    use ConfigTrait;
    use AdminTrait;
    protected string $lock_file;
    protected ?Terminal $terminalService = null;

    public function __construct()
    {
        $this->lock_file = base_path() . '/install.lock';
        // 不在构造函数中实例化依赖，延迟加载
    }

    /**
     * 创建 PDO 连接（替代 DataImporterService）
     */
    protected function createPdo(string $host, string $username, string $password, int $port = 3306): PDO
    {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * 获取 Terminal 实例
     */
    protected function getTerminal(): Terminal
    {
        if ($this->terminalService === null) {
            $this->terminalService = Terminal::create();
        }
        return $this->terminalService;
    }

    /**
     * 检查是否已安装
     *
     * @return bool
     */
    public function checkInstalled(): bool
    {
        // 输出调试信息
        echo "Checking for install.lock at: {$this->lock_file}\n";
        echo "File exists: " . (file_exists($this->lock_file) ? "Yes" : "No") . "\n";
        return file_exists($this->lock_file);
    }

    /**
     * 获取许可协议数据
     *
     * @return array
     */
    public function getAgreementData(): array
    {
        return [
            'title'       => 'MDAdmin 软件许可协议',
            'content'     => $this->getLicenseAgreement(),
            'version'     => '1.0.0',
            'update_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 检查系统环境
     *
     * @return array
     */
    public function checkEnvironment(): array
    {
        $system_variables = $this->checkSystemRequirements();
        $dirs_list        = $this->checkDirectoryPermissions();
        $all_ok           = $this->checkAllRequirements($system_variables, $dirs_list);

        return [
            'check_items'           => $system_variables,
            'directory_check_items' => $dirs_list,
            'all_requirements_met'  => $all_ok,
            'php_version'           => PHP_VERSION,
            'server_software'       => $_SERVER['SERVER_SOFTWARE'] ?? '未知',
            'operating_system'      => PHP_OS,
            'memory_limit'          => ini_get('memory_limit'),
            'max_execution_time'    => ini_get('max_execution_time'),
        ];
    }

    /**
     * 验证数据库配置
     *
     * @param array $config
     *
     * @return array
     */
    public function validateDatabaseConfig(array $config): array
    {
        $errors = [];
        // 必填字段验证
        $required = ['host', 'port', 'username', 'password', 'database'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                $errors[] = "数据库{$field}不能为空";
            }
        }
        // 端口验证
        if (!is_numeric($config['port']) || $config['port'] <= 0 || $config['port'] > 65535) {
            $errors[] = "数据库端口必须为1-65535之间的数字";
        }
        // 数据库名称验证
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $config['database'])) {
            $errors[] = "数据库名称格式不正确";
        }
        return $errors;
    }

    /**
     * 验证管理员配置
     *
     * @param array $config
     *
     * @return array
     */
    public function validateAdminConfig(array $config): array
    {
        $errors = [];

        // 必填字段验证
        $required = ['username', 'password', 'email'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                $errors[] = "管理员{$field}不能为空";
            }
        }

        // 邮箱格式验证
        if (!filter_var($config['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "邮箱格式不正确";
        }

        // 密码强度验证
        if (strlen($config['password']) < 6) {
            $errors[] = "密码长度不能少于6位";
        }

        return $errors;
    }

    /**
     * 测试数据库连接
     *
     * @param array $config
     *
     * @return array
     * @throws Exception
     */
    public function testDatabaseConnection(array $config): array
    {
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']}";
            $pdo = new \PDO($dsn, $config['username'], $config['password']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $message = '数据库连接成功';
            $redisConnected = false;

            // 测试 Redis 连接（非阻塞，失败不抛异常）
            $redisHost = $config['redisHost'] ?? $config['redis_host'] ?? '';
            $redisPort = $config['redisPort'] ?? $config['redis_port'] ?? 6379;
            $redisPassword = $config['redisPassword'] ?? $config['redis_password'] ?? '';

            if ($redisHost) {
                try {
                    $redis = new \Redis();
                    $redis->connect($redisHost, (int) $redisPort, 3);
                    if ($redisPassword !== '') {
                        $redis->auth($redisPassword);
                    }
                    $redis->ping();
                    $redisConnected = true;
                    $message .= '，Redis 连接成功';
                    $redis->close();
                } catch (\Throwable $e) {
                    $message .= '，Redis 连接失败: ' . $e->getMessage();
                }
            }

            return [
                'connected'       => $redisConnected || true, // MySQL 成功即为整体成功
                'redis_connected' => $redisConnected,
                'message'         => $message,
            ];
        } catch (Exception $e) {
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }

    /**
     * 安装-SSE模式
     * 
     * 流程：1.验证安装状态 -> 2.环境检测 -> 3.数据库配置 -> 4.参数配置 -> 5.执行安装 -> 6.创建.env(最后)
     *
     * @param array $params
     *
     * @return \Generator
     * @throws \Exception
     */
    public function install(array $params): \Generator
    {
        $sessionUuid = uniqid('install_', true);
        yield Sse::progress('> 执行安装', 1, [], $sessionUuid);
        try {
            // 1. 验证安装状态 (1-10%)
            yield Sse::progress('> 1/6 验证安装状态', 5, [], $sessionUuid);
            if ($this->checkInstalled()) {
                throw new Exception('系统已安装，请勿重复安装');
            }
            
            // 2. 环境检测 (10-15%)
            yield Sse::progress('> 2/6 环境检测...', 12, [], $sessionUuid);
            if (!$this->checkEnvironment()['all_requirements_met']) {
                throw new Exception('系统环境不满足安装要求');
            }
            
            // 3. 数据库配置验证 (15-20%)
            yield Sse::progress('> 3/6 数据库配置验证', 17, [], $sessionUuid);
            $dbParams = $this->extractPrefixedData($params, 'db_');
            $errors   = $this->validateDatabaseConfig($dbParams);
            if (!empty($errors)) {
                throw new Exception('安装参数验证失败: ' . implode(', ', $errors));
            }
            
            // 4. 参数配置验证 (20-25%)
            yield Sse::progress('> 4/6 参数配置验证', 22, [], $sessionUuid);
            $adminParams = $this->extractPrefixedData($params, 'admin_');
            $errors      = $this->validateAdminConfig($adminParams);
            if (!empty($errors)) {
                throw new Exception('安装参数验证失败: ' . implode(', ', $errors));
            }

            // 提取多租户开关
            $enableTenant = !empty($params['enable_tenant']) && $params['enable_tenant'] == '1';

            // 提取 Redis 配置参数
            $redisParams = $this->extractPrefixedData($params, 'redis_');

            // 测试数据库连接 (25-30%)
            yield Sse::progress('> 测试数据库连接', 28, [], $sessionUuid);
            $this->testDatabaseConnection($dbParams);

            // 5. 执行安装 (30-60%)
            if ($params['install_database'] == 1) {
                yield Sse::progress('> 5/6 执行数据库安装', 30, [], $sessionUuid);
                yield from $this->installDatabaseTables($dbParams, $adminParams, $sessionUuid, $enableTenant);
            }

            if ($params['install_database'] == 0) {
                yield Sse::progress('> 5/6 创建系统管理员', 30, [], $sessionUuid);
                yield from $this->initializeAdmin($dbParams, $adminParams, $sessionUuid, $enableTenant);
            }

            // 前端构建 (60-80%)
            if ($params['build_project'] == 1) {
                yield Sse::progress('> 检查前端项目', 60, [], $sessionUuid);
                yield from $this->processFrontendProjects($sessionUuid, $enableTenant);
            }

            // 创建安装锁 (80-90%)
            yield Sse::progress('> 创建安装锁', 85, [], $sessionUuid);
            $this->createInstallLock();
            
            // 6. 创建 .env 文件 (90-99%)
            yield Sse::progress('> 6/6 创建.env配置文件', 95, [], $sessionUuid);
            $this->copyDatabaseTemplateAndCreateEnv($dbParams, $enableTenant, $redisParams);

            // 完成安装 (100%)
           yield Sse::completed('安装完成', [], $sessionUuid);
            Util::reloadWebman();
            return;
        } catch (Throwable $e) {
            yield Sse::runtimeError($e->getMessage(), [], $sessionUuid);
            return;
        }
    }

    /**
     * 检查前端项目目录是否存在
     * 前端代码统一在 template 目录下
     *
     * @param string $projectType
     *
     * @return bool
     */
    private function checkFrontendProjectExists(string $projectType): bool
    {
        $baseDir      = dirname(base_path());
        $projectPaths = [
            'admin'    => $baseDir . '/template/mono/apps/admin',
            'platform' => $baseDir . '/template/mono/apps/platform',
            'web'      => $baseDir . '/template/web',
            'uni-app'  => $baseDir . '/template/uni-app',
        ];
        $projectDir  = $projectPaths[$projectType] ?? $baseDir . '/template/' . $projectType;
        $packageJson = $projectDir . '/package.json';

        return is_dir($projectDir) && file_exists($packageJson);
    }

    /**
     * 检查构建目录是否存在
     *
     * @param string $projectType
     *
     * @return bool
     */
    private function checkBuildDirectoryExists(string $projectType): bool
    {
        $config = config("terminal.frontend_programs.{$projectType}");
        if (!$config || !$config['enabled']) {
            return false;
        }

        // 处理不同项目的构建目录路径
        $sourceDir = $config['source_dir'] ?? '';

        // 替换路径变量
        $sourceDir = $this->replacePathVariables($sourceDir);

        // 标准化路径分隔符（Windows兼容）
        $sourceDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourceDir);

        // 检查构建目录是否存在
        if (!file_exists($sourceDir)) {
            error_log("构建目录不存在: " . $sourceDir);
            return false;
        }

        // 检查构建目录是否为空
        $files = glob($sourceDir . DIRECTORY_SEPARATOR . '*');
        if (empty($files)) {
            error_log("构建目录为空: " . $sourceDir);
            return false;
        }

        return true;
    }

    /**
     * 等待构建完成-带重试机制
     *
     * @param string $projectType
     * @param int    $maxRetries
     * @param int    $retryInterval
     *
     * @return bool
     */
    private function waitForBuildCompletion(string $projectType, int $maxRetries = 5, int $retryInterval = 2000000): bool
    {
        $config = config("terminal.frontend_programs.{$projectType}");
        if (!$config || !$config['enabled']) {
            return false;
        }

        $sourceDir = $config['source_dir'] ?? '';
        // 替换路径变量
        $sourceDir = $this->replacePathVariables($sourceDir);
        $sourceDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourceDir);

        // 等待构建目录创建
        for ($i = 0; $i < $maxRetries; $i++) {
            if (file_exists($sourceDir)) {
                // 检查目录是否包含文件（非空）
                $files = glob($sourceDir . DIRECTORY_SEPARATOR . '*');
                if (!empty($files)) {
                    // 额外等待1000ms确保文件完全写入
                    usleep(1000000);
                    return true;
                }
            }
            usleep($retryInterval);
        }

        error_log("等待构建完成超时: " . $sourceDir);
        return false;
    }

    /**
     * 检查并等待构建结果
     *
     * @param string $projectType
     * @param string $sessionUuid
     *
     * @return \Generator
     * @throws \Exception
     */
    private function checkAndWaitForBuildResult(string $projectType, string $sessionUuid): \Generator
    {
        yield Sse::progress("检查{$projectType}构建结果...", 58, [], $sessionUuid);

        // 等待构建完成
        if (!$this->waitForBuildCompletion($projectType)) {
            yield Sse::progress("{$projectType} 构建超时或失败", 59, [], $sessionUuid);
            return false;
        }

        // 最终检查构建目录
        if ($this->checkBuildDirectoryExists($projectType)) {
            yield Sse::progress("{$projectType} 构建成功", 59, [], $sessionUuid);
            return true;
        } else {
            yield Sse::progress("{$projectType} 构建失败或目录为空", 59, [], $sessionUuid);
            return false;
        }
    }

    /**
     * 智能处理前端项目
     *
     * @param string $sessionUuid
     *
     * @return \Generator
     * @throws \Exception|\Throwable
     */
    private function processFrontendProjects(string $sessionUuid, bool $enableTenant = false): \Generator
    {
        $frontendProjects  = $enableTenant ? ['admin', 'platform', 'web'] : ['admin', 'web'];
        $availableProjects = [];

        // 检查可用的前端项目
        foreach ($frontendProjects as $project) {
            if ($this->checkFrontendProjectExists($project)) {
                $availableProjects[] = $project;
                yield Sse::progress("检测到 {$project} 前端项目", 36, [], $sessionUuid);
            } else {
                yield Sse::progress("跳过 {$project} 前端项目（目录不存在）", 36, [], $sessionUuid);
            }
        }

        if (empty($availableProjects)) {
            yield Sse::progress('未检测到任何前端项目，跳过前端处理', 37, [], $sessionUuid);
            return;
        }

        // 安装依赖 (60-65%)
        yield Sse::progress('安装前端依赖...', 62, [], $sessionUuid);
        foreach ($availableProjects as $project) {
            yield from $this->installFrontendDependencies($project, $sessionUuid);
        }

        // 构建前端 (65-70%)
        yield Sse::progress('构建前端项目...', 67, [], $sessionUuid);
        foreach ($availableProjects as $project) {
            yield from $this->buildFrontend($project, $sessionUuid);
        }

        // 检查构建结果 (70-75%)
        yield Sse::progress('检查构建结果...', 72, [], $sessionUuid);
        $builtProjects = [];
        foreach ($availableProjects as $project) {
            if (yield from $this->checkAndWaitForBuildResult($project, $sessionUuid)) {
                $builtProjects[] = $project;
            }
        }

        if (empty($builtProjects)) {
            yield Sse::progress('所有前端项目构建失败，跳过文件迁移', 75, [], $sessionUuid);
            return;
        }

        // 迁移构建文件 (75-80%)
        yield Sse::progress('迁移前端构建文件...', 77, [], $sessionUuid);
        yield from $this->migrateFrontendBuildFiles($sessionUuid, $builtProjects);
    }

    /**
     * 安装前端依赖（通用方法）
     *
     * @param string $projectType
     * @param string $sessionUuid
     *
     * @return \Generator
     * @throws \Throwable
     */
    private function installFrontendDependencies(string $projectType, string $sessionUuid): \Generator
    {
        try {
            // 获取配置的包管理器
            $packageManager = config('terminal.npm_package_manager', 'pnpm');
            $command        = "install.{$projectType}";

            // 使用异步执行方法提供实时输出
            $progressStep = $projectType === 'admin' ? 42 : 44;
            yield from $this->executeCommandWithRealTimeOutput($command, "{$projectType}前端依赖安装", $sessionUuid, $progressStep);

        } catch (Exception $e) {
            throw new Exception("{$projectType}前端依赖安装失败: " . $e->getMessage());
        }
    }

    /**
     * 构建前端-异步执行方法
     *
     * @param string $projectType
     * @param string $sessionUuid
     *
     * @return \Generator
     * @throws \Throwable
     */
    private function buildFrontend(string $projectType, string $sessionUuid): \Generator
    {
        try {
            // 获取配置的包管理器
            $packageManager = config('terminal.npm_package_manager', 'pnpm');
            $command        = "build.{$projectType}.{$packageManager}";

            // 使用异步执行方法提供实时输出
            $progressStep = $projectType === 'admin' ? 52 : 54;
            yield from $this->executeCommandWithRealTimeOutput($command, "{$projectType}前端构建", $sessionUuid, $progressStep);

        } catch (Exception $e) {
            throw new Exception("{$projectType}前端构建失败: " . $e->getMessage());
        }
    }

    /**
     * 迁移前端构建文件-仅迁移存在的项目
     *
     * @param string $sessionUuid
     * @param array  $availableProjects
     *
     * @return \Generator
     * @throws \Exception
     */
    private function migrateFrontendBuildFiles(string $sessionUuid, array $availableProjects): \Generator
    {
        try {
            $progressStep = 61;
            foreach ($availableProjects as $project) {
                yield Sse::progress("迁移{$project}前端构建文件...", $progressStep, [], $sessionUuid);
                $this->migrateFrontendProgram($project);
                $progressStep++;
            }

            yield Sse::progress('前端构建文件迁移完成', $progressStep, [], $sessionUuid);
        } catch (Exception $e) {
            throw new Exception('前端构建文件迁移失败: ' . $e->getMessage());
        }
    }

    /**
     * 迁移前端构建文件
     *
     * @param string $frontendType 前端类型（admin/web/uni-app）
     * @return bool
     */
    private function migrateFrontendProgram(string $frontendType): bool
    {
        $config = config('terminal.frontend_programs', []);
        
        if (!isset($config[$frontendType]) || !$config[$frontendType]['enabled']) {
            return false;
        }

        // 处理uni-app等多平台程序
        $programConfig = $config[$frontendType];

        $sourceDir = $programConfig['source_dir'] ?? '';
        $targetDir = $programConfig['target_dir'] ?? '';
        $copyMappings = $programConfig['copy_mappings'] ?? [];
        $cleanTarget = $programConfig['clean_target'] ?? true;
        $preserveFiles = $programConfig['preserve_files'] ?? [];
        $copyOptions = $programConfig['copy_options'] ?? [];

        // 替换路径变量
        $sourceDir = $this->replacePathVariables($sourceDir);
        $targetDir = $this->replacePathVariables($targetDir);

        if (empty($sourceDir) || empty($targetDir)) {
            return false;
        }

        try {
            // 复制构建文件
            return $this->copyBuildFiles($sourceDir, $targetDir, $copyMappings, $cleanTarget, $preserveFiles, $copyOptions);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 替换路径中的变量
     *
     * @param string $path 路径
     *
     * @return string
     */
    private function replacePathVariables(string $path): string
    {
        // 后端根目录（server目录）
        $backendRoot = base_path();
        // 项目根目录（包含server、frontend等目录的父目录）
        $projectRoot = dirname($backendRoot);
        // 前端根目录（frontend目录）
        $frontendRoot = $projectRoot . DIRECTORY_SEPARATOR . 'frontend';
        // 前端模板monorepo目录
        $templateRoot = $projectRoot . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'mono';
        
        $variables = [
            '{backend_root}' => $backendRoot,
            '{project_root}' => $projectRoot,
            '{frontend_root}' => $frontendRoot,
            '{template_root}' => $templateRoot,
        ];
        
        foreach ($variables as $variable => $value) {
            $path = str_replace($variable, $value, $path);
        }
        
        return $path;
    }

    /**
     * 复制构建文件
     *
     * @param string $sourceDir 源目录
     * @param string $targetDir 目标目录
     * @param array $copyMappings 复制映射
     * @param bool $cleanTarget 是否清理目标目录
     * @param array $preserveFiles 保留的文件
     * @param array $copyOptions 复制选项
     *
     * @return bool
     */
    private function copyBuildFiles(string $sourceDir, string $targetDir, array $copyMappings = [], bool $cleanTarget = true, array $preserveFiles = [], array $copyOptions = []): bool
    {
        if (!is_dir($sourceDir)) {
            return false;
        }

        // 确保目标目录存在
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // 清理目标目录（如果需要）
        if ($cleanTarget && is_dir($targetDir)) {
            $this->cleanDirectory($targetDir, $preserveFiles);
        }

        // 如果没有配置复制映射，默认复制所有文件
        if (empty($copyMappings)) {
            $copyMappings = ['*' => '.'];
        }

        // 执行复制
        foreach ($copyMappings as $source => $target) {
            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $source;
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $target;

            // 确保目标路径存在
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }

            // 处理通配符复制
            if (str_contains($source, '*')) {
                // 转换路径分隔符为正斜杠，确保 glob 函数在 Windows 上正常工作
                $globPath = str_replace(DIRECTORY_SEPARATOR, '/', $sourcePath);
                $files = glob($globPath);
                if (!empty($files)) {
                    foreach ($files as $file) {
                        $destFile = $targetPath . DIRECTORY_SEPARATOR . basename($file);
                        if (is_dir($file)) {
                            // 递归复制目录
                            $this->copyDirectory($file, $destFile);
                        } else {
                            // 复制文件
                            copy($file, $destFile);
                        }
                    }
                }
            } elseif (is_dir($sourcePath)) {
                // 复制目录
                $this->copyDirectory($sourcePath, $targetPath);
            } else {
                // 复制单个文件
                if (file_exists($sourcePath)) {
                    copy($sourcePath, $targetPath);
                }
            }
        }

        return true;
    }

    /**
     * 复制目录
     *
     * @param string $source 源目录
     * @param string $target 目标目录
     *
     * @return bool
     */
    private function copyDirectory(string $source, string $target): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        // 创建目标目录
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $targetPath = $target . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($sourcePath, $targetPath);
            }
        }

        return true;
    }

    /**
     * 清理目录
     *
     * @param string $directory 目录路径
     * @param array $preserveFiles 保留的文件
     */
    private function cleanDirectory(string $directory, array $preserveFiles = []): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $file;
            if (in_array($file, $preserveFiles)) {
                continue;
            }

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
    }

    /**
     * 删除目录
     *
     * @param string $directory 目录路径
     */
    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    /**
     * 异步执行命令并提供实时输出
     *
     * @param string $commandKey   命令键值
     * @param string $description  命令描述
     * @param string $sessionUuid  会话UUID
     * @param int    $progressStep 进度步骤
     *
     * @return \Generator
     * @throws \Throwable
     */
    private function executeCommandWithRealTimeOutput(string $commandKey, string $description, string $sessionUuid, int $progressStep): \Generator
    {
        // 检查命令是否存在
        $command = \core\business\terminal\Terminal::getCommand($commandKey);
        if (!$command) {
            throw new Exception('错误：命令不允许执行或不存在: ' . $commandKey);
        }

        // 检查服务是否启用
        if (!config('terminal.enabled', false)) {
            throw new Exception('Terminal服务未启用');
        }

        // 开始执行
        yield Sse::progress("开始{$description}...", $progressStep - 1, [], $sessionUuid);

        // 显示命令描述（如果有）
        if (!empty($command['description'])) {
            yield Sse::progress('> ' . $command['description'], $progressStep - 1, [], $sessionUuid);
        }

        // 替换命令变量
        $terminal     = Terminal::create();
        $finalCommand = $terminal->replaceCommandVariables($command['command'], $command['variables'] ?? []);
        // 替换 cwd 中的变量
        $finalCwd = $terminal->replaceCommandVariables($command['cwd'], $command['variables'] ?? []);
        yield Sse::progress('> ' . $finalCommand, $progressStep - 1, [], $sessionUuid);

        // 执行命令
        if (!$terminal->executeCommand($finalCommand, $finalCwd)) {
            throw new Exception('执行失败');
        }

        // 处理实时输出
        $config       = config('terminal.execution', []);
        $pollInterval = $config['poll_interval'] ?? 500000;

        // 获取输出文件路径
        $outputFile    = $terminal->getOutputFile();
        $outputContent = file_get_contents($outputFile);

        // 获取进程状态
        while ($terminal->getProcStatus()) {
            $contents = file_get_contents($outputFile);
            if ($contents !== $outputContent) {
                $newOutput = substr($contents, strlen($outputContent));
                // 发送实时输出，无论是否包含换行符
                yield Sse::progress($newOutput, $progressStep - 1, [], $sessionUuid);
                $outputContent = $contents;
            }
            usleep($pollInterval);
        }

        // 检查是否有剩余输出
        $contents = file_get_contents($outputFile);
        if ($contents !== $outputContent) {
            $newOutput = substr($contents, strlen($outputContent));
            yield Sse::progress($newOutput, $progressStep - 1, [], $sessionUuid);
        }

        // 获取执行结果
        $exitCode = $terminal->getProcessExitCode();

        if ($exitCode === 0) {
            yield Sse::progress("{$description}完成", $progressStep, [], $sessionUuid);
        } else {
            throw new Exception("{$description}失败，退出代码: " . $exitCode);
        }

        // 清理资源
        $terminal->cleanupResources();
    }

    /**
     * 提取带前缀的数据
     *
     * @param array  $data
     * @param string $prefix
     *
     * @return array
     */
    private function extractPrefixedData(array $data, string $prefix): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $result[substr($key, strlen($prefix))] = $value;
            }
        }
        return $result;
    }

    /**
     * 检查系统要求
     *
     * @return array
     */
    private function checkSystemRequirements(): array
    {
        $system_variables = [];

        // PHP版本检查
        $min_php_version     = '8.2.0';
        $current_php_version = PHP_VERSION;
        $php_version_ok      = version_compare($current_php_version, $min_php_version) >= 0;
        $system_variables[]  = [
            "name"        => "PHP版本",
            "current"     => $current_php_version,
            "need"        => ">= " . $min_php_version,
            "status"      => $php_version_ok,
            "status_text" => $php_version_ok ? '符合' : '不符合',
            'message'     => $php_version_ok ?
                "PHP版本符合要求 ({$current_php_version})" :
                "PHP版本过低，当前版本 {$current_php_version}，需要 {$min_php_version} 或更高版本",
            "is_required" => true,
        ];

        // 内存限制检查
        $memory_limit       = ini_get('memory_limit');
        $min_memory         = '128M';
        $memory_ok          = $this->convertMemoryToBytes($memory_limit) >= $this->convertMemoryToBytes($min_memory);
        $system_variables[] = [
            "name"        => "内存限制",
            "current"     => $memory_limit,
            "need"        => ">= " . $min_memory,
            "status"      => $memory_ok,
            "status_text" => $memory_ok ? '符合' : '建议提升',
            'message'     => $memory_ok ?
                "内存限制符合要求 ({$memory_limit})" :
                "建议将内存限制提升到 {$min_memory} 或更高，当前为 {$memory_limit}",
            "is_required" => false,
        ];

        // 最大执行时间检查
        $max_execution_time = ini_get('max_execution_time');
        $min_execution_time = 30;
        $time_ok            = intval($max_execution_time) >= $min_execution_time || intval($max_execution_time) === 0;
        $system_variables[] = [
            "name"        => "最大执行时间",
            "current"     => $max_execution_time . '秒',
            "need"        => ">= " . $min_execution_time . '秒',
            "status"      => $time_ok,
            "status_text" => $time_ok ? '符合' : '建议提升',
            'message'     => $time_ok ?
                "执行时间符合要求 ({$max_execution_time}秒)" :
                "建议将最大执行时间提升到 {$min_execution_time} 秒或更高，当前为 {$max_execution_time} 秒",
            "is_required" => false,
        ];

        // 文件上传大小限制检查
        $upload_max_filesize = ini_get('upload_max_filesize');
        $post_max_size       = ini_get('post_max_size');
        $min_upload_size     = '2M';
        $upload_ok           = $this->convertMemoryToBytes($upload_max_filesize) >= $this->convertMemoryToBytes($min_upload_size);
        $system_variables[]  = [
            "name"        => "文件上传限制",
            "current"     => $upload_max_filesize,
            "need"        => ">= " . $min_upload_size,
            "status"      => $upload_ok,
            "status_text" => $upload_ok ? '符合' : '建议提升',
            'message'     => $upload_ok ?
                "文件上传限制符合要求 ({$upload_max_filesize})" :
                "建议将文件上传限制提升到 {$min_upload_size} 或更高，当前为 {$upload_max_filesize}",
            "is_required" => false,
        ];

        // 扩展检查
        $extensions = [
            'pdo'       => ['name' => 'PDO扩展', 'required' => true, 'description' => '数据库连接必需'],
            'pdo_mysql' => ['name' => 'PDO MySQL扩展', 'required' => true, 'description' => 'MySQL数据库连接必需'],
            'curl'      => ['name' => 'CURL扩展', 'required' => true, 'description' => 'HTTP请求处理必需'],
            'openssl'   => ['name' => 'OpenSSL扩展', 'required' => true, 'description' => '加密和安全通信必需'],
            'gd'        => ['name' => 'GD库', 'required' => true, 'description' => '图像处理必需'],
            'fileinfo'  => ['name' => 'Fileinfo扩展', 'required' => true, 'description' => '文件类型检测必需'],
            'json'      => ['name' => 'JSON扩展', 'required' => true, 'description' => 'JSON数据处理必需'],
            'mbstring'  => ['name' => 'MBString扩展', 'required' => true, 'description' => '多字节字符串处理必需'],
            'sodium'    => ['name' => 'Sodium扩展', 'required' => false, 'description' => '现代加密算法（可选）'],
            'imagick'   => ['name' => 'Imagick扩展', 'required' => false, 'description' => '高级图像处理（可选）'],
            'zip'       => ['name' => 'Zip扩展', 'required' => false, 'description' => '压缩文件处理（可选）'],
        ];

        foreach ($extensions as $ext => $info) {
            $status             = extension_loaded($ext);
            $system_variables[] = [
                "name"        => $info['name'],
                "current"     => $status ? '已安装' : '未安装',
                "need"        => $info['required'] ? "必需" : "可选",
                "status"      => $status,
                "status_text" => $status ? '已安装' : ($info['required'] ? '缺失' : '未安装'),
                "message"     => $status ?
                    "{$info['name']}已安装" :
                    ($info['required'] ?
                        "{$info['name']}未安装 - {$info['description']}" :
                        "{$info['name']}未安装 - {$info['description']}（可选）"),
                "is_required" => $info['required'],
            ];
        }
        return $system_variables;
    }

    /**
     * 检查目录权限
     *
     * @return array
     */
    private function checkDirectoryPermissions(): array
    {
        $root_path = base_path();
        $dirs_list = [
            // 必需读写目录
            [
                "path"            => $root_path,
                "path_name"       => "网站根目录",
                "name"            => "root_dir",
                "required"        => true,
                "permission_type" => "read_write", // 新增：明确权限类型
                "description"     => "系统根目录，需要读写权限",
            ],
            // 必需写权限文件
            [
                "path"            => $root_path . "/.env",
                "path_name"       => ".env文件",
                "name"            => "env_file",
                "required"        => true,
                "permission_type" => "write_only", // 新增：明确权限类型
                "description"     => "环境配置文件，需要写入权限",
            ],
            // 必需读写目录
            [
                "path"            => $root_path . '/runtime',
                "path_name"       => "runtime目录",
                "name"            => "runtime_dir",
                "required"        => true,
                "permission_type" => "read_write",
                "description"     => "运行时目录，需要读写权限",
            ],
            [
                "path"            => $root_path . '/public/upload',
                "path_name"       => "upload目录",
                "name"            => "upload_dir",
                "required"        => true,
                "permission_type" => "read_write",
                "description"     => "文件上传目录，需要读写权限",
            ],
            [
                "path"            => $root_path . '/storage',
                "path_name"       => "storage目录",
                "name"            => "storage_dir",
                "required"        => true,
                "permission_type" => "read_write",
                "description"     => "存储目录，需要读写权限",
            ],
            [
                "path"            => $root_path . '/public/install',
                "path_name"       => "install目录",
                "name"            => "install_dir",
                "required"        => true,
                "permission_type" => "read_write",
                "description"     => "安装目录，需要读写权限",
            ],
            // 可选读权限目录
            [
                "path"            => $root_path . '/vendor',
                "path_name"       => "vendor目录",
                "name"            => "vendor_dir",
                "required"        => false,
                "permission_type" => "read_only", // 新增：明确权限类型
                "description"     => "依赖包目录，需要读取权限",
            ],
            [
                "path"            => $root_path . '/config',
                "path_name"       => "config目录",
                "name"            => "config_dir",
                "required"        => false,
                "permission_type" => "read_only",
                "description"     => "配置目录，需要读取权限",
            ],
        ];

        foreach ($dirs_list as $k => $v) {
            // 如果是文件，检查文件权限
            if (str_contains($v["path"], '.') && !is_dir($v["path"])) {
                // 文件不存在，尝试创建
                if (!file_exists($v["path"])) {
                    $dir = dirname($v["path"]);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    @touch($v["path"]);
                }
            } else {
                // 目录不存在，尝试创建
                if (!file_exists($v["path"])) {
                    @mkdir($v["path"], 0755, true);
                }
            }

            $is_readable = is_readable($v["path"]);
            $is_writable = is_writable($v["path"]);
            $exists      = file_exists($v["path"]);

            $dirs_list[$k]["exists"]      = $exists;
            $dirs_list[$k]["is_readable"] = $is_readable;
            $dirs_list[$k]["is_writable"] = $is_writable;

            // 根据权限类型判断状态
            switch ($v["permission_type"]) {
                case "read_write":
                    $dirs_list[$k]["status"]      = $exists && $is_readable && $is_writable;
                    $dirs_list[$k]["status_text"] = $dirs_list[$k]["status"] ? '正常' : '异常';
                    $dirs_list[$k]["message"]     = $dirs_list[$k]["status"] ?
                        "{$v['path_name']}权限正常" :
                        (!$exists ? "{$v['path_name']}不存在" :
                            (!$is_readable ? "{$v['path_name']}不可读" : "{$v['path_name']}不可写"));
                    break;

                case "write_only":
                    $dirs_list[$k]["status"]      = $exists && $is_writable;
                    $dirs_list[$k]["status_text"] = $dirs_list[$k]["status"] ? '正常' : '异常';
                    $dirs_list[$k]["message"]     = $dirs_list[$k]["status"] ?
                        "{$v['path_name']}权限正常" :
                        (!$exists ? "{$v['path_name']}不存在" : "{$v['path_name']}不可写");
                    break;

                case "read_only":
                    $dirs_list[$k]["status"]      = $exists && $is_readable;
                    $dirs_list[$k]["status_text"] = $dirs_list[$k]["status"] ? '正常' : '警告';
                    $dirs_list[$k]["message"]     = $dirs_list[$k]["status"] ?
                        "{$v['path_name']}权限正常" :
                        (!$exists ? "{$v['path_name']}不存在" : "{$v['path_name']}不可读");
                    break;
            }
        }

        return $dirs_list;
    }

    /**
     * 检查所有要求
     *
     * @param array $system_variables
     * @param array $dirs_list
     *
     * @return bool
     */
    private function checkAllRequirements(array $system_variables, array $dirs_list): bool
    {
        // 检查系统变量
        foreach ($system_variables as $variable) {
            if ($variable['is_required'] && $variable['status'] !== true) {
                return false;
            }
        }

        // 检查目录权限
        foreach ($dirs_list as $dir) {
            if ($dir['required'] && !$dir['status']) {
                return false;
            }
        }

        return true;
    }

    /**
     * 初始化管理员（不安装数据库时使用）
     * 
     * 使用 PDO 直接创建管理员，不依赖框架 Db
     *
     * @throws \Exception
     */
    private function initializeAdmin(array $dbParams, array $adminParams, string $sessionUuid, bool $enableTenant = false): \Generator
    {
        yield Sse::progress('📝 创建管理员账户', 50, [], $sessionUuid);

        // 使用 Trait 创建管理员
        $this->initDbConfig([
            'host' => $dbParams['host'],
            'port' => $dbParams['port'],
            'database' => $dbParams['database'],
            'username' => $dbParams['username'],
            'password' => $dbParams['password'],
            'prefix' => $dbParams['prefix'] ?? 'ma_',
        ]);
        $this->createAdmin($adminParams, $enableTenant);

        yield Sse::progress('✅ 管理员账户创建完成', 55, [], $sessionUuid);
    }

    /**
     * 安装数据库表（直接通过 PDO 执行迁移）
     * 
     * 每次安装时：
     * 1. 创建数据库
     * 2. 通过 PDO 执行迁移创建表
     * 3. 运行种子数据（通过 Trait）
     *
     * @param array $dbParams
     * @param array $adminParams
     * @param string $sessionUuid 会话UUID
     *
     * @return \Generator
     * @throws \Exception
     */
    private function installDatabaseTables(array $dbParams, array $adminParams, string $sessionUuid, bool $enableTenant = false): \Generator
    {
        try {
            // 1. 连接数据库并执行安装
            $pdo = $this->createPdo(
                $dbParams['host'],
                $dbParams['username'],
                $dbParams['password'],
                (int) $dbParams['port']
            );

            $database = $dbParams['database'];

            // 2. 检查数据库是否存在
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$database}'");
            $dbExists = $stmt->rowCount() > 0;

            if ($dbExists) {
                // 删除现有数据库
                yield Sse::progress('> 删除旧数据库', 31, [], $sessionUuid);
                $pdo->exec("DROP DATABASE `{$database}`");
            }

            // 3. 创建数据库
            yield Sse::progress('> 创建数据库', 32, [], $sessionUuid);
            $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$database}`");

            // 4. 执行迁移创建表 (30-40%)
            yield Sse::progress('> 执行数据库结构安装', 33, [], $sessionUuid);
            yield from $this->runMigrations($dbParams, $sessionUuid);

            yield Sse::progress('> 数据库结构安装完成', 40, [], $sessionUuid);

            // 5. 运行种子数据 (40-50%)
            yield from $this->runAllSeeders($dbParams, $adminParams, $sessionUuid, $enableTenant);

        } catch (\Exception $e) {
            throw new Exception('数据库安装失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 执行迁移创建表
     * 
     * 使用框架 Db 执行迁移
     * 
     * @param array $dbParams 数据库参数
     * @param string $sessionUuid
     * @return \Generator
     */
    private function runMigrations(array $dbParams, string $sessionUuid): \Generator
    {
        // 获取迁移文件目录
        $migrationDir = base_path('resource/database/migrations');
        
        if (!is_dir($migrationDir)) {
            yield Sse::progress('⚠️ 迁移目录不存在', 33, [], $sessionUuid);
            return;
        }

        $files = glob($migrationDir . '/*.php');
        if (empty($files)) {
            yield Sse::progress('⚠️ 没有找到迁移文件', 33, [], $sessionUuid);
            return;
        }

        // 排序文件
        sort($files);

        // 创建 PDO 连接
        try {
            $pdo = new \PDO(
                "mysql:host={$dbParams['host']};port={$dbParams['port']};dbname={$dbParams['database']};charset=utf8mb4",
                $dbParams['username'],
                $dbParams['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Exception $e) {
            yield Sse::progress('⚠️ PDO 连接失败: ' . $e->getMessage(), 33, [], $sessionUuid);
            return;
        }

        $total = count($files);
        $current = 0;
        
        foreach ($files as $file) {
            $basename = basename($file, '.php');
            $current++;
            // 迁移进度 30-40%
            $progress = 30 + ($current / $total) * 10;
            
            try {
                // 加载迁移文件
                $migrationClass = require $file;
                
                // 捕获 echo 输出
                ob_start();
                
                // 执行迁移的 up 方法
                if (method_exists($migrationClass, 'up')) {
                    // 获取 Schema Builder
                    $schema = $this->createSchemaBuilder($pdo, $dbParams['prefix'] ?? 'ma_', $dbParams['database'] ?? 'madong');
                    $migrationClass->up($schema);
                }
                
                // 获取捕获的输出
                $output = ob_get_clean();
                
                // 通过 SSE 返回迁移输出
                if (!empty($output)) {
                    yield Sse::progress($output, (int)$progress, [], $sessionUuid);
                }
                
                yield Sse::progress("> 迁移: {$basename}", (int)$progress, [], $sessionUuid);
            } catch (\Exception $e) {
                ob_end_clean(); // 清理捕获的输出
                yield Sse::progress("⚠️ 迁移失败 {$basename}: " . $e->getMessage(), (int)$progress, [], $sessionUuid);
                // 记录详细错误日志
                error_log("Migration error in {$basename}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }
        
        yield Sse::progress('✅ 迁移执行完成', 40, [], $sessionUuid);
    }

    /**
     * 创建 Schema Builder 实例
     */
    private function createSchemaBuilder(\PDO $pdo, string $prefix, string $database): \Illuminate\Database\Schema\Builder
    {
        // 使用 'mysql' 作为连接名，匹配 config('database.default') 返回值
        $connectionName = 'mysql';

        // 创建连接
        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => $database,
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => $prefix,
            'strict' => false,
            'engine' => 'InnoDB',
        ], $connectionName);

        // 更新默认连接名，使静态方法也能正确找到连接
        $capsule->getContainer()['config']['database.default'] = $connectionName;

        // 获取连接并注入 PDO
        $connection = $capsule->getConnection($connectionName);
        $connection->setPdo($pdo);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule->schema();
    }

    /**
     * 配置数据库连接用于迁移（使用 .env 文件）
     * 
     * 保存数据库配置到 .env 文件
     * 用于数据库测试成功后，为后续安装步骤配置数据库连接
     */
    public function saveDatabaseConfig(array $dbParams): void
    {
        $envFile = base_path('.env');
        $envTemplateFile = base_path('.example.env');
        
        // 如果 .env 不存在或为空，从模板创建
        $envContent = '';
        if (file_exists($envFile) && filesize($envFile) > 0) {
            $envContent = file_get_contents($envFile);
        } elseif (file_exists($envTemplateFile)) {
            $envContent = file_get_contents($envTemplateFile);
        } else {
            // 创建一个基本的 .env 内容
            $envContent = $this->getDefaultEnvContent();
        }

        // 更新 .env 中的数据库配置
        $patterns = [
            '/^DB_HOST=.*/m' => 'DB_HOST=' . $dbParams['host'],
            '/^DB_PORT=.*/m' => 'DB_PORT=' . $dbParams['port'],
            '/^DB_DATABASE=.*/m' => 'DB_DATABASE=' . $dbParams['database'],
            '/^DB_USERNAME=.*/m' => 'DB_USERNAME=' . $dbParams['username'],
            '/^DB_PASSWORD=.*/m' => 'DB_PASSWORD=' . $dbParams['password'],
            '/^DB_PREFIX=.*/m' => 'DB_PREFIX=' . ($dbParams['prefix'] ?? 'ma_'),
        ];

        foreach ($patterns as $pattern => $replacement) {
            $envContent = preg_replace($pattern, $replacement, $envContent);
        }

        // 更新 .env 中的 Redis 配置
        // 兼容 camelCase（testDatabase JSON POST）和 snake_case（SSE URL 参数）
        $redisPatterns = [
            '/^REDIS_HOST=.*/m' => 'REDIS_HOST=' . ($dbParams['redisHost'] ?? $dbParams['redis_host'] ?? '127.0.0.1'),
            '/^REDIS_PORT=.*/m' => 'REDIS_PORT=' . ($dbParams['redisPort'] ?? $dbParams['redis_port'] ?? '6379'),
            '/^REDIS_PASSWORD=.*/m' => 'REDIS_PASSWORD=' . ($dbParams['redisPassword'] ?? $dbParams['redis_password'] ?? 'null'),
        ];

        foreach ($redisPatterns as $pattern => $replacement) {
            $envContent = preg_replace($pattern, $replacement, $envContent);
        }

        file_put_contents($envFile, $envContent);
    }
    
    /**
     * 获取默认的 .env 内容
     */
    private function getDefaultEnvContent(): string
    {
        return <<<ENV
APP_DEBUG = false

DB_CONNECTION = mysql
DB_HOST = 127.0.0.1
DB_PORT = 3306
DB_DATABASE = md_admin
DB_USERNAME = root
DB_PASSWORD = 
DB_PREFIX = ma_

REDIS_HOST = 127.0.0.1
REDIS_PORT = 6379
REDIS_PASSWORD = 
REDIS_PREFIX = md:

CACHE_DRIVER = redis
SESSION_DRIVER = redis

LOG_CHANNEL = daily
ENV;
    }

    /**
     * 运行所有种子数据
     * 
     * 使用 Trait 处理菜单、字典、配置、管理员数据
     *
     * @param array $dbParams 数据库参数
     * @param array $adminParams 管理员参数
     * @param string $sessionUuid 会话UUID
     * @return \Generator
     */
    private function runAllSeeders(array $dbParams, array $adminParams, string $sessionUuid, bool $enableTenant = false): \Generator
    {
        // 初始化数据库配置
        $this->initDbConfig([
            'host' => $dbParams['host'],
            'port' => $dbParams['port'],
            'database' => $dbParams['database'],
            'username' => $dbParams['username'],
            'password' => $dbParams['password'],
            'prefix' => $dbParams['prefix'] ?? 'ma_',
        ]);
        
        // 菜单 + 前台菜单 42%
        yield Sse::progress("📝 导入菜单模板 & 前台菜单模板", 42, [], $sessionUuid);
        $this->runMenu($enableTenant);

        // 消息分类/定义/模板 43%
        yield Sse::progress("📝 导入消息分类/定义/模板", 43, [], $sessionUuid);
        $this->runMessage();

        // 字典模板 45%
        yield Sse::progress("📝 导入字典模板", 45, [], $sessionUuid);
        $this->runDict($enableTenant);
        
        // 配置模板 48%
        yield Sse::progress("📝 导入配置模板", 48, [], $sessionUuid);
        $this->runConfig($enableTenant);

        // 管理员 50%
        yield Sse::progress("📝 创建管理员账户", 50, [], $sessionUuid);
        $this->createAdmin($adminParams, $enableTenant);

        yield Sse::progress("✅ 所有种子数据执行完成", 52, [], $sessionUuid);
    }

    /**
     * 获取安装协议
     *
     * @return string
     */
    private function getLicenseAgreement(): string
    {
        $templatePath = base_path('resource/license.html');
        return file_get_contents($templatePath);
    }

    /**
     * 将内存大小字符串转换为字节数
     *
     * @param string $size
     *
     * @return int
     */
    private function convertMemoryToBytes(string $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            $size = (int)$size * pow(1024, stripos('bkmgtpezy', $unit[0]));
        }

        return (int)$size;
    }

    /**
     * 复制数据库模板并创建.env文件
     *
     * @param array $dbParams 数据库参数
     *
     * @return void
     * @throws \Exception
     */
    private function copyDatabaseTemplateAndCreateEnv(array $dbParams, bool $enableTenant = false, array $redisParams = []): void
    {
        try {
            $databaseTemplatePath = base_path('resource/data/template/database.php');
            $databaseConfigPath   = base_path('config/database.php');

            if (file_exists($databaseTemplatePath)) {
                if (!copy($databaseTemplatePath, $databaseConfigPath)) {
                    throw new Exception('复制数据库模板文件失败');
                }
                error_log("数据库模板文件已复制到: " . $databaseConfigPath);
            } else {
                throw new Exception('数据库模板文件不存在: ' . $databaseTemplatePath);
            }

            // 2. 创建.env文件
            $envTemplatePath = base_path('.example.env');
            $envFilePath     = base_path('.env');

            if (!file_exists($envTemplatePath)) {
                throw new Exception('.example.env模板文件不存在: ' . $envTemplatePath);
            }

            // 读取模板内容
            $envContent = file_get_contents($envTemplatePath);
            if ($envContent === false) {
                throw new Exception('读取.env模板文件失败');
            }

            // 替换数据库配置参数
            $envContent = $this->replaceEnvDatabaseParams($envContent, $dbParams);

            // 替换 Redis 配置参数
            $envContent = $this->replaceEnvRedisParams($envContent, $redisParams);

            // 设置多租户开关
            $envContent = $this->setEnvTenantToggle($envContent, $enableTenant);

            // 写入.env文件
            if (file_put_contents($envFilePath, $envContent) === false) {
                throw new Exception('创建.env文件失败');
            }

            error_log(".env文件已创建: " . $envFilePath);

        } catch (Exception $e) {
            error_log("配置环境文件失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 替换.env文件中的数据库参数
     *
     * @param string $envContent .env文件内容
     * @param array  $dbParams   数据库参数
     *
     * @return string 替换后的内容
     */
    private function replaceEnvDatabaseParams(string $envContent, array $dbParams): string
    {
        // 定义需要替换的参数映射
        $replacements = [
            'DB_HOST'     => $dbParams['host'] ?? '127.0.0.1',
            'DB_PORT'     => $dbParams['port'] ?? '3306',
            'DB_DATABASE' => $dbParams['database'] ?? 'md_admin',
            'DB_USERNAME' => $dbParams['username'] ?? 'root',
            'DB_PASSWORD' => $dbParams['password'] ?? 'root',
            'DB_PREFIX'   => $dbParams['prefix'] ?? 'ma_',
        ];

        foreach ($replacements as $key => $value) {
            $pattern     = '/^' . preg_quote($key) . '=.*$/m';
            $replacement = $key . '=' . $value;
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n" . $replacement;
            }
        }

        return $envContent;
    }

    /**
     * 替换.env文件中的 Redis 参数
     *
     * @param string $envContent  .env文件内容
     * @param array  $redisParams Redis 参数（host/port/password）
     *
     * @return string 替换后的内容
     */
    private function replaceEnvRedisParams(string $envContent, array $redisParams): string
    {
        $host = $redisParams['host'] ?? '127.0.0.1';
        $port = $redisParams['port'] ?? '6379';
        $password = $redisParams['password'] ?? 'null';

        $replacements = [
            'REDIS_HOST'            => $host,
            'REDIS_PORT'            => $port,
            'REDIS_PASSWORD'        => $password,
            'QUEUE_REDIS_HOST'      => "redis://{$host}:{$port}",
            'QUEUE_REDIS_PORT'      => $port,
            'QUEUE_REDIS_PASSWORD'  => $password,
            'CACHE_CUSTOM_REDIS_HOST'     => $host,
            'CACHE_CUSTOM_REDIS_PORT'     => $port,
            'CACHE_CUSTOM_REDIS_PASSWORD' => $password,
        ];

        foreach ($replacements as $key => $value) {
            $pattern     = '/^' . preg_quote($key) . '=.*$/m';
            $replacement = $key . '=' . $value;
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n" . $replacement;
            }
        }

        return $envContent;
    }

    /**
     * 设置 .env 中的多租户开关
     *
     * @param string $envContent .env文件内容
     * @param bool   $enable     是否启用多租户
     *
     * @return string 更新后的内容
     */
    private function setEnvTenantToggle(string $envContent, bool $enable): string
    {
        $key = 'APP_TENANT_ENABLED';
        $value = $enable ? 'true' : 'false';
        $pattern = '/^' . preg_quote($key) . '=.*$/m';

        if (preg_match($pattern, $envContent)) {
            $envContent = preg_replace($pattern, $key . '=' . $value, $envContent);
        } else {
            $envContent .= "\n" . $key . '=' . $value;
        }

        return $envContent;
    }

    /**
     * 创建安装锁
     *
     * @return void
     */
    private function createInstallLock(): void
    {
        file_put_contents($this->lock_file, date('Y-m-d H:i:s'));
    }

    /**
     * 分割SQL语句
     *
     * @param string $sql
     *
     * @return array
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $currentStatement = '';
        $delimiter = ';';
        $delimiterLength = strlen($delimiter);
        $inString = false;
        $stringChar = '';
        $escaped = false;

        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // 处理 DELIMITER 命令
            if (stripos($trimmedLine, 'DELIMITER') === 0) {
                // 保存之前的语句
                if (!empty(trim($currentStatement))) {
                    $statements[] = trim($currentStatement);
                    $currentStatement = '';
                }
                // 设置新的分隔符
                $parts = preg_split('/\s+/', $trimmedLine, 2);
                if (isset($parts[1])) {
                    $delimiter = trim($parts[1]);
                    $delimiterLength = strlen($delimiter);
                }
                continue;
            }

            $currentStatement .= $line . "\n";

            // 分析字符，处理字符串中的特殊字符
            for ($i = 0; $i < strlen($line); $i++) {
                $char = $line[$i];

                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if (!$inString && ($char === '"' || $char === "'" || $char === '`')) {
                    $inString = true;
                    $stringChar = $char;
                    continue;
                }

                if ($inString && $char === $stringChar) {
                    $inString = false;
                    continue;
                }

                if (!$inString) {
                    // 检查是否到达分隔符
                    if (substr($line, $i, $delimiterLength) === $delimiter) {
                        $statement = substr($currentStatement, 0, -($delimiterLength - strlen($line) + $i));
                        if (!empty(trim($statement))) {
                            $statements[] = trim($statement);
                        }
                        $currentStatement = '';
                        $i += $delimiterLength - 1;
                    }
                }
            }
        }

        // 添加最后一个语句
        if (!empty(trim($currentStatement))) {
            $statements[] = trim($currentStatement);
        }

        return $statements;
    }
}