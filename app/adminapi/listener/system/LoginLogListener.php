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

use app\adminapi\event\system\LoginLogEvent;
use app\dao\ops\logs\LoginLogDao;
use core\foundation\base\BaseListener;
use support\Container;

/**
 * 登录日志监听器
 * 处理登录日志事件，将登录记录保存到数据库
 */
class LoginLogListener extends BaseListener
{
    protected function process($event): void
    {
        $this->saveLoginLog($event);
    }

    /**
     * 保存登录日志
     */
    private function saveLoginLog(LoginLogEvent $event): void
    {
        /** @var LoginLogDao $dao */
        $dao = Container::make(LoginLogDao::class);

        $dao->save([
            'user_id'     => $event->userId,
            'app'         => $event->app,
            'ip'          => $event->ip,
            'ip_location' => $event->ipLocation,
            'os'          => $event->os,
            'browser'     => $event->browser,
            'status'      => $event->status,
            'message'     => $event->message,
            'login_time'  => $event->loginTime,
            'key'         => md5($event->accessToken),
            'expires_at'  => $event->expiresAt,
            'remark'      => $event->status == 1 ? '登录成功' : '登录失败',
        ]);
    }
}
