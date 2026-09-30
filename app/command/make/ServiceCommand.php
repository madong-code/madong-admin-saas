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

namespace app\command\make;

use app\command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 生成服务层命令
 * 使用方法：
 * - 生成服务：php webman make:service TestService
 * - 生成带完整路径的服务：php webman make:service admin/system/TestService
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong-make:service',
    description: 'Generate a new service class',
    aliases: ['madong-make:service'],
    hidden: false
)]
class ServiceCommand extends BaseCommand
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Service name');
    }

    /**
     * 执行命令
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Service Generator');

        $name = $input->getArgument('name');
        $io->info("Generating service: {$name}");

        // 处理路径和命名空间
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', $name);
        $className = ucfirst(array_pop($parts));

        // 构建文件路径和命名空间
        if (empty($parts)) {
            // 如果没有提供路径，默认创建到app/service目录
            $file = app_path() . DIRECTORY_SEPARATOR . 'service' . DIRECTORY_SEPARATOR . "{$className}.php";
            $namespace = 'app\service';
        } else {
            // 使用用户提供的完整路径
            $relativePath = implode(DIRECTORY_SEPARATOR, $parts);
            $file = app_path() . DIRECTORY_SEPARATOR . 'service' . DIRECTORY_SEPARATOR . $relativePath . DIRECTORY_SEPARATOR . "{$className}.php";
            $namespace = 'app\service\\' . str_replace('/', '\\', $relativePath);
        }

        if (is_file($file)) {
            if (!$this->confirm($io, "$file already exists. Do you want to override it?")) {
                $io->warning('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // 提取Dao名称
        $daoName = str_replace('Service', 'Dao', $className);
        if ($daoName === $className) {
            $daoName .= 'Dao';
        }

        $this->createService($className, $namespace, $daoName, $file);
        $io->success("Service {$className} generated successfully!");

        return Command::SUCCESS;
    }

    /**
     * 创建服务文件
     */
    protected function createService(string $className, string $namespace, string $daoName, string $file): void
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        // 读取模板文件
        $template = file_get_contents(__DIR__ . '/stubs/service.stub');

        // 替换占位符
        $content = str_replace(
            ['DummyNamespace', 'DummyClass', 'DummyDao'],
            [$namespace, $className, $daoName],
            $template
        );

        file_put_contents($file, $content);
    }
}