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
 * 操作日志事件
 */
class OperationLogEvent extends BaseEvent
{
    /**
     * 操作名称
     */
    public string $name;

    /**
     * OA 标签
     */
    public array $oaTags;

    /**
     * OA 描述
     */
    public string $oaDescription;

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
     * 请求URL
     */
    public string $url;

    /**
     * 控制器类名
     */
    public string $className;

    /**
     * 操作方法
     */
    public string $action;

    /**
     * 请求方法
     */
    public string $method;

    /**
     * 请求参数
     */
    public string $param;

    /**
     * 响应结果
     */
    public array|string $result;

    /**
     * 用户名
     */
    public string $userName;

    /**
     * 用户ID
     */
    public int $userId;

    /**
     * 构造函数
     */
    public function __construct(
        string $name,
        array $oaTags,
        string $oaDescription,
        string $app,
        string $ip,
        string $ipLocation,
        string $browser,
        string $os,
        string $url,
        string $className,
        string $action,
        string $method,
        string $param,
        array|string $result,
        string $userName,
        int $userId
    ) {
        $this->name = $name;
        $this->oaTags = $oaTags;
        $this->oaDescription = $oaDescription;
        $this->app = $app;
        $this->ip = $ip;
        $this->ipLocation = $ipLocation;
        $this->browser = $browser;
        $this->os = $os;
        $this->url = $url;
        $this->className = $className;
        $this->action = $action;
        $this->method = $method;
        $this->param = $param;
        $this->result = $result;
        $this->userName = $userName;
        $this->userId = $userId;
    }

    public function getEventName(): string
    {
        return 'adminapi.operation.log';
    }
}
