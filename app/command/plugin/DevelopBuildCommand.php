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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 打包插件命令
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong-plugin:develop:build',
    description: 'Build plugin',
    aliases: ['madong-plugin:dev:build'],
    hidden: false
)]
class DevelopBuildCommand extends BaseCommand
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'Plugin key (kebab-case format, e.g. test-demo)')
            ->addOption('target', 't', InputOption::VALUE_OPTIONAL, 'Target directory path', null);
    }

    /**
     * 执行命令
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Build Plugin');

        // 获取参数
        $pluginKey = $input->getArgument('key');
        $targetDirectory = $input->getOption('target');

        $io->info(sprintf("Building plugin: %s", $pluginKey));
        if ($targetDirectory) {
            $io->info(sprintf("Target directory: %s", $targetDirectory));
        } else {
            $io->info("Target directory: Default (runtime/plugins)");
        }

        try {
            // 通过容器获取插件开发服务实例
            $pluginDevelopService = Container::get(PluginDevelopService::class);

            // 构建插件
            $result = $pluginDevelopService->build($pluginKey, $targetDirectory);

            if ($result['code'] === 200) {
                $io->success("Plugin built successfully!");
                $io->text(sprintf("ZIP file path: %s", $result['data']['zip_file_path']));
                return parent::SUCCESS;
            } else {
                return $this->outputError($io, sprintf("Build failed: %s", $result['message']));
            }
        } catch (\Exception $e) {
            return $this->outputError($io, sprintf("Build failed: %s", $e->getMessage()), $e);
        }
    }
}
