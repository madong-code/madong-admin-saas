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
namespace core\business\plugin\traits;

use core\foundation\tool\Sse;

/**
 * SSE 进度流 Trait
 * 统一管理 Install/Uninstall 中的进度文件的创建、写入、读取和清理
 */
trait ProgressStreamTrait
{
    /**
     * 创建进度记录临时文件
     *
     * @param string $prefix      文件前缀（install/uninstall）
     * @param string $sessionUuid 会话标识
     *
     * @return string 临时文件路径
     */
    protected function createProgressFile(string $prefix, string $sessionUuid): string
    {
        $outputFile = runtime_path('migrations/plugin/' . $prefix . '_' . $sessionUuid . '.log');
        $outputDir  = dirname($outputFile);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }
        file_put_contents($outputFile, '');
        return $outputFile;
    }

    /**
     * 创建进度回调闭包
     *
     * @param string $outputFile 临时文件路径
     *
     * @return callable 进度回调
     */
    protected function createProgressCallback(string $outputFile): callable
    {
        $callbackCount = 0;
        return function (string $message, ?int $progress = null) use ($outputFile, &$callbackCount) {
            $callbackCount++;
            $progressStr = $progress !== null ? "(progress: {$progress})" : "(no progress)";
            $logLine     = "[CALLBACK #{$callbackCount}] {$message} {$progressStr}\n";
            file_put_contents($outputFile, $logLine, FILE_APPEND);
        };
    }

    /**
     * 输出缓冲模式执行并捕获输出
     *
     * @param callable $fn         要执行的回调
     * @param string   $outputFile 临时文件路径（写入捕获的输出）
     */
    protected function captureOutputWithProgress(callable $fn, string $outputFile): void
    {
        ob_start();
        try {
            $fn();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $output = ob_get_clean();
        if (!empty($output)) {
            file_put_contents($outputFile, $output, FILE_APPEND);
        }
    }

    /**
     * 从进度文件读取并 yield 进度行
     *
     * @param string $outputFile    临时文件路径
     * @param int    $progressValue 进度值
     * @param array  $extra         额外数据
     * @param string $sessionUuid   会话标识
     *
     * @return \Generator
     */
    protected function yieldProgressLines(string $outputFile, int $progressValue, array $extra = [], string $sessionUuid = ''): \Generator
    {
        $fileContent = file_get_contents($outputFile);
        if (!empty($fileContent)) {
            $lines = array_filter(explode("\n", $fileContent));
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    yield Sse::progress($line, $progressValue, $extra, $sessionUuid);
                }
            }
        }
    }

    /**
     * 清理进度记录临时文件
     *
     * @param string $outputFile 临时文件路径
     */
    protected function cleanupProgressFile(string $outputFile): void
    {
        if ($outputFile && file_exists($outputFile)) {
            @unlink($outputFile);
        }
    }
}
