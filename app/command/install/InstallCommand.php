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
 * php webman install:madong --username=admin --password=123456
 * php webman install:madong --db-host=localhost --db-port=3306 --db-name=madong --db-user=root --db-pass=123456 --admin-username=admin --admin-password=123456
 */
#[AsCommand(
    name: 'install:madong',
    description: '安装 MDAdmin 框架（交互式或参数化）',
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
            ->setDescription('安装 MDAdmin 框架')
            ->setHelp('此命令用于安装 MDAdmin 框架，支持交互式和参数化两种方式')
            
            // 数据库参数
            ->addOption('db-host', null, InputOption::VALUE_OPTIONAL, '数据库主机')
            ->addOption('db-port', null, InputOption::VALUE_OPTIONAL, '数据库端口', '3306')
            ->addOption('db-name', null, InputOption::VALUE_OPTIONAL, '数据库名称')
            ->addOption('db-user', null, InputOption::VALUE_OPTIONAL, '数据库用户名')
            ->addOption('db-pass', null, InputOption::VALUE_OPTIONAL, '数据库密码')
            ->addOption('db-prefix', null, InputOption::VALUE_OPTIONAL, '数据库表前缀', 'ma_')
            
            // 管理员参数
            ->addOption('admin-username', null, InputOption::VALUE_OPTIONAL, '管理员用户名')
            ->addOption('admin-password', null, InputOption::VALUE_OPTIONAL, '管理员密码')
            ->addOption('admin-email', null, InputOption::VALUE_OPTIONAL, '管理员邮箱')
            
            // 安装选项
            ->addOption('install-database', null, InputOption::VALUE_OPTIONAL, '是否安装数据库表（1=是，0=否）', '1')
            ->addOption('build-project', null, InputOption::VALUE_OPTIONAL, '是否构建前端项目（1=是，0=否）', '1')
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
            '<comment>提示: 密码输入时不会显示字符(出于安全考虑)，输入完成后会显示确认信息</comment>',
            '',
        ]);

        // 检查是否已安装
        if ($this->getInstallService()->checkInstalled()) {
            $io->error('系统已安装，请勿重复安装！');
            $io->note('如需重新安装，请先删除 ' . base_path('install.lock') . ' 文件');
            return Command::FAILURE;
        }

        // 交互式或非交互式
        $isInteractive = !$input->getOption('non-interactive');
        $skipEnvCheck = $input->getOption('skip-env-check');

        // 1. 环境检测
        if (!$skipEnvCheck) {
            $io->section('1. 环境检测');
            $this->handleEnvironmentCheck($io);
        }

        // 2. 数据库配置
        $io->section('2. 数据库配置');
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
            
            // 处理 SSE 事件输出
            foreach ($generator as $chunk) {
                $result = $this->handleSseEvent($chunk, $io, null);
                if ($result !== null) {
                    return $result;
                }
            }
            
            return Command::SUCCESS;
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
     */
    private function collectInstallParams(SymfonyStyle $io, InputInterface $input, bool $isInteractive): array
    {
        $params = [];

        // 数据库配置
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
            $dbName = $io->ask('请输入数据库名称', 'md_admin');
        }
        $params['db_database'] = $dbName ?? 'md_admin';

        $dbUser = $input->getOption('db-user');
        if (!$dbUser && $isInteractive) {
            $dbUser = $io->ask('请输入数据库用户名', 'root');
        }
        $params['db_username'] = $dbUser ?? 'root';

        $dbPass = $input->getOption('db-pass');
        if (!$dbPass && $isInteractive) {
            $io->text('提示: 密码输入时显示掩码字符 *');
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

        // 管理员配置
        $io->newLine();
        $io->text('<info>管理员配置</info>');

        $adminUsername = $input->getOption('admin-username');
        if (!$adminUsername && $isInteractive) {
            $adminUsername = $io->ask('请输入管理员用户名', 'admin');
        }
        $params['admin_username'] = $adminUsername ?? 'admin';

        $adminPassword = $input->getOption('admin-password');
        if (!$adminPassword && $isInteractive) {
            $io->text('提示: 密码输入时显示掩码字符 *');
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

        // 安装选项
        $io->newLine();
        $io->text('<info>安装选项</info>');

        $installDatabase = $input->getOption('install-database');
        if ($installDatabase === null && $isInteractive) {
            $installDatabase = $this->confirm($io, '是否安装数据库表？', true) ? '1' : '0';
        }
        $params['install_database'] = $installDatabase ?? '1';

        $buildProject = $input->getOption('build-project');
        if ($buildProject === null && $isInteractive) {
            $buildProject = $this->confirm($io, '是否构建前端项目？', false) ? '1' : '0';
        }
        $params['build_project'] = $buildProject ?? '0';

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
            ['管理员用户名' => $params['admin_username']],
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
