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

namespace app\queue\redis;

use core\foundation\base\BaseQueueConsumer;
use core\infrastructure\logger\Logger;
use core\io\excel\ExportPathGuard;

/**
 * 删除导出的残留excel文件
 *
 * @author Mr.April
 * @since  1.0
 */
class RemoveExcelFile extends BaseQueueConsumer
{
    public string $queue = 'remove-excel-file';
    protected int $maxRetry = 1; // 文件删除无需多次重试

    protected function handle(array $data): void
    {
        $filePath = $data['file_path'] ?? '';
        if (!is_string($filePath) || $filePath === '') return;

        try {
            // 队列消息可被独立投递（平台端队列管理），消费者必须自行校验，不能依赖控制器
            $fullPath = ExportPathGuard::resolve($filePath);
        } catch (\InvalidArgumentException $e) {
            Logger::warning('[RemoveExcelFile] 拒绝非法导出文件路径', ['reason' => $e->getMessage()]);
            return;
        }

        if (is_file($fullPath) && !@unlink($fullPath)) {
            Logger::warning('[RemoveExcelFile] 导出文件删除失败', []);
        }
    }
}
