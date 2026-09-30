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
        if (empty($filePath)) return;

        $fullPath = runtime_path() . $filePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}
