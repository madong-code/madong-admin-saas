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
 * Official Website: http://www.madong.cn
 */

namespace app\model\ops\crontab;

use app\enum\system\TaskScheduleCycle;
use app\model\tenant\Tenant;
use core\foundation\base\SystemModel;
use InvalidArgumentException;

/**
 * 定时任务
 *
 * @author Mr.April
 * @since  1.0
 */
class Crontab extends SystemModel
{

    protected $table = 'sys_crontab';

    protected $primaryKey = 'id';

    protected $appends = ['created_date', 'updated_date'];


    protected $casts = [
        'biz_id'      => 'string',
        'created_by'  => 'string',
        'cycle_rule'  => 'array',
        'parameter'   => 'array',
        'id'          => 'string',
        'tenant_id'   => 'string',
        'source'      => 'string',
        'updated_by'  => 'string',
    ];

    protected $fillable = [
        'id',
        'biz_id',
        'title',
        'type',
        'task_cycle',
        'cycle_rule',
        'rule',
        'target',
        'parameter',
        'running_times',
        'last_running_time',
        'enabled',
        'first_started',
        'created_at',
        'updated_at',
        'deleted_at',
        'singleton',
        'remark',
        'tenant_id',
        'source',
    ];

    /**
     *  通过ID获取最后执行记录
     *
     * @return mixed
     */
    public function getLogAttribute(): mixed
    {
        return CrontabLog::getModel()->where(['crontab_id' => $this->id])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * 获取器-创建时间
     *
     * @param $value
     *
     * @return string
     */
    public function getCreateTimeAttribute($value): string
    {
        return getDateText($value);
        return date('Y-m-d H:i:s', $value); // 将时间戳格式化为日期时间字符串
    }

    /**
     * 获取器-last_running_time
     *
     * @param $value
     *
     * @return bool|string
     */
    public function getLastRunningTimeAttribute($value): bool|string
    {
        if (!$value) return '--';
        return getDateText($value);
    }

    /**
     * 获取器-rule_name
     *
     * @param $value
     *
     * @return string|null
     */
    public function getRuleNameAttribute($value): ?string
    {
        // 提前返回空规则
        if (empty($this->cycle_rule)) {
            return null;
        }

        $ruleArr = $this->cycle_rule;
        $weekMap = [0 => '周日', 1 => '周一', 2 => '周二', 3 => '周三', 4 => '周四', 5 => '周五', 6 => '周六'];

        try {
            $taskCycle = TaskScheduleCycle::from($this->task_cycle ?? 1);
        } catch (InvalidArgumentException) {
            return '任务周期配置错误';
        }

        // 公共时间组件生成器（带安全访问）
        $timeComponent = fn($key, $default = 0) => $ruleArr[$key] ?? $default;

        // 基础时间格式
        $baseTimeFormat = function () use ($ruleArr, $timeComponent) {
            $hour   = $timeComponent('hour');
            $minute = $timeComponent('minute');
            return $hour > 0 ? "{$hour}点{$minute}分" : "{$minute}分";
        };

        return match ($taskCycle) {
            TaskScheduleCycle::DAILY => "每天{$baseTimeFormat()}",
            TaskScheduleCycle::HOURLY => "每小时第{$timeComponent('minute')}分",
            TaskScheduleCycle::N_HOURS => "{$timeComponent('hour')}小时第{$timeComponent('minute')}分",
            TaskScheduleCycle::N_MINUTES => "{$timeComponent('minute')}分",
            TaskScheduleCycle::N_SECONDS => "{$timeComponent('second')}秒",
            TaskScheduleCycle::WEEKLY => sprintf(
                "每%s%s",
                $weekMap[$timeComponent('week', 0)] ?? '未知星期',
                $baseTimeFormat()
            ),
            TaskScheduleCycle::MONTHLY => sprintf(
                "每月%d号%s",
                $timeComponent('day', 1),
                $baseTimeFormat()
            ),
            TaskScheduleCycle::YEARLY => sprintf(
                "每年%d月%d号%s",
                $timeComponent('month', 1),
                $timeComponent('day', 1),
                $baseTimeFormat()
            ),
            default => '未知任务规则'
        };
    }

    /**
     * 关联执行志记录表
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CrontabLog::class, 'crontab_id', 'id');
    }

    /**
     * 关联租户（平台视角：全局任务 tenant_id 为 null，关联结果为 null）
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

}
