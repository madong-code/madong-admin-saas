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

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 命令基类
 *
 * 提供通用的工具方法供所有命令使用
 * - SSE 事件处理：适用于需要流式输出的命令（如插件安装/卸载）
 * - 通用工具方法：目录操作、确认、输出等
 *
 * @author Mr.April
 * @since 1.0.0
 */
abstract class BaseCommand extends Command
{
    /**
     * 解析SSE事件
     *
     * @param string $event SSE事件字符串
     * @return array|null 解析后的事件数据
     */
    protected function parseSseEvent(string $event): ?array
    {
        // 提取所有data行的内容
        if (preg_match_all('/^data: (.+)$/m', $event, $matches)) {
            // 合并所有data行
            $data = implode("\n", $matches[1]);
            return json_decode($data, true);
        }
        return null;
    }

    /**
     * 处理SSE事件输出
     *
     * @param string $event SSE事件字符串
     * @param SymfonyStyle $io SymfonyStyle 实例
     * @param string|null $pluginName 插件名称（可选）
     * @return int|null 返回状态码，如果需要提前退出
     */
    protected function handleSseEvent(string $event, SymfonyStyle $io, ?string $pluginName = null): ?int
    {
        try {
            // 直接输出原始事件内容，以便调试
            if (strpos($event, 'data:') !== false) {
                // 提取data部分
                $lines = explode("\n", $event);
                foreach ($lines as $line) {
                    if (strpos($line, 'data:') === 0) {
                        $data = substr($line, 5);
                        if (!empty(trim($data))) {
                            // 尝试解析JSON
                            $jsonData = json_decode($data, true);
                            if ($jsonData) {
                                $eventType = $jsonData['event'] ?? '';
                                $data = $jsonData['data'] ?? [];

                                switch ($eventType) {
                                    case 'progress':
                                        $progress = $data['progress'] ?? 0;
                                        $message = $data['message'] ?? '';
                                        $io->text(sprintf("[%d%%] %s", $progress, $message));
                                        break;

                                    case 'completed':
                                        $message = $data['message'] ?? 'Operation completed successfully';
                                        if ($pluginName) {
                                            $io->success("Plugin '{$pluginName}' {$message}");
                                        } else {
                                            $io->success($message);
                                        }
                                        return Command::SUCCESS;

                                    case 'runtime_error':
                                        $io->error($data['message'] ?? '运行时错误');
                                        return Command::FAILURE;

                                    case 'error':
                                        $io->error($data['message'] ?? 'Operation failed');
                                        return Command::FAILURE;

                                    case 'warning':
                                        $io->warning($data['message'] ?? 'Warning');
                                        break;
                                }
                            } else {
                                // 不是JSON，直接输出
                                $io->text(trim($data));
                            }
                        }
                    }
                }
            } else {
                // 不是SSE事件，直接输出
                $io->text(trim($event));
            }
        } catch (\Throwable $e) {
            // 忽略错误，继续执行
            $io->error('处理SSE事件时出错: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * 执行流式操作（SSE）
     *
     * @param iterable $generator 迭代器
     * @param SymfonyStyle $io SymfonyStyle 实例
     * @param string|null $pluginName 插件名称
     * @return int 命令退出码
     */
    protected function executeStream(iterable $generator, SymfonyStyle $io, ?string $pluginName = null): int
    {
        foreach ($generator as $event) {
            $result = $this->handleSseEvent($event, $io, $pluginName);
            if ($result !== null) {
                return $result;
            }
        }
        return Command::SUCCESS;
    }

    /**
     * 递归删除目录
     *
     * @param string $directory 目录路径
     * @return bool 删除是否成功
     */
    protected function deleteDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return true;
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . '/' . $file;
            if (is_dir($path)) {
                if (!$this->deleteDirectory($path)) {
                    return false;
                }
            } else {
                if (!unlink($path)) {
                    return false;
                }
            }
        }

        return rmdir($directory);
    }

    /**
     * 确认操作
     *
     * @param SymfonyStyle $io SymfonyStyle 实例
     * @param string $message 确认消息
     * @param bool $default 默认值
     * @return bool 是否确认
     */
    protected function confirm(SymfonyStyle $io, string $message, bool $default = false): bool
    {
        return $io->confirm($message, $default);
    }

    /**
     * 密码输入（按行读取，避免污染交互流）
     *
     * 与 Symfony QuestionHelper 一致地使用 fgets(STDIN) 读取整行：旧的逐字符读取
     * 会在 Windows 的 CRLF 中残留 \n，并与 QuestionHelper 的缓冲读取互相干扰，
     * 导致后续的 ask()/confirm() 被跳过或答案错位（例如「是否启用多租户」被默认值吞掉）。
     *
     * @param SymfonyStyle $io SymfonyStyle 实例
     * @param string $message 提示消息
     * @param string $mask 掩码字符，默认 *
     * @return string 输入的密码
     */
    protected function askPassword(SymfonyStyle $io, string $message, string $mask = '*'): string
    {
        $io->text($message);

        // Linux/Mac 终端关闭回显；Windows 终端由系统回显，逐字符读取本来也无法隐藏
        $hideEcho = DIRECTORY_SEPARATOR !== '\\';
        if ($hideEcho) {
            @system('stty -echo 2>/dev/null');
        }
        $line = fgets(STDIN);
        if ($hideEcho) {
            @system('stty echo 2>/dev/null');
        }

        $password = $line === false ? '' : rtrim($line, "\r\n");
        $io->write(str_repeat($mask, strlen($password)));
        $io->newLine();

        return $password;
    }

    /**
     * 确认密码输入
     *
     * @param SymfonyStyle $io SymfonyStyle 实例
     * @param string $message 提示消息
     * @param int $minLength 最小长度
     * @return string 确认后的密码
     */
    protected function askPasswordWithConfirm(SymfonyStyle $io, string $message, int $minLength = 6): string
    {
        $io->text($message);
        
        while (true) {
            $password = $this->askPassword($io, '请输入密码: ');
            
            if (strlen($password) < $minLength) {
                $io->warning("密码长度不能少于 {$minLength} 位");
                continue;
            }
            
            // 确认密码
            $confirm = $this->askPassword($io, '请再次输入密码: ');
            
            if ($password !== $confirm) {
                $io->error('两次输入的密码不一致，请重新输入');
                continue;
            }
            
            return $password;
        }
    }

    /**
     * 输出错误信息并记录
     *
     * @param SymfonyStyle $io SymfonyStyle 实例
     * @param string $message 错误消息
     * @param \Throwable|null $exception 异常对象
     * @return int 返回失败状态码
     */
    protected function outputError(SymfonyStyle $io, string $message, ?\Throwable $exception = null): int
    {
        $io->error($message);
        if ($exception) {
            $io->error(sprintf("File: %s Line: %d", $exception->getFile(), $exception->getLine()));
            if ($io->isVerbose()) {
                $io->text($exception->getTraceAsString());
            }
        }
        return Command::FAILURE;
    }

    /**
     * 输出成功信息
     *
     * @param SymfonyStyle $io SymfonyStyle 实例
     * @param string $message 成功消息
     * @return int 返回成功状态码
     */
    protected function outputSuccess(SymfonyStyle $io, string $message): int
    {
        $io->success($message);
        return Command::SUCCESS;
    }
}
