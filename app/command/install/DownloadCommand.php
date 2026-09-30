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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 下载模板代码命令（下载到 template 目录）
 *
 * 使用方法：
 *   php webman madong-download:template            # 下载所有模板
 *   php webman madong-download:template -m          # 仅下载 mono 模板
 *   php webman madong-download:template -w          # 仅下载 web 模板
 *   php webman madong-download:template -s          # 仅下载 skills 模板
 *   php webman madong-download:template -b develop  # 指定分支
 *   php webman madong-download:template -f          # 强制更新
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong-download:template',
    description: 'Download template code to template directory',
    aliases: ['madong-download:template'],
    hidden: false
)]
class DownloadCommand extends BaseCommand
{
    // 模板配置：mono（monorepo）和 web（独立前台）
    private array $templateConfigs = [
        'mono' => [
            'name'     => 'Monorepo',
            'git_url'  => 'https://gitee.com/motion-code/madong-mono.git',
            'dir_name' => 'template' . DIRECTORY_SEPARATOR . 'mono',
        ],
        'web' => [
            'name'     => 'Web 前台',
            'git_url'  => 'https://gitee.com/motion-code/web-nuxt.git',
            'dir_name' => 'template' . DIRECTORY_SEPARATOR . 'web',
        ],
        'skills' => [
            'name'     => '技能模板',
            'git_url'  => 'https://gitee.com/motion-code/madong-saas-skills.git',
            'dir_name' => 'skills',
        ],
    ];

    protected function configure(): void
    {
        $this
            ->addOption('mono', 'm', InputOption::VALUE_NONE, 'Download mono template (template/mono)')
            ->addOption('web', 'w', InputOption::VALUE_NONE, 'Download web template (template/web)')
            ->addOption('skills', 's', InputOption::VALUE_NONE, 'Download skills template (skills/)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force update (overwrite existing)')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Git branch to download', 'main');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Template Downloader');

        // 检查 Git
        if (!$this->isGitAvailable()) {
            $io->error('Git is not available. Please install Git first.');
            $io->note('Download from: https://git-scm.com/downloads');
            return Command::FAILURE;
        }

        $force = $input->getOption('force');
        $branch = $input->getOption('branch');
        $rootDir = dirname(base_path());

        // 确定要下载的项目
        $projects = [];
        foreach (array_keys($this->templateConfigs) as $name) {
            if ($input->getOption($name)) {
                $projects[] = $name;
            }
        }
        if (empty($projects)) {
            $projects = array_keys($this->templateConfigs);
        }

        // 确保 template 目录存在
        $templateBase = $rootDir . DIRECTORY_SEPARATOR . 'template';
        if (!is_dir($templateBase)) {
            mkdir($templateBase, 0755, true);
            $io->info('Created template directory');
        }

        $success = 0;
        foreach ($projects as $name) {
            $config = $this->templateConfigs[$name];
            $io->section("Downloading: {$config['name']}");

            $targetDir = $rootDir . DIRECTORY_SEPARATOR . $config['dir_name'];

            if (is_dir($targetDir)) {
                // 已存在 → git pull
                if (!$force && !$io->confirm("'{$config['dir_name']}' already exists. Update it?", true)) {
                    $io->note("Skipped {$config['dir_name']}");
                    continue;
                }
                if ($this->execGit("cd \"{$targetDir}\" && git pull origin {$branch}", $io)) {
                    $io->success("Updated {$config['dir_name']}");
                    $success++;
                }
            } else {
                // 不存在 → git clone
                $io->info("Cloning {$config['name']}...");
                if ($this->execGit("cd \"{$rootDir}\" && git clone -b {$branch} {$config['git_url']} {$config['dir_name']}", $io)) {
                    $io->success("Cloned to {$config['dir_name']}");
                    $success++;
                }
            }
        }

        // 总结
        $total = count($projects);
        $io->section('Summary');
        if ($success === $total) {
            $io->success("All {$total} template(s) downloaded successfully!");
            return Command::SUCCESS;
        }
        if ($success > 0) {
            $io->warning("Downloaded {$success} / {$total} template(s)");
            return Command::FAILURE;
        }
        $io->error("Failed to download any template");
        return Command::FAILURE;
    }

    /**
     * 执行 Git 命令并输出结果
     */
    private function execGit(string $command, SymfonyStyle $io): bool
    {
        $io->text("> {$command}");
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        foreach ($output as $line) {
            $io->text($line);
        }
        return $returnVar === 0;
    }

    private function isGitAvailable(): bool
    {
        $output = [];
        $returnVar = 0;
        exec('git --version', $output, $returnVar);
        return $returnVar === 0;
    }
}
