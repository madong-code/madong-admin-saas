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

namespace app\command\plugin;

use app\command\BaseCommand;
use app\service\core\plugin\PluginDevelopService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 创建插件模板命令
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong-plugin:develop:create',
    description: 'Create plugin template',
    aliases: ['madong-plugin:dev:create'],
    hidden: false
)]
class DevelopCreateCommand extends BaseCommand
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Plugin name (snake_case format, e.g. test_demo)')
            ->addArgument('title', InputArgument::REQUIRED, 'Plugin title')
            ->addArgument('description', InputArgument::OPTIONAL, 'Plugin description', '');
    }

    /**
     * 执行命令
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create Plugin Template');

        // 获取参数
        $pluginName = $input->getArgument('name');
        $pluginTitle = $input->getArgument('title');
        $pluginDescription = $input->getArgument('description');

        $io->info(sprintf("Creating plugin template: %s", $pluginName));
        $io->info(sprintf("Plugin title: %s", $pluginTitle));
        $io->info(sprintf("Plugin description: %s", $pluginDescription));

        try {
            // 通过容器获取插件开发服务实例
            $pluginDevelopService = Container::get(PluginDevelopService::class);

            // 生成插件模板
            $result = $pluginDevelopService->generatePluginTemplate($pluginName, $pluginTitle, $pluginDescription);

            if ($result['code'] === 200) {
                $io->success("Plugin template created successfully!");
                $io->text(sprintf("Frontend path: %s", $result['data']['frontend_path']));
                $io->text(sprintf("Backend path: %s", $result['data']['backend_path']));
                return parent::SUCCESS;
            } else {
                return $this->outputError($io, sprintf("Creation failed: %s", $result['message']));
            }
        } catch (\Exception $e) {
            return $this->outputError($io, sprintf("Creation failed: %s", $e->getMessage()), $e);
        }
    }
}
