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
 * Official Website: https://core.tech
 */

namespace app\service\platform\ops\crontab;

use app\dao\ops\crontab\CrontabLogDao;
use core\foundation\base\BaseService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * 平台定时任务日志服务
 *
 * 平台端查看全部定时任务执行日志，不做租户隔离过滤。
 *
 * @author Mr.April
 * @since  1.0
 */
class CrontabLogService extends BaseService
{
    public function __construct(CrontabLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 写入一条定时任务执行日志（全局日志 tenant_id 恒为 NULL）
     *
     * @param array $data
     *
     * @return mixed
     */
    public function saveLog(array $data)
    {
        $data['tenant_id'] = $data['tenant_id'] ?? null;
        return $this->dao->save($data);
    }

    /**
     * 列表查询（平台全局作用域：仅返回 tenant_id IS NULL 的日志）
     *
     * @param array           $where
     * @param string|array    $field
     * @param int             $page
     * @param int             $limit
     * @param string          $order
     * @param array           $with
     * @param bool            $search
     *
     * @return \Illuminate\Database\Eloquent\Collection|null
     */
    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false): ?Collection
    {
        return $this->dao->selectList($where, $field, $page, $limit, $order, $with, $search);
    }

    /**
     * 列表计数
     *
     * @param array $where
     *
     * @return int
     */
    public function getCount(array $where): int
    {
        return $this->dao->getCount($where);
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
