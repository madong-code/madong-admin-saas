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

namespace app\command\metadata;

use app\service\core\metadata\MetadataCollectorService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use support\Container;

#[AsCommand(
    name: 'madong:permission:collect',
    description: 'Collect permissions from controller annotations',
    aliases: ['madong:permission:collect'],
    hidden: false
)]
class CollectCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure()
    {
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', base_path('permissions.json'));
    }

    /**
     * 执行命令
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputPath = $input->getOption('output');

        $output->writeln('<info>开始收集权限...</info>');

        try {
            /** @var MetadataCollectorService $collector */
            $collector = Container::make(MetadataCollectorService::class);
            $permissions = $collector->collect();

            // 导出为 JSON 文件
            $success = $collector->exportToJson($outputPath);

            if ($success) {
                $output->writeln(sprintf('<info>权限收集完成，共收集到 %d 个权限</info>', count($permissions)));
                $output->writeln(sprintf('<info>权限列表已导出到：%s</info>', $outputPath));
                return Command::SUCCESS;
            } else {
                $output->writeln('<error>权限收集失败：无法写入文件</error>');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $output->writeln('<error>权限收集失败：' . $e->getMessage() . '</error>');
            $output->writeln('<error>错误堆栈：' . $e->getTraceAsString() . '</error>');
            return Command::FAILURE;
        }
    }
}
