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
 * 生成控制器命令
 * 使用方法：
 * - 生成控制器：php webman make:controller TestController
 * - 生成带完整路径的控制器：php webman make:controller adminapi/controller/system/TestController
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong-make:controller',
    description: 'Generate a new controller class',
    aliases: ['madong-make:controller'],
    hidden: false
)]
class ControllerCommand extends BaseCommand
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Controller name');
    }

    /**
     * 执行命令
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Controller Generator');

        $name = $input->getArgument('name');
        $io->info("Generating controller: {$name}");

        // 处理路径和命名空间
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', $name);
        $className = ucfirst(array_pop($parts));

        // 构建文件路径和命名空间
        if (empty($parts)) {
            // 如果没有提供路径，默认创建到app/controller目录
            $file = app_path() . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . "{$className}.php";
            $namespace = 'app\controller';
        } else {
            // 使用用户提供的完整路径
            $relativePath = implode(DIRECTORY_SEPARATOR, $parts);
            $file = app_path() . DIRECTORY_SEPARATOR . $relativePath . DIRECTORY_SEPARATOR . "{$className}.php";
            $namespace = 'app\\' . str_replace('/', '\\', $relativePath);
        }

        if (is_file($file)) {
            if (!$this->confirm($io, "$file already exists. Do you want to override it?")) {
                $io->warning('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->createController($className, $namespace, $file);
        $io->success("Controller {$className} generated successfully!");

        return Command::SUCCESS;
    }

    /**
     * 创建控制器文件
     */
    protected function createController(string $className, string $namespace, string $file): void
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        // 读取模板文件
        $template = file_get_contents(__DIR__ . '/stubs/controller.stub');

        // 替换占位符
        $content = str_replace(
            ['DummyNamespace', 'DummyClass'],
            [$namespace, $className],
            $template
        );

        file_put_contents($file, $content);
    }
}