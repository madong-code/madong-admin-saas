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
namespace app\model\tenant;

use core\foundation\base\SystemModel;

/**
 * 租户操作日志模型
 *
 * @property int    $id
 * @property int    $tenant_id   租户ID
 * @property int    $admin_id    管理员ID
 * @property string $admin_name  管理员名称
 * @property string $action      操作类型
 * @property string $target_type 目标类型
 * @property string $target_id   目标ID
 * @property string $before_data 变更前数据(JSON)
 * @property string $after_data  变更后数据(JSON)
 * @property string $ip          IP地址
 * @property string $user_agent  User-Agent
 * @property string $result      结果
 * @property string $error_msg   错误信息
 * @property int    $created_at  创建时间戳
 */
class TenantLog extends SystemModel
{
    protected $table = 'saas_tenant_log';
    protected $primaryKey = 'id';
    public $timestamps = false;


    protected $casts = [
        'admin_id'    => 'string',
        'after_data'  => 'json',
        'before_data' => 'json',
        'tenant_id'   => 'string',
    ];

    /** 成功 */
    const RESULT_SUCCESS = 'success';
    /** 失败 */
    const RESULT_FAIL = 'fail';
}
