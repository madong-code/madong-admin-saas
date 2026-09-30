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
namespace core\communication\email;

use app\service\admin\system\config\ConfigService as AdminConfigService;
use app\service\platform\system\ConfigService as PlatformConfigService;
use core\foundation\exception\handler\AdminException;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use support\Container;

/**
 * 邮件服务
 *
 * @author Mr.April
 * @since  1.0
 */
class MailService
{

    /**
     * group_code → ConfigService 类名映射
     */
    private const CONFIG_SERVICE_MAP = [
        'platform' => PlatformConfigService::class,
        'setting'  => AdminConfigService::class,
    ];

    /**
     * config 表中存储邮件配置的 code
     */
    const SETTING_CONFIG_CODE = 'email';

    protected ?PHPMailer $mailer;
    protected string $groupCode;

    public function __construct($host = null, $username = null, $password = null, $port = null, $encryption = null, string $groupCode = 'setting')
    {
        $this->groupCode = $groupCode;
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('PHP OpenSSL extension is not installed or enabled.');
        }
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            throw new \RuntimeException('请执行 composer require phpmailer/phpmailer');
        }
        $this->mailer = new PHPMailer(true); // 使用异常处理
        $this->configure($host, $username, $password, $port, $encryption);
    }

    /**
     * 根据 group_code 决议对应的 ConfigService 实例
     */
    private function resolveConfigService(): object
    {
        $class = self::CONFIG_SERVICE_MAP[$this->groupCode] ?? AdminConfigService::class;
        return Container::make($class);
    }

    protected function configure(?string $host = null, ?string $username = null, ?string $password = null, ?string $port = null, ?string $encryption = null): void
    {
        $configService = $this->resolveConfigService();
        $config        = $configService->config(self::SETTING_CONFIG_CODE, [], ['group_code' => $this->groupCode]);
        $this->mailer->isSMTP(); // 使用 SMTP
        $this->mailer->Host       = $host ?? $config['Host']; // 默认 SMTP 服务器地址
        $this->mailer->SMTPAuth   = true; // 启用 SMTP 身份验证
        $this->mailer->Username   = $username ?? $config['Username']; // 默认 SMTP 用户名
        $this->mailer->Password   = $password ?? $config['Password']; // 默认 SMTP 密码
        $this->mailer->SMTPSecure = $encryption ?? $config['SMTPSecure']; // 默认加密方式
        $this->mailer->Port       = $port ?? $config['Port']; // 默认 TCP 端口号
    }

    /**
     * @param string $to       收件人邮箱
     * @param string $subject  主题
     * @param string $content  内容
     * @param string $fromName 发件人显示名称
     *
     * @return true|array
     * @throws \core\foundation\exception\handler\AdminException
     */
    public function send(string $to, string $subject, string $content, string $fromName = ''): true|array
    {
        try {
            // 设置发件人 - 使用SMTP用户名作为发件人地址，$fromName作为显示名称
            $this->mailer->setFrom($this->mailer->Username, $fromName);

            // 收件人
            $this->mailer->addAddress($to);

            // 邮件内容
            $this->mailer->isHTML(true); // 设置邮件格式为 HTML
            $this->mailer->CharSet = 'UTF-8'; // 设置字符编码为 UTF-8
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $content;

            // 发送邮件
            $this->mailer->send();
            return true; // 发送成功
        } catch (Exception $e) {
            return throw new AdminException($e->getMessage());// 发送失败，返回错误信息
        }
    }
}
