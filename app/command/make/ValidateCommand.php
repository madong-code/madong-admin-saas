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
 * 生成验证层命令
 * 使用方法：
 * - 生成验证器：php webman make:validate TestValidate
 * - 生成带完整路径的验证器：php webman make:validate adminapi/validate/system/TestValidate
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong-make:validate',
    description: 'Generate a new validate class',
    aliases: ['madong-make:validate'],
    hidden: false
)]
class ValidateCommand extends BaseCommand
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Validate name');
    }

    /**
     * 执行命令
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Validate Generator');

        $name = $input->getArgument('name');
        $io->info("Generating validate: {$name}");

        // 处理路径和命名空间
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', $name);
        $className = ucfirst(array_pop($parts));

        // 构建文件路径和命名空间
        if (empty($parts)) {
            // 如果没有提供路径，默认创建到app/validate目录
            $file = app_path() . DIRECTORY_SEPARATOR . 'validate' . DIRECTORY_SEPARATOR . "{$className}.php";
            $namespace = 'app\validate';
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

        $this->createValidate($className, $namespace, $file);
        $io->success("Validate {$className} generated successfully!");

        return Command::SUCCESS;
    }

    /**
     * 创建验证器文件
     */
    protected function createValidate(string $className, string $namespace, string $file): void
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        // 读取模板文件
        $template = file_get_contents(__DIR__ . '/stubs/validate.stub');

        // 替换占位符
        $content = str_replace(
            ['DummyNamespace', 'DummyClass'],
            [$namespace, $className],
            $template
        );

        file_put_contents($file, $content);
    }
}