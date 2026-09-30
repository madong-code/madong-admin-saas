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
namespace app\adminapi\event\system;

use core\foundation\base\BaseEvent;

/**
 * 登录日志事件
 */
class LoginLogEvent extends BaseEvent
{
    /**
     * 操作名称
     */
    public string $name;

    /**
     * 应用名称
     */
    public string $app;

    /**
     * IP地址
     */
    public string $ip;

    /**
     * IP归属地
     */
    public string $ipLocation;

    /**
     * 浏览器
     */
    public string $browser;

    /**
     * 操作系统
     */
    public string $os;

    /**
     * 状态
     */
    public int $status;

    /**
     * 消息
     */
    public string $message;

    /**
     * 用户名
     */
    public string $userName;

    /**
     * 用户ID
     */
    public string|null|int $userId;

    /**
     * 登录时间
     */
    public int $loginTime;

    /**
     * 访问令牌
     */
    public string $accessToken;

    /**
     * 过期时间
     */
    public int $expiresAt;

    /**
     * 构造函数
     */
    public function __construct(
        string $name,
        string $app,
        string $ip,
        string $ipLocation,
        string $browser,
        string $os,
        int $status,
        string $message,
        string $userName,
        string|null|int $userId,
        int $loginTime,
        string $accessToken,
        int $expiresAt
    ) {
        $this->name = $name;
        $this->app = $app;
        $this->ip = $ip;
        $this->ipLocation = $ipLocation;
        $this->browser = $browser;
        $this->os = $os;
        $this->status = $status;
        $this->message = $message;
        $this->userName = $userName;
        $this->userId = $userId;
        $this->loginTime = $loginTime;
        $this->accessToken = $accessToken;
        $this->expiresAt = $expiresAt;
    }

    public function getEventName(): string
    {
        return 'adminapi.login.log';
    }
}
