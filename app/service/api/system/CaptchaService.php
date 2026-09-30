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
namespace app\service\api\system;

use app\model\system\config\Config;
use core\foundation\base\BaseService;
use support\Redis;
use Webman\Http\Request;

/**
 * 验证码服务
 */
class CaptchaService extends BaseService
{
    /**
     * 获取验证码图片
     */
    public function getCaptchaImage(): array
    {
        // 生成验证码
        $captcha = $this->generateCaptcha();
        
        // 存储验证码到Redis，5分钟过期
        Redis::setex('captcha:' . $captcha['key'], 300, $captcha['code']);
        
        return [
            'code' => 200,
            'msg' => '获取成功',
            'data' => [
                'captcha_key' => $captcha['key'],
                'captcha_image' => $captcha['image'],
                'expire_time' => 300
            ]
        ];
    }

    /**
     * 发送短信验证码
     */
    public function sendSmsCode(string $type): array
    {
        $request = \request();
        $mobile = $request->post('mobile');
        
        if (empty($mobile)) {
            throw new \Exception('手机号不能为空', 400);
        }
        
        // 验证手机号格式
        if (!preg_match('/^1[3-9]\\d{9}$/', $mobile)) {
            throw new \Exception('手机号格式不正确', 400);
        }
        
        // 检查发送频率
        $lastSendTime = Redis::get('sms_last_send:' . $mobile);
        if ($lastSendTime && time() - $lastSendTime < 60) {
            throw new \Exception('发送过于频繁，请稍后再试', 429);
        }
        
        // 生成验证码
        $code = sprintf('%06d', mt_rand(0, 999999));
        
        // 存储验证码到Redis，10分钟过期
        $cacheKey = 'sms_code:' . $mobile;
        Redis::setex($cacheKey, 600, $code);
        
        // 记录发送时间
        Redis::setex('sms_last_send:' . $mobile, 60, time());
        
        // 发送短信（这里应该调用短信服务商API）
        $this->sendSms($mobile, $code, $type);
        
        return [
            'code' => 200,
            'msg' => '验证码发送成功'
        ];
    }

    /**
     * 生成验证码
     */
    private function generateCaptcha(): array
    {
        // 生成随机验证码
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        
        // 生成唯一key
        $key = md5(uniqid() . microtime(true));
        
        // 这里应该生成验证码图片，暂时返回模拟数据
        $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        return [
            'key' => $key,
            'code' => $code,
            'image' => $image
        ];
    }

    /**
     * 发送短信
     */
    private function sendSms(string $mobile, string $code, string $type): void
    {
        // 这里应该调用短信服务商API
        // 暂时模拟发送成功
        $templates = [
            'login' => '您的登录验证码是：{code}，有效期10分钟',
            'register' => '您的注册验证码是：{code}，有效期10分钟',
            'bind' => '您的绑定验证码是：{code}，有效期10分钟',
            'reset' => '您的密码重置验证码是：{code}，有效期10分钟'
        ];
        
        $template = $templates[$type] ?? '您的验证码是：{code}，有效期10分钟';
        $content = str_replace('{code}', $code, $template);
        
        // 记录发送日志
        \Log::info("短信发送: {$mobile} - {$content}");
    }
}