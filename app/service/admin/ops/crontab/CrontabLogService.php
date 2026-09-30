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

namespace app\service\admin\ops\crontab;

use app\dao\ops\crontab\CrontabLogDao;
use core\foundation\base\BaseService;
use Illuminate\Support\Arr;

class CrontabLogService extends BaseService
{
    public function __construct(CrontabLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 写入一条定时任务执行日志
     *
     * @param array $data
     *
     * @return mixed
     */
    public function saveLog(array $data)
    {
        return $this->dao->save($data);
    }

    /**
     * 删除指定定时任务关联的全部执行日志
     *
     * 支持单个或批量任务ID，统一以 whereIn 处理，避免批量删除时
     * 误用 where('crontab_id', [...]) 导致日志未清理。
     *
     * @param array|int|string $crontabId 定时任务ID（支持单个或批量）
     */
    public function deleteByCrontabId(array|int|string $crontabId): void
    {
        $ids = Arr::wrap($crontabId);
        if (empty($ids)) {
            return;
        }
        $this->dao->getModel()->query()->whereIn('crontab_id', $ids)->delete();
    }
}
