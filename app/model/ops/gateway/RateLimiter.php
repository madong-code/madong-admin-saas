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

/**
 * 限流模型
 *
 * 用于存储和管理速率限制规则，支持IP限流、用户限流等功能
 *
 * @author Mr.April
 * @since  1.0
 */
class RateLimiter extends BaseModel
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
    protected $table = 'sys_rate_limiter';

    /**
     * 追加字段
     *
     * @var array
     */
    protected $appends = ['created_date', 'updated_date'];

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
        "conditions",     // 附加条件
        "http_methods",   // HTTP方法
        "path",           // 限制路径
        "limit_type",     // 限制类型(ip-IP限流,user-用户限流)
        "limit_value",    // 限制值
        "period",         // 统计周期(秒)
        "ttl",            // 缓存时间(秒)
        "message",        // 提示信息
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
}
