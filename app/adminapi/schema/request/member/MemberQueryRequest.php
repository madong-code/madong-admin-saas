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
namespace app\adminapi\schema\request\member;

use app\schema\request\BaseQueryRequest;


/**
 * 会员查询请求DTO
 */
class MemberQueryRequest extends BaseQueryRequest
{


    /**
     * 用户名
     */
    public ?string $username = null;

    /**
     * 邮箱
     */
    public ?string $email = null;

    /**
     * 手机号
     */
    public ?string $phone = null;

    /**
     * 状态
     */
    public ?int $status = null;

    /**
     * 等级ID
     */
    public ?int $level_id = null;

    /**
     * 排序字段
     */
    public ?string $order_by = null;

    /**
     * 排序方向
     */
    public ?string $order_dir = null;

}