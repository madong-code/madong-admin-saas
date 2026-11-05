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

namespace app\command\install;

use app\command\BaseCommand;
use core\business\install\InstallService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 框架安装命令
 *
 * 支持 CLI 交互式安装和在线安装共享同一套服务逻辑
 *
 * 使用示例:
 * php webman install:madong
 * php webman install:madong -y --tenant-mode=manual --admin-username=root --admin-password=123456
 * php webman install:madong -y --tenant-mode=none --admin-username=admin --admin-password=123456
 * php webman install:madong --db-host=localhost --db-port=3306 --db-name=madong --db-user=root --db-pass=123456
 *
 * 交互顺序与 Web 向导一致：环境检测 → 数据库 → 是否启用多租户 → 超级管理员账户 → 安装选项。
 * 多租户默认使用系统保留账号 root（平台超级管理员），单租户默认 admin。
 */
#[AsCommand(
    name: 'install:madong',
    description: '安装Madong-Saas框架（交互式或参数化）',
    aliases: ['install:madong'],
    hidden: false
)]
class InstallCommand extends BaseCommand
{

    private ?InstallService $installService = null;

    public function __construct()
    {
        parent::__construct();
        // 不在构造函数中实例化 InstallService，避免命令加载时触发依赖初始化
    }

    /**
     * 获取 InstallService 实例（延迟加载）
     */
    private function getInstallService(): InstallService
    {
        if ($this->installService === null) {
            try {
                $this->installService = new InstallService();
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'Failed to initialize InstallService: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }
        return $this->installService;
    }

    /**
     * 配置命令参数和选项
     */
    protected function configure(): void
    {
        $this
            ->setDescription('安装Madong-SaaS框架（交互式或参数化）')
            ->setHelp('此命令用于安装 Madong-SaaS框架，支持交互式和参数化两种方式')
            
            // 数据库参数
            ->addOption('db-host', null, InputOption::VALUE_OPTIONAL, '数据库主机')
            ->addOption('db-port', null, InputOption::VALUE_OPTIONAL, '数据库端口', '3306')
            ->addOption('db-name', null, InputOption::VALUE_OPTIONAL, '数据库名称')
            ->addOption('db-user', null, InputOption::VALUE_OPTIONAL, '数据库用户名')
            ->addOption('db-pass', null, InputOption::VALUE_OPTIONAL, '数据库密码')
            ->addOption('db-prefix', null, InputOption::VALUE_OPTIONAL, '数据库表前缀', 'ma_')
            
            // 管理员参数（默认值依赖多租户开关，交互时在租户配置之后收集）
            ->addOption('admin-username', null, InputOption::VALUE_OPTIONAL, '管理员用户名（多租户默认 root，单租户默认 admin；显式指定优先）')
            ->addOption('admin-password', null, InputOption::VALUE_OPTIONAL, '管理员密码')
            ->addOption('admin-email', null, InputOption::VALUE_OPTIONAL, '管理员邮箱')
            ->addOption('site-name', null, InputOption::VALUE_OPTIONAL, '站点名称')

            // 租户参数（键名与 Web UI 安装向导一致；交互模式先询问是否启用）
            ->addOption('tenant-mode', null, InputOption::VALUE_OPTIONAL, '租户模式：none=单租户，manual=启用多租户（安装后手动创建租户）；默认 manual')
            ->addOption('enable-tenant', null, InputOption::VALUE_OPTIONAL, '是否启用多租户（1=是，0=否），显式设置时覆盖 --tenant-mode')
            
            // 安装选项
            ->addOption('install-database', null, InputOption::VALUE_OPTIONAL, '是否安装数据库表（1=是，0=否）', '1')
            ->addOption('build-project', null, InputOption::VALUE_OPTIONAL, '是否构建前端项目（1=是，0=否；默认不构建，失败不影响安装）')
            ->addOption('non-interactive', 'y', InputOption::VALUE_NONE, '非交互模式（使用所有默认值）')
            ->addOption('skip-env-check', null, InputOption::VALUE_NONE, '跳过环境检查');
    }

    /**
     * 执行安装命令
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 显示欢迎信息
        $io->title('MDAdmin 框架安装程序');
        $io->text([
            '',
            '欢迎使用 MDAdmin-Saas 框架安装程序！',
            '此程序将引导您完成框架的安装配置。',
            '',
            '<comment>提示: 部分终端会回显密码明文，请注意环境安全；输入完成后会显示确认信息</comment>',
            '',
        ]);

        // 检查是否已安装
        if ($this->getInstallService()->checkInstalled()) {
            $io->error('系统已安装，请勿重复安装！');
            $io->note('如需重新安装，请先删除 ' . base_path('install.lock') . ' 文件');
            return Command::FAILURE;
        }

        // 交互式或非交互式：同时尊重 Symfony 的 --no-interaction / -n，
        // 避免出现「以为在交互、实际所有问题都被跳过」的错觉
        $isInteractive = !$input->getOption('non-interactive') && $input->isInteractive();
        $skipEnvCheck = $input->getOption('skip-env-check');

        if (!$isInteractive) {
            $io->note('非交互模式：不询问任何问题。默认启用多租户并使用平台超管 root、数据库 madong_saas；可用 --enable-tenant=0（账号默认变回 admin）、--admin-username、--db-name 覆盖。');
        }

        // 1. 环境检测
        if (!$skipEnvCheck) {
            $io->section('1. 环境检测');
            $this->handleEnvironmentCheck($io);
        }

        // 2. 安装配置（顺序与 Web 向导一致：数据库 → 多租户 → 账户 → 选项）
        $io->section('2. 安装配置（数据库 → 多租户 → 账户 → 选项）');
        $installParams = $this->collectInstallParams($io, $input, $isInteractive);

        // 3. 确认安装
        $io->section('3. 安装确认');
        if (!$this->confirmInstallation($io, $installParams, $isInteractive)) {
            $io->note('安装已取消');
            return Command::SUCCESS;
        }

        // 4. 执行安装
        $io->section('4. 执行安装');
        $io->text('开始安装...');
        
        try {
            // 调用安装服务（共享在线安装逻辑）
            $generator = $this->getInstallService()->install($installParams);
            
            // 处理 SSE 事件输出：completed 不提前返回，让生成器执行完服务重载
            $completed = false;
            foreach ($generator as $chunk) {
                $result = $this->handleSseEvent($chunk, $io, null);
                if ($result === Command::FAILURE) {
                    return Command::FAILURE;
                }
                if ($result === Command::SUCCESS) {
                    $completed = true;
                }
            }

            if ($completed) {
                $io->note('CLI 安装不会自动重载 Webman 服务，请按部署方式重启后端后访问。');
                $io->note('租户开关已写入 .env 的 APP_TENANT_ENABLED；重启后端后 /adminapi/install/check 的 saas_mode 才会更新。');
                if (($installParams['enable_tenant'] ?? '0') === '1') {
                    $io->note(sprintf(
                        '多租户已启用：请用平台超级管理员 %s 登录平台端；默认租户与租户管理员请在安装后到平台后台创建。',
                        $installParams['admin_username']
                    ));
                }
                if (($installParams['build_project'] ?? '0') !== '1') {
                    $io->note('已跳过前端构建；如需更新前端资源，请在后台「终端 → 重新发布」中单独执行。');
                }
            }

            return $completed ? Command::SUCCESS : Command::FAILURE;
        } catch (\Throwable $e) {
            $this->outputError($io, '安装失败: ' . $e->getMessage(), $e);
            return Command::FAILURE;
        }
    }

    /**
     * 处理环境检查
     */
    private function handleEnvironmentCheck(SymfonyStyle $io): void
    {
        $environment = $this->getInstallService()->checkEnvironment();
        
        // 显示系统信息
        $io->text('系统信息:');
        $io->definitionList(
            ['PHP 版本' => $environment['php_version']],
            ['服务器软件' => $environment['server_software']],
            ['操作系统' => $environment['operating_system']],
            ['内存限制' => $environment['memory_limit']],
            ['最大执行时间' => $environment['max_execution_time'] . '秒'],
        );
        $io->newLine();

        // 检查系统要求
        $allOk = $environment['all_requirements_met'];
        
        if (!$allOk) {
            $io->warning('环境检查未通过，请检查以下项目：');
            
            // 显示失败的检查项
            foreach ($environment['check_items'] as $item) {
                if (!$item['status']) {
                    $io->error($item['message']);
                }
            }
            
            foreach ($environment['directory_check_items'] as $item) {
                if (!$item['status'] && $item['required']) {
                    $io->error($item['message']);
                }
            }
            
            // 确认是否继续
            if (!$this->confirm($io, '环境检查未通过，是否继续安装？', false)) {
                throw new \Exception('环境检查未通过，安装已取消');
            }
        } else {
            $io->success('环境检查通过！');
        }
        
        $io->newLine();
    }

    /**
     * 收集安装参数
     *
     * 顺序与 Web 安装向导一致：数据库 → 是否启用多租户 → 超级管理员账户 → 安装选项。
     * 先决定租户开关，才能给出正确的账户默认值（多租户 root / 单租户 admin）。
     */
    private function collectInstallParams(SymfonyStyle $io, InputInterface $input, bool $isInteractive): array
    {
        $params = [];

        // 1. 数据库配置
        $io->text('<info>数据库配置</info>');
        
        $dbHost = $input->getOption('db-host');
        if (!$dbHost && $isInteractive) {
            $dbHost = $io->ask('请输入数据库主机', '127.0.0.1');
        }
        $params['db_host'] = $dbHost ?? '127.0.0.1';

        $dbPort = $input->getOption('db-port');
        if (!$dbPort && $isInteractive) {
            $dbPort = $io->ask('请输入数据库端口', '3306');
        }
        $params['db_port'] = $dbPort ?? '3306';

        $dbName = $input->getOption('db-name');
        if (!$dbName && $isInteractive) {
            $dbName = $io->ask('请输入数据库名称', 'madong_saas');
        }
        $params['db_database'] = $dbName ?? 'madong_saas';

        $dbUser = $input->getOption('db-user');
        if (!$dbUser && $isInteractive) {
            $dbUser = $io->ask('请输入数据库用户名', 'root');
        }
        $params['db_username'] = $dbUser ?? 'root';

        $dbPass = $input->getOption('db-pass');
        if (!$dbPass && $isInteractive) {
            $io->text('<comment>提示: 终端可能回显密码明文，请确认环境安全；输入完成后会显示确认信息</comment>');
            $dbPass = $this->askPassword($io, '请输入数据库密码: ');
            if ($dbPass !== '') {
                $io->text('✓ 数据库密码已设置');
            }
        }
        $params['db_password'] = $dbPass ?? '';

        $dbPrefix = $input->getOption('db-prefix');
        if (!$dbPrefix && $isInteractive) {
            $dbPrefix = $io->ask('请输入数据库表前缀', 'ma_');
        }
        $params['db_prefix'] = $dbPrefix ?? 'ma_';

        // 2. 多租户配置：先问「是否启用」，账户默认值依赖此开关
        $io->newLine();
        $io->text('<info>多租户配置（先决定是否启用，再设置相关账户）</info>');

        $tenantMode = $input->getOption('tenant-mode');
        if ($tenantMode === null || $tenantMode === '') {
            $tenantMode = $isInteractive
                ? ($this->confirm($io, '是否启用多租户？（平台运营端 + 租户端，默认启用）', true) ? 'manual' : 'none')
                : 'manual';
        }
        if (!in_array($tenantMode, ['none', 'manual'], true)) {
            $io->warning("未知的租户模式 [{$tenantMode}]，已回退为 manual");
            $tenantMode = 'manual';
        }

        $enableTenant = $tenantMode === 'manual' ? '1' : '0';
        $explicitTenant = $input->getOption('enable-tenant');
        if ($explicitTenant !== null && $explicitTenant !== '') {
            $enableTenant = $explicitTenant === '1' ? '1' : '0';
        }
        // tenant_mode 与最终开关保持一致，避免摘要与实际安装结果不符
        $tenantMode = $enableTenant === '1' ? 'manual' : 'none';

        // 后端唯一消费 enable_tenant（'1' 启用），键名与 Web UI SSE 参数一致。
        // 默认 manual，与 .example.env 的 APP_TENANT_ENABLED=true 对齐，避免 CLI 安装后 saas_mode 变为 false。
        $params['tenant_mode']            = $tenantMode;
        $params['enable_tenant']          = $enableTenant;
        // 后端暂未实现安装期自动创建默认租户，安装后请在平台后台创建
        $params['create_default_tenant'] = '0';

        $tenantEnabled = $enableTenant === '1';
        $io->text($tenantEnabled
            ? '已启用多租户：平台超级管理员默认使用系统保留账号 root（仅安装期可创建）；默认租户与租户管理员请在安装后到平台后台创建。'
            : '已选择单租户：超级管理员默认使用 admin。');

        // 3. 超级管理员账户（默认值依赖上一步的租户开关）
        $io->newLine();
        $io->text($tenantEnabled
            ? '<info>平台超级管理员配置（多租户：root）</info>'
            : '<info>超级管理员配置（单租户：admin）</info>');

        // root 是系统保留账号，后台业务校验禁止创建，仅安装器通过 PDO 直连写入；
        // 显式 --admin-username 始终优先，单租户仍保持 admin。
        $defaultAdminUsername = $tenantEnabled ? 'root' : 'admin';

        $adminUsername = $input->getOption('admin-username');
        if (!$adminUsername && $isInteractive) {
            $adminUsername = $io->ask('请输入超级管理员用户名', $defaultAdminUsername);
        }
        $params['admin_username'] = $adminUsername ?? $defaultAdminUsername;

        $adminPassword = $input->getOption('admin-password');
        if (!$adminPassword && $isInteractive) {
            $io->text('<comment>提示: 终端可能回显密码明文，请确认环境安全；输入完成后会显示确认信息</comment>');
            do {
                $adminPassword = $this->askPassword($io, '请输入管理员密码（至少6位）: ');
                if (strlen($adminPassword) < 6) {
                    $io->error('密码长度不能少于6位！');
                }
            } while (strlen($adminPassword) < 6);
            $io->text('✓ 管理员密码已设置');
        }
        $params['admin_password'] = $adminPassword ?? '123456';

        $adminEmail = $input->getOption('admin-email');
        if (!$adminEmail && $isInteractive) {
            $adminEmail = $io->ask('请输入管理员邮箱', 'admin@example.com');
        }
        $params['admin_email'] = $adminEmail ?? 'admin@example.com';

        // 站点名称（与 Web UI 参数保持一致，后端暂不消费）
        $siteName = $input->getOption('site-name');
        if (!$siteName && $isInteractive) {
            $siteName = $io->ask('请输入站点名称', 'Madong管理系统');
        }
        $params['site_name'] = $siteName ?? 'Madong管理系统';

        // 4. 安装选项
        $io->newLine();
        $io->text('<info>安装选项</info>');

        $installDatabase = $input->getOption('install-database');
        if ($installDatabase === null && $isInteractive) {
            $installDatabase = $this->confirm($io, '是否安装数据库表？', true) ? '1' : '0';
        }
        $params['install_database'] = $installDatabase ?? '1';

        $buildProject = $input->getOption('build-project');
        if ($buildProject === null || $buildProject === '') {
            $buildProject = $isInteractive
                ? ($this->confirm($io, '是否构建前端项目？（耗时较长，失败不影响安装）', false) ? '1' : '0')
                : '0';
        }
        $params['build_project'] = $buildProject === '1' ? '1' : '0';

        return $params;
    }

    /**
     * 确认安装信息
     */
    private function confirmInstallation(SymfonyStyle $io, array $params, bool $isInteractive): bool
    {
        // 显示配置摘要
        $io->text('安装配置摘要:');
        $io->definitionList(
            ['数据库主机' => $params['db_host']],
            ['数据库端口' => $params['db_port']],
            ['数据库名称' => $params['db_database']],
            ['数据库用户' => $params['db_username']],
            ['表前缀' => $params['db_prefix']],
            ['租户模式' => ($params['enable_tenant'] ?? '0') === '1'
                ? '多租户（APP_TENANT_ENABLED=true，默认租户请在后台创建）'
                : '单租户（APP_TENANT_ENABLED=false）'],
            ['超级管理员' => $params['admin_username'] . (($params['enable_tenant'] ?? '0') === '1'
                ? '（平台超管 / 系统保留账号）'
                : '（单租户）')],
            ['管理员邮箱' => $params['admin_email']],
            ['安装数据库' => $params['install_database'] == '1' ? '是' : '否'],
            ['构建前端' => $params['build_project'] == '1' ? '是' : '否'],
        );
        $io->newLine();

        // 确认安装
        if ($isInteractive) {
            return $this->confirm($io, '确认开始安装？安装前请确保数据库配置正确！', false);
        }

        return true;
    }
}
