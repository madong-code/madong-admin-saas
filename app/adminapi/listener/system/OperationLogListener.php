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
namespace app\adminapi\listener\system;

use app\adminapi\event\system\OperationLogEvent;
use app\dao\ops\logs\OperateLogDao;
use core\foundation\base\BaseListener;
use support\Container;

/**
 * 操作日志监听器
 *
 * 处理操作日志事件，将操作记录保存到数据库
 */
class OperationLogListener extends BaseListener
{
    protected function process($event): void
    {
        $this->saveOperateLog($event);
    }

    /**
     * 保存操作日志
     */
    private function saveOperateLog(OperationLogEvent $event): void
    {
        /** @var OperateLogDao $dao */
        $dao = Container::make(OperateLogDao::class);

        $dao->save([
            'name'           => $event->name,
            'app'            => $event->app,
            'ip'             => $event->ip,
            'ip_location'    => $event->ipLocation,
            'browser'        => $event->browser,
            'os'             => $event->os,
            'url'            => $event->url,
            'class_name'     => $event->className,
            'action'         => $event->action,
            'method'         => $event->method,
            'param'          => $event->param,
            'result'         => is_array($event->result) ? json_encode($event->result, JSON_UNESCAPED_UNICODE) : $event->result,
            'user_name'      => $event->userName,
        ]);
    }
}
