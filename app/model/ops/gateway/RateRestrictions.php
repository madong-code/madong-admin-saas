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

namespace app\model\ops\gateway;

use core\foundation\base\BaseModel;
use Illuminate\Support\Carbon;

/**
 * 限访模型
 *
 * 用于存储和管理访问限制规则，支持IP黑名单、路径限制等功能
 *
 * @author Mr.April
 * @since  1.0
 */
class RateRestrictions extends BaseModel
{

    /**
     * 数据表主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 数据表名
     *
     * @var string
     */
    protected $table = 'sys_rate_restrictions';

    /**
     * 追加字段
     *
     * @var array
     */
    protected $appends = ['created_date', 'updated_date', 'start_date', 'end_date'];

    /**
     * 可填充字段
     *
     * @var array
     */
    protected $fillable = [
        "id",             // 主键ID
        "name",           // 规则名称
        "enabled",        // 规则状态(0-禁用,1-启用)
        "priority",       // 规则优先级(数字越小优先级越高)
        "match_type",     // 匹配类型(exact-精确匹配,wildcard-通配符匹配,regex-正则表达式匹配)
        "methods",        // 请求方法(GET,POST,PUT等，*表示所有方法)
        "path",           // 限制路径
        "ip",             // IP地址
        "message",        // 提示信息
        "start_time",     // 开始时间(Unix时间戳)
        "end_time",       // 结束时间(Unix时间戳)
        "created_by",     // 创建人
        "updated_by",     // 修改人
        "created_at",     // 创建时间
        "updated_at",     // 修改时间
        'tenant_id',
    ];


    protected $casts = [
        'created_by' => 'string',
        'id'         => 'string',
        'tenant_id'  => 'string',
        'updated_by' => 'string',
    ];

    /**
     * 追加起始时间
     *
     * @return string|null
     */
    public function getStartDateAttribute(): ?string
    {
        if ($this->getAttribute('start_time')) {
            try {
                $timestamp = $this->getRawOriginal('start_time');
                if (empty($timestamp)) {
                    return null;
                }
                $carbonInstance = Carbon::createFromTimestamp($timestamp);
                return $carbonInstance->setTimezone(config('app.default_timezone'))->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    public function getEndDateAttribute(): ?string
    {
        if ($this->getAttribute('end_time')) {
            try {
                $timestamp = $this->getRawOriginal('end_time');
                if (empty($timestamp)) {
                    return null;
                }
                $carbonInstance = Carbon::createFromTimestamp($timestamp);
                return $carbonInstance->setTimezone(config('app.default_timezone'))->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

}
