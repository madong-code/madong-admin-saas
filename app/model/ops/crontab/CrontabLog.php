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

use core\foundation\base\SystemModel;

/**
 *定时任务日志
 */
class CrontabLog extends SystemModel
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sys_crontab_log';

    /**
     * The primary key associated with the table
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'crontab_id',
        'target',
        'log',
        'return_code',
        'running_time',
        'created_at',
        'updated_at',
        'tenant_id',
        'source',
    ];


    protected $casts = [
        'crontab_id'  => 'string',
        'id'          => 'string',
        'tenant_id'   => 'string',
        'source'      => 'string',
    ];

    /**
     * 获取 create_time 属性的访问器。
     *
     * @param $value
     *
     * @return string
     */
    public function getCreateTimeAttr($value): string
    {
        return date('Y-m-d H:i:s', $value); // 将时间戳格式化为日期时间字符串
    }

    public function getRunningTimeAttr($value): string
    {
        return number_format($value, 2) . 'ms'; // 将时间戳格式化为日期时间字符串
    }

}




















