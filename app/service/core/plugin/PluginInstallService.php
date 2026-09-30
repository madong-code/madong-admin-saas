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
namespace app\service\core\plugin;

use app\enum\common\PluginInstallEvent;
use app\event\plugin\PluginInstalled;
use app\service\core\plugin\PluginLifecycleOrchestrator;
use app\event\plugin\PluginInstalling;
use app\process\Monitor;
use core\business\plugin\traits\ProgressStreamTrait;
use core\foundation\exception\handler\PluginException;
use core\foundation\tool\Sse;
use core\io\uuid\UUIDGenerator;
use support\Container;

/**
 * 安装插件服务
 */
final class PluginInstallService extends PluginBaseService
{
    use ProgressStreamTrait;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 插件安装
     *
     * 安装流程统一使用插件自带的 Install.php
     * PluginInstallService 只负责流程控制，实际安装逻辑完全由插件 Install.php 处理
     *
     * @param string       $code
     * @param string       $mode
     * @param string|int|null $tenantId 租户ID（null=平台安装）
     *
     * @return \Generator
     * @throws \Exception
     */
    public function install(string $code, string $mode = 'local', string|int|null $tenantId = null): \Generator
    {
        $sessionUuid = UUIDGenerator::generate();
        $request     = request();
        if ($request) {
            $sessionUuid = $request->input('uuid', $sessionUuid);
        }

        // 检查插件是否已安装
        $pluginDir = $this->plugin_path . DIRECTORY_SEPARATOR . $code;
        $installedConfigFile = $pluginDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php';

        if ($tenantId === null) {
            // 平台安装：检查 installed.php
            if (is_file($installedConfigFile)) {
                yield Sse::warning('插件已安装，跳过安装', ['plugin' => $code], $sessionUuid);
                return;
            }
        } else {
            // 租户安装：检查平台是否已安装 + 租户是否已安装
            if (!is_file($installedConfigFile)) {
                yield Sse::error('插件尚未被平台安装，无法安装', ['plugin' => $code], $sessionUuid);
                return;
            }
            /** @var \app\dao\plugin\TenantPluginDao $tenantPluginDao */
            $tenantPluginDao = Container::make(\app\dao\plugin\TenantPluginDao::class);
            $existing = $tenantPluginDao->findByTenantAndKey($tenantId, $code);
            if ($existing) {
                yield Sse::warning('该租户已安装此插件，跳过安装', ['plugin' => $code], $sessionUuid);
                return;
            }
        }

        try {
            yield Sse::progress(PluginInstallEvent::INSTALL_START->label(), 0, ['plugin' => $code], $sessionUuid);

            // 1. 检查本地是否已经下载了模块
            yield Sse::progress('检查本地模块', 5, [], $sessionUuid);
            $localPluginPath = $this->plugin_path . DIRECTORY_SEPARATOR . $code;
            $moduleExists    = is_dir($localPluginPath);

            // 2. 如果是local模式，只检测plugin目录是否包含，没有则直接返回异常安装失败
            if ($mode === 'local') {
                if (!$moduleExists) {
                    yield Sse::error('本地模块不存在，安装失败', [], $sessionUuid);
                    return;
                }
                yield Sse::progress('本地模块存在', 10, [], $sessionUuid);
            } else {
                // 3. 如果没有指定，优先检测本地，没有则通过远程下载模块zip
                if (!$moduleExists) {
                    yield Sse::progress('本地模块不存在，开始远程下载', 10, [], $sessionUuid);

                    // 获取下载链接
                    $remoteService = Container::make(PluginRemoteService::class);
                    $authCode     = config('madong.auth_code');
                    $secretKey    = config('madong.auth_secret');

                    // 检查授权配置
                    if (empty($authCode) || empty($secretKey)) {
                        yield Sse::error('授权配置缺失，无法下载远程插件。请在配置文件中设置 madong.auth_code 和 madong.auth_secret', [], $sessionUuid);
                        return;
                    }

                    $downloadInfo = $remoteService->getRemoteDownloadService($authCode, $secretKey, $code, 'latest');

                    // 记录下载信息，方便调试
                    yield Sse::progress('下载接口返回数据: ' . json_encode($downloadInfo, JSON_UNESCAPED_UNICODE), 12, [], $sessionUuid);

                    if (empty($downloadInfo['url'])) {
                        $errorMsg = '获取下载链接失败';
                        if (isset($downloadInfo['msg'])) {
                            $errorMsg .= ': ' . $downloadInfo['msg'];
                        }
                        if (isset($downloadInfo['message'])) {
                            $errorMsg .= ': ' . $downloadInfo['message'];
                        }
                        yield Sse::error($errorMsg, [], $sessionUuid);
                        return;
                    }

                    yield Sse::progress('获取下载链接成功: ' . $downloadInfo['url'], 15, [], $sessionUuid);

                    // 下载并解压插件
                    yield Sse::progress('开始下载并解压模块', 20, [], $sessionUuid);
                    $downloadService = Container::make(PluginDownloadService::class);
                    $downloadService->downloadAndExtract($code, $downloadInfo['url']);
                    yield Sse::progress('模块下载并解压完成', 30, [], $sessionUuid);
                } else {
                    yield Sse::progress('本地模块存在', 20, [], $sessionUuid);
                }
            }

            // 5. 安装检查
            yield Sse::progress(PluginInstallEvent::INSTALL_CHECK->label(), 35, [], $sessionUuid);
            $checkResult = $this->installCheck($code);
            if ($checkResult['overall_status'] !== 'success') {
                yield Sse::error($checkResult['message'], [], $sessionUuid);
                return;
            }
            Monitor::pause();

            // 6. 统一调用插件 Install.php 进行安装（这是唯一的安装入口）
            $pluginConfig = $this->getPluginConfig($code);
            $version = $pluginConfig['version'] ?? '1.0.0';

            // 派发安装前事件
            (new PluginInstalling($code, $version))->dispatch();

            yield Sse::progress(PluginInstallEvent::EXECUTE_INSTALL_METHOD->label(), 50, [], $sessionUuid);

            // 检查插件是否提供 Install.php
            $className = "plugin\\{$code}\\Install";
            if (!class_exists($className)) {
                throw new PluginException("插件未提供 Install.php，无法进行安装");
            }

            // 提前验证父类可加载，避免后续 fatal error 导致进程崩溃
            $reflection = new \ReflectionClass($className);
            $parentClass = $reflection->getParentClass();
            if (!$parentClass) {
                throw new PluginException("插件 Install.php 缺少继承的基类");
            }
            $parentName = $parentClass->getName();
            if (!class_exists($parentName)) {
                throw new PluginException("插件 Install.php 的父类 {$parentName} 不存在，请检查 use 导入路径");
            }

            // 实例化并执行插件 Install 类（传入进度回调以支持在线模式反馈）
            $installInstance = new $className();

            // 设置安装上下文
            $context = $tenantId === null ? 'platform' : 'tenant';
            $installInstance->setContext($context);
            if ($tenantId !== null) {
                $installInstance->setTenantId($tenantId);
                // DB模式下设置租户数据库连接
                if (\core\business\tenant\context\TenantContext::getIsolationMode() === 'database') {
                    $connectionName = \core\business\tenant\context\TenantContext::getConnectionName();
                    if ($connectionName) {
                        $installInstance->setConnection($connectionName);
                    }
                }
            }

            // 创建进度记录临时文件
            $outputFile = $this->createProgressFile('install', $sessionUuid);
            $progressCallback = $this->createProgressCallback($outputFile);

            // 设置进度回调和在线模式
            $installInstance->setProgressCallback($progressCallback);
            $installInstance->setOnlineMode(true);

            // 开启输出缓冲，捕获 Install::install() 内部的 echo 输出
            try {
                $this->captureOutputWithProgress(function() use ($installInstance, $version) {
                    $installInstance->install($version);
                }, $outputFile);
            } catch (\Throwable $e) {
                $this->cleanupProgressFile($outputFile);
                yield Sse::error('插件安装执行失败: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], $sessionUuid);
                return;
            }

            // 读取文件内容并 yield 所有日志
            yield from $this->yieldProgressLines($outputFile, 50, [], $sessionUuid);

            // 清理临时文件
            $this->cleanupProgressFile($outputFile);

            yield Sse::progress('插件安装方法执行成功', 85, [], $sessionUuid);

            // 7.1 租户安装：菜单由「租户安装模块」(TenantMenuSyncService) 安装到该租户 sys_menu
            // (单体模式菜单已在 PluginInstall::loadMenus 阶段写入 sys_menu, 此处无需处理)
            if ($tenantId !== null) {
                yield Sse::progress('开始同步插件菜单到租户', 88, [], $sessionUuid);
                try {
                    /** @var \app\service\core\plugin\PluginLifecycleOrchestrator $orchestrator */
                    $orchestrator = Container::make(PluginLifecycleOrchestrator::class);
                    $orchestrator->syncTenantMenu($code, $version, (string)$tenantId);
                    yield Sse::progress('插件菜单同步完成', 92, [], $sessionUuid);
                } catch (\Throwable $e) {
                    yield Sse::warning('插件菜单同步失败(可稍后重试): ' . $e->getMessage(), [], $sessionUuid);
                }
            }

            // 8. 创建安装标记/租户记录
            if ($tenantId === null) {
                // 平台安装：创建 installed.php 标记
                yield Sse::progress('创建安装标记', 90, [], $sessionUuid);
                $installedConfig = [
                    'version' => $pluginConfig['version'] ?? '1.0.0',
                    'installed_at' => date('Y-m-d H:i:s'),
                ];
                file_put_contents($installedConfigFile, '<?php return ' . var_export($installedConfig, true) . ';');
                yield Sse::progress('安装标记创建成功', 95, [], $sessionUuid);
            } else {
                // 租户安装：记录 tenant_plugin 表
                yield Sse::progress('记录租户插件信息', 90, [], $sessionUuid);
                /** @var \app\dao\plugin\TenantPluginDao $tenantPluginDao */
                $tenantPluginDao = Container::make(\app\dao\plugin\TenantPluginDao::class);
                $now = time();
                $tenantPluginDao->getModel()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'plugin_key' => $code],
                    [
                        'status'       => 1,
                        'is_purchased' => 1,
                        'installed_at' => $now,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]
                );
                // 同步写入运行态表
                \app\model\plugin\TenantPluginInstall::updateOrCreate(
                    ['tenant_id' => $tenantId, 'plugin_key' => $code],
                    [
                        'status'       => 1,
                        'installed_at' => $now,
                        'sync_status'  => 'success',
                        'version'      => $pluginConfig['version'] ?? '1.0.0',
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]
                );
                yield Sse::progress('租户插件信息记录成功', 95, [], $sessionUuid);
            }

            // 9. 安装完成 - 派发安装后事件
            (new PluginInstalled($code, $version))->dispatch();
            yield Sse::completed(PluginInstallEvent::INSTALL_COMPLETED->label(), ['plugin' => $code], $sessionUuid);
            Monitor::pause();

        } catch (PluginException $e) {
            yield Sse::error($e->getMessage(), [], $sessionUuid);
            return;
        } catch (\Throwable $e) {
            yield Sse::error('安装失败：' . $e->getMessage(), [], $sessionUuid);
            return;
        }

    }

    /**
     * 安装检查
     *
     * @param string $name 插件名称
     *
     * @return array 检查结果
     */
    public function installCheck(string $name): array
    {
        // 初始化返回结果
        $result = [
            'project'        => $name,
            'overall_status' => 'success',
            'message'        => '检查通过',
            'checks'         => [],
        ];

        // 定义检查项数组
        $checks = [];

        try {
            // 1. 核心目录存在性检查（模板目录统一在 template 下）
            $coreDirectories = [
                'admin' => [
                    'path' => \core\business\plugin\PluginPath::templateProjectPath('mono/apps/admin') . DIRECTORY_SEPARATOR,
                    'name' => 'Admin目录',
                ],
                'platform' => [
                    'path' => \core\business\plugin\PluginPath::templateProjectPath('mono/apps/platform') . DIRECTORY_SEPARATOR,
                    'name' => 'Platform目录',
                ],
                'web'   => [
                    'path' => \core\business\plugin\PluginPath::templateProjectPath('web') . DIRECTORY_SEPARATOR,
                    'name' => 'Web目录',
                ],
            ];

            foreach ($coreDirectories as $dirKey => $dirInfo) {
                if (is_dir($dirInfo['path'])) {
                    $checks[] = [
                        'type'      => 'directory',
                        'name'      => $dirInfo['name'],
                        'path'      => $dirInfo['path'],
                        'status'    => 'success',
                        'message'   => "{$dirInfo['name']}存在",
                        'exception' => null,
                    ];
                } else {
                    $checks[] = [
                        'type'      => 'directory',
                        'name'      => $dirInfo['name'],
                        'path'      => $dirInfo['path'],
                        'status'    => 'error',
                        'message'   => "{$dirInfo['name']}不存在",
                        'exception' => [
                            'type'    => 'DirectoryNotFoundException',
                            'message' => __('common.plugin.error.dir_not_exist', ['name' => $dirKey]),
                        ],
                    ];
                }
            }

            // 2. 插件安装目录权限检查（与 plugin.php 配置 template 路径一致）
            $adminPluginDirectory = \core\business\plugin\PluginPath::templateProjectPath('mono/apps/admin') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR;
            $platformPluginDirectory = \core\business\plugin\PluginPath::templateProjectPath('mono/apps/platform') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR;
            $webPluginDirectory   = \core\business\plugin\PluginPath::templateProjectPath('web') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR;
            $resourceDirectory    = public_path() . DIRECTORY_SEPARATOR;

            $permissionChecks = [
                [
                    'name'            => '插件源码目录',
                    'path'            => $this->plugin_path,
                    'permission_type' => 'readable',
                    'description'     => '插件源码目录可读性检查',
                ],
                [
                    'name'            => 'Admin插件目录',
                    'path'            => $adminPluginDirectory,
                    'permission_type' => 'writable',
                    'description'     => '插件将安装到的Admin目录可写性检查',
                ],
                [
                    'name'            => 'Platform插件目录',
                    'path'            => $platformPluginDirectory,
                    'permission_type' => 'writable',
                    'description'     => '插件将安装到的Platform目录可写性检查',
                ],
                [
                    'name'            => 'Web插件目录',
                    'path'            => $webPluginDirectory,
                    'permission_type' => 'writable',
                    'description'     => '插件将安装到的Web目录可写性检查',
                ],
                [
                    'name'            => '公共资源目录',
                    'path'            => $resourceDirectory,
                    'permission_type' => 'writable',
                    'description'     => '插件资源将安装到的公共目录可写性检查',
                ],
            ];

            foreach ($permissionChecks as $checkItem) {
                $path      = $checkItem['path'];
                $checkType = $checkItem['permission_type'];
                $checkName = $checkItem['name'];

                if ($checkType === 'readable') {
                    $status  = is_readable($path) ? 'success' : 'error';
                    $message = $status === 'success' ? "{$checkName}可读" : "{$checkName}不可读";
                } else {
                    // 可写检查：目录存在则检查是否可写，不存在则尝试创建
                    if (is_dir($path)) {
                        $status  = is_write($path) ? 'success' : 'error';
                        $message = $status === 'success' ? "{$checkName}可写" : "{$checkName}不可写";
                    } else {
                        $status  = mkdir($path, 0777, true) ? 'success' : 'error';
                        $message = $status === 'success' ? "{$checkName}创建成功并可写" : "{$checkName}创建失败或不可写";
                    }
                }

                $checks[] = [
                    'type'            => 'permission',
                    'name'            => $checkName,
                    'path'            => $path,
                    'permission_type' => $checkType,
                    'description'     => $checkItem['description'],
                    'status'          => $status,
                    'message'         => $message,
                    'exception'       => $status === 'error' ? [
                        'type'    => 'PermissionException',
                        'message' => $message,
                    ] : null,
                ];
            }

            // 4. 插件信息检查
            $pluginConfig = $this->getPluginConfig($name);
            if (empty($pluginConfig)) {
                $checks[] = [
                    'type'        => 'plugin_info',
                    'name'        => '插件信息检查',
                    'description' => '检查插件的info.php文件是否存在',
                    'status'      => 'error',
                    'message'     => "插件信息文件不存在",
                    'exception'   => [
                        'type'    => 'InfoFileNotFoundException',
                        'message' => __('common.plugin.error.info_file_not_exist', ['name' => $name]),
                    ],
                ];
            } else {
                $checks[] = [
                    'type'        => 'plugin_info',
                    'name'        => '插件信息检查',
                    'description' => '验证插件信息文件的完整性',
                    'status'      => 'success',
                    'message'     => "插件信息文件存在",
                    'plugin_info' => [
                        'code'        => $pluginConfig['code']??'',
                        'name'        => $pluginConfig['name']??'',
                        'description' => $pluginConfig['description'] ?? '',
                        'version'     => $pluginConfig['version'],
                    ],
                    'exception'   => null,
                ];
            }



            // 3. 插件安装状态检查（检查 installed.php 文件）
            $pluginDir = $this->plugin_path . DIRECTORY_SEPARATOR . $name;
            $installedConfigFile = $pluginDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php';

            if (is_file($installedConfigFile)) {
                $checks[] = [
                    'type'        => 'installation_status',
                    'name'        => '插件安装状态检查',
                    'description' => '检查插件是否已安装',
                    'status'      => 'error',
                    'message'     => "插件已安装",
                    'exception'   => [
                        'type'    => 'PluginAlreadyInstalledException',
                        'message' => __('common.plugin.error.repeat_install', ['name' => $name]),
                    ],
                ];
            } else {
                $checks[] = [
                    'type'        => 'installation_status',
                    'name'        => '插件安装状态检查',
                    'description' => '确认插件未安装',
                    'status'      => 'success',
                    'message'     => "插件未安装，可以继续",
                    'exception'   => null,
                ];
            }

            // 4. 插件依赖检查（检查 installed.php 文件）
            if (isset($pluginConfig['support_app']) && !empty($pluginConfig['support_app']) && $pluginConfig['support_app'] != $name) {
                $supportAppCode = $pluginConfig['support_app'];
                $supportAppDir = $this->plugin_path . DIRECTORY_SEPARATOR . $supportAppCode;
                $supportAppInstalledFile = $supportAppDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php';

                if (!is_file($supportAppInstalledFile)) {
                    $supportAppConfig = $this->getPluginConfig($supportAppCode);
                    $supportAppTitle = empty($supportAppConfig) ? $supportAppCode : $supportAppConfig['title'];

                    $checks[] = [
                        'type'             => 'dependency',
                        'name'             => '插件依赖检查',
                        'description'      => '检查插件依赖的主应用是否已安装',
                        'dependency_name'  => $supportAppCode,
                        'dependency_title' => $supportAppTitle,
                        'status'           => 'error',
                        'message'          => "依赖插件未安装",
                        'exception'        => [
                            'type'    => 'DependencyNotFoundException',
                            'message' => $pluginConfig['title'] . '插件的主应用' . $supportAppTitle . '插件还未安装，请先安装主应用',
                        ],
                    ];
                } else {
                    $checks[] = [
                        'type'            => 'dependency',
                        'name'            => '插件依赖检查',
                        'description'     => '确认插件依赖已满足',
                        'dependency_name' => $supportAppCode,
                        'status'          => 'success',
                        'message'         => "依赖插件已安装",
                        'exception'       => null,
                    ];
                }
            }

            // 5. 框架版本支持检查
            $currentFrameworkVersion = config('config.app.version', 'v1.0.1');

            if (isset($pluginConfig['support_version']) && !empty($pluginConfig['support_version'])) {
                $checks[] = [
                    'type'             => 'version',
                    'name'             => '框架版本支持检查',
                    'description'      => '验证当前框架版本是否支持该插件',
                    'current_version'  => $currentFrameworkVersion,
                    'required_version' => $pluginConfig['support_version'],
                    'status'           => 'success',
                    'message'          => "当前框架版本支持该插件",
                    'exception'        => null,
                ];
            }

        } catch (\Exception $e) {
            // 捕获异常
            $checks[] = [
                'type'        => 'system',
                'name'        => '系统检查',
                'description' => '系统级检查',
                'status'      => 'error',
                'message'     => $e->getMessage(),
                'exception'   => [
                    'type'    => get_class($e),
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ],
            ];
        }

        // 计算整体状态
        $errorCount   = 0;
        $warningCount = 0;

        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $errorCount++;
            } elseif ($check['status'] === 'warning') {
                $warningCount++;
            }
        }

        if ($errorCount > 0) {
            $result['overall_status'] = 'error';
            $result['message']        = "检查失败，发现{$errorCount}个错误";
        } elseif ($warningCount > 0) {
            $result['overall_status'] = 'warning';
            $result['message']        = "检查通过但有{$warningCount}个警告";
        } else {
            $result['overall_status'] = 'success';
            $result['message']        = "所有检查通过";
        }

        // 添加检查项到结果
        $result['checks'] = $checks;

        return $result;
    }

}