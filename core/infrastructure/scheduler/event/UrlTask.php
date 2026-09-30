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
namespace core\infrastructure\scheduler\event;

use app\enum\system\OperationResult;
use core\infrastructure\scheduler\event\EventBootstrap;

class UrlTask implements EventBootstrap
{
    /**
     * @param $crontab
     *
     * @return array
     */
    public static function parse($crontab): array
    {
        $url  = trim($crontab['target'] ?? '');
        // 目标地址若未携带协议(scheme)，默认补齐 http://，避免 Guzzle 报 "scheme is not allowed"
        if ($url !== '' && !preg_match('#^[a-zA-Z][a-zA-Z\d+.\-]*://#', $url)) {
            $url = 'http://' . $url;
        }
        $code = OperationResult::SUCCESS->value;
        try {
            $client = new \GuzzleHttp\Client();
            $options = [];
            // 透传租户上下文：以 X-Tenant-Id 请求头携带
            $tenantId = $crontab['tenant_id'] ?? '';
            if ($tenantId !== '') {
                $options['headers']['X-Tenant-Id'] = (string) $tenantId;
            }
            $response = $client->get($url, $options);
            $log      = strip_tags($response->getBody()->getContents());
        } catch (\Throwable $throwable) {
            $code = OperationResult::FAILURE->value;
            $log  = $throwable->getMessage();
        }
        return ['code' => $code, 'log' => $log];
    }
}
