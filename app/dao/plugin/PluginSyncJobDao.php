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

namespace app\dao\plugin;

use app\model\plugin\PluginSyncJob;
use core\foundation\base\BaseDao;

/**
 * 插件同步任务 DAO
 *
 * @author Mr.April
 * @since  1.0
 */
class PluginSyncJobDao extends BaseDao
{
    protected function setModel(): string
    {
        return PluginSyncJob::class;
    }

    /**
     * 推进进度
     */
    public function bumpProgress(string $jobId, int $doneDelta, ?string $error = null, ?string $status = null): bool
    {
        $job = $this->query()->where('id', $jobId)->first();
        if (!$job) {
            return false;
        }
        $update = [
            'progress_done' => $job->progress_done + $doneDelta,
        ];
        if ($error !== null) {
            $update['error'] = $error;
        }
        if ($status !== null) {
            $update['status'] = $status;
            if (in_array($status, ['success', 'failed'], true)) {
                $update['finished_at'] = time();
            }
            if ($status === 'running') {
                $update['started_at'] = $job->started_at ?? time();
            }
        }
        return $this->update($jobId, $update) > 0;
    }
}
