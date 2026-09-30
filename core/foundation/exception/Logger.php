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
namespace core\foundation\exception;


class Logger extends \support\Log
{
    /**
     * 处理静态方法调用
     *
     * @param string $name      方法名
     * @param array  $arguments 参数数组
     *
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if ($name === 'error') {
            return self::handleErrorLog($arguments);
        }

        return parent::__callStatic($name, $arguments);
    }

    /**
     * 处理错误日志记录
     *
     * @param array $arguments 原始参数
     *
     * @return mixed
     */
    protected static function handleErrorLog(array $arguments): mixed
    {
        $config   = config('core.foundation.exception.exception_handler', []);
        $original = $arguments[1] ?? [];

        $logData            = self::prepareLogData($original);
        $logData['message'] = current($arguments);

        if (!empty(request())) {
            self::enrichRequestData($logData);
        }

        $logData['environment'] = self::determineEnvironment($logData['domain'] ?? '', $config);

        return parent::__callStatic('error', [$logData['message'], $logData]);
    }

    /**
     * 准备日志数据
     *
     * @param array $original 原始数据
     *
     * @return array
     */
    protected static function prepareLogData(array $original): array
    {
        return [
            'error'         => $original['error'] ?? '--',
            'domain'        => $original['domain'] ?? '--',
            'request_url'   => $original['request_url'] ?? '--',
            'client_ip'     => $original['client_ip'] ?? '127.0.0.1',
            'timestamp'     => $original['timestamp'] ?? date('Y-m-d H:i:s'),
            'request_param' => $original['request_param'] ?? [],
            'file'          => $original['file'] ?? '--',
            'line'          => $original['line'] ?? '--',
        ];
    }

    /**
     * 丰富请求相关数据
     *
     * @param array $logData 日志数据引用
     */
    protected static function enrichRequestData(array &$logData): void
    {
        $logData['domain']        = request()->host();
        $logData['request_url']   = request()->uri();
        $logData['client_ip']     = request()->getRealIp();
        $logData['request_param'] = request()->all();
    }

    /**
     * 确定当前环境
     *
     * @param string $domain 当前域名
     * @param array  $config 配置数组
     *
     * @return string
     */
    protected static function determineEnvironment(string $domain, array $config): string
    {
        $environments = [
            'dev' => '测试环境',
            'pre'  => '预发环境',
            'prod' => '正式环境',
        ];

        foreach ($environments as $key => $title) {
            if (!empty($config['domain'][$key]) && str_contains($domain, $config['domain'][$key])) {
                return $title;
            }
        }

        return '开发环境';
    }
}
