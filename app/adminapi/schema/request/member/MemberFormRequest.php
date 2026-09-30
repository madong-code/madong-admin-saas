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

use app\schema\request\BaseFormRequest;


/**
 * 会员表单请求DTO
 */
class MemberFormRequest extends BaseFormRequest
{
    /**
     * 用户名
     */
    public string $username;

    /**
     * 邮箱
     */
    public ?string $email = null;

    /**
     * 手机号
     */
    public ?string $phone = null;

    /**
     * 密码
     */
    public ?string $password = null;

    /**
     * 昵称
     */
    public ?string $nickname = null;

    /**
     * 头像
     */
    public ?string $avatar = null;

    /**
     * 等级ID
     */
    public int $level_id = 1;

    /**
     * 积分
     */
    public int $points = 0;

    /**
     * 余额
     */
    public float $balance = 0;

    /**
     * 性别
     */
    public int $gender = 0;

    /**
     * 生日
     */
    public ?string $birthday = null;

    /**
     * 状态
     */
    public int $status = 1;

}