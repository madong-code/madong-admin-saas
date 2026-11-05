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
 * Official Website: https://madong.tech
 */

namespace app\command;

use core\security\jwt\JwtSecret;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 存量部署 JWT 密钥生成 / 轮换
 *
 * 用法：
 *   php webman jwt:secret           # 缺失或占位值时生成，已存在合法值则保持幂等
 *   php webman jwt:secret --force   # 强制轮换（所有已签发令牌立即失效）
 *
 * @author Mr.April
 * @since  1.0
 */
#[AsCommand(
    name: 'jwt:secret',
    description: '生成或轮换 .env 中的 JWT_SECRET（轮换后所有已签发令牌失效）'
)]
class JwtSecretGenerateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, '强制轮换已存在的合法密钥');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGenerate($input, $output);
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGenerate($input, $output);
    }

    private function runGenerate(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $envFile = base_path('.env');

        if (!file_exists($envFile)) {
            $template = base_path('.example.env');
            if (!file_exists($template)) {
                $io->error('.example.env 不存在，无法创建 .env');
                return Command::FAILURE;
            }
            if (file_put_contents($envFile, (string)file_get_contents($template)) === false) {
                $io->error('.env 创建失败，请检查目录权限');
                return Command::FAILURE;
            }
        }

        $content = file_get_contents($envFile);
        if ($content === false) {
            $io->error('.env 读取失败，请检查文件权限');
            return Command::FAILURE;
        }

        $force  = (bool)$input->getOption('force');
        $result = JwtSecret::ensureInEnv($content, $force);

        if (!$result['changed']) {
            $io->success('JWT_SECRET 已存在且符合安全要求，本次未改动。使用 --force 可强制轮换。');
            return Command::SUCCESS;
        }

        if (file_put_contents($envFile, $result['content']) === false) {
            $io->error('.env 写入失败，请检查文件权限');
            return Command::FAILURE;
        }

        $io->success($force ? 'JWT_SECRET 已轮换（密钥明文不输出）。' : 'JWT_SECRET 已生成（密钥明文不输出）。');
        $io->warning('请重启服务。所有已签发的 access/refresh 令牌立即失效，用户需重新登录。');

        return Command::SUCCESS;
    }
}
