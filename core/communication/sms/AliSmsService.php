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
namespace core\communication\sms;

use app\service\admin\system\config\ConfigService as AdminConfigService;
use app\service\platform\system\ConfigService as PlatformConfigService;
use core\foundation\exception\handler\AdminException;
use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;
use support\Container;

/**
 * 短信发送-阿里云
 *
 * @author Mr.April
 * @since  1.0
 */
class AliSmsService
{

    /**
     * group_code → ConfigService 类名映射
     */
    private const CONFIG_SERVICE_MAP = [
        'platform' => PlatformConfigService::class,
        'setting'  => AdminConfigService::class,
    ];

    /**
     * config 表中存储短信配置的 code
     */
    const SETTING_CONFIG_CODE = 'sms';
    protected ?EasySms $easySms;
    protected string $groupCode;

    /**
     * @param string $groupCode 配置作用域，默认 'setting'（admin端），传 'platform' 读取平台端配置
     */
    public function __construct(string $groupCode = 'setting')
    {
        $this->groupCode = $groupCode;
        $configService   = $this->resolveConfigService();
        $config          = $configService->config(self::SETTING_CONFIG_CODE, [], ['group_code' => $this->groupCode]);
        $this->easySms   = new EasySms([
            'default' => [
                'driver' => 'aliyun',
                'config' => [
                    'access_key' => $config['access_key'] ?? '',//你的默认阿里云Access Key
                    'secret_key' => $config['secret_key'] ?? '',//你的默认阿里云Secret Key
                    'sign_name'  => $config['sign_name'] ?? '',//你的默认短信签名
                ],
            ],
        ]);
    }

    /**
     * 根据 group_code 决议对应的 ConfigService 实例
     */
    private function resolveConfigService(): object
    {
        $class = self::CONFIG_SERVICE_MAP[$this->groupCode] ?? AdminConfigService::class;
        return Container::make($class);
    }

    /**
     * @param string|int $to       手机号码
     * @param string     $template 短信模板CODE
     * @param array      $data     ['变量1' => '值1','变量2' => '值2']
     *
     * @return array
     */
    public function send(string|int $to, string $template, array $data = []): array
    {
        try {
            return $this->easySms->send($to, [
                'template' => $template,
                'data'     => $data,
            ]);
        } catch (NoGatewayAvailableException|\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }
}
