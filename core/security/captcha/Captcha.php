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
namespace core\security\captcha;

use core\foundation\exception\handler\CaptchaException;
use Ramsey\Uuid\Uuid;
use support\Redis;
use support\Request;
use Throwable;
use Webman\Captcha\CaptchaBuilder;
use Webman\Captcha\PhraseBuilder;

class Captcha
{
    /**
     * 生成验证码
     *
     * @param \support\Request|null $request
     * @param string                $type
     *
     * @return array
     */
    public function captcha(Request|null $request = null, string $type = 'admin-login'): array
    {
        if (empty($request)) {
            $request = \request();
        }
        $builder = new PhraseBuilder(4, 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ');

        $captcha = new CaptchaBuilder(null, $builder);
        $captcha->setBackgroundColor(242, 243, 245);
        $captcha->build(120, 36);

        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();
        // 获取配置
        $config = $this->getCaptchaConfig();
        $code   = strtolower($captcha->getPhrase());

        // 存储验证码
        $this->storeCaptcha($uuid, $code, $config['mode'], $config['expire'], $request);

        // 返回验证码图像
        $img_content = $captcha->get();

        $base64 = 'data:image/png;base64,' . base64_encode($img_content);

        return compact('uuid', 'base64');

    }

    /**
     * 生成手机验证码
     *
     * @param \support\Request|null $request
     * @param int                   $length
     *
     * @return array
     */
    public function generateSmsCaptcha(Request|null $request = null, int $length = 6): array
    {
        if (empty($request)) {
            $request = \request();
        }
        $uid = $request->input('mobile_phone');
        $this->validatePhoneNumber($uid);

        // 生成 6 位数字验证码
        $code = str_pad(rand(0, 999999), $length, '0', STR_PAD_LEFT);

        // 获取配置
        $config = $this->getCaptchaConfig();

        // 存储验证码
        $this->storeSmsCaptcha($uid, $code, $config['mode'], $config['expire'], $request);

        return [
            'code' => $code,
            'uid'  => $uid,
        ];
    }

    /**
     * 检查验证码
     *
     * @param string     $key
     * @param string|int $value
     *
     * @return bool
     */
    public
    function check(string $key, string|int $value): bool
    {
        $config = $this->getCaptchaConfig();
        $code   = $this->retrieveCaptcha($key, $config['mode']);

        return strtolower($value) === $code;
    }

    /**
     * 获取验证码配置
     *
     * @return array
     */
    protected function getCaptchaConfig(): array
    {
        return [
            'mode'   => config('core.security.captcha.mode', 'session'),
            'expire' => config('core.security.captcha.expire', 300),
        ];
    }

    /**
     * 存储验证码
     *
     * @param string  $key
     * @param string  $code
     * @param string  $mode
     * @param int     $expire
     * @param Request $request
     *
     * @throws CaptchaException
     */
    protected function storeCaptcha(string $key, string $code, string $mode, int $expire, Request $request): void
    {
        try {
            if ($mode === 'redis') {
                Redis::set($key, $code, 'EX', $expire);
            } else {
                $request->session()->set($key, $code);
            }
        } catch (\Exception $e) {
            throw new CaptchaException('验证码生成失败，请检查配置', -1);
        }
    }

    /**
     * 存储手机验证码
     *
     * @param string  $uid
     * @param string  $code
     * @param string  $mode
     * @param int     $expire
     * @param Request $request
     *
     * @throws CaptchaException
     */
    protected
    function storeSmsCaptcha(string $uid, string $code, string $mode, int $expire, Request $request): void
    {
        try {
            if ($mode === 'redis') {
                Redis::set($uid, $code, 'EX', $expire);
            } else {
                $request->session()->set($uid, $code);
            }
        } catch (\Exception $e) {
            throw new CaptchaException('验证码生成失败，请检查配置', -1);
        }
    }

    /**
     * 验证手机号
     *
     * @param string $uid
     *
     * @throws CaptchaException
     */
    protected
    function validatePhoneNumber(string $uid): void
    {
        if (!$uid || !preg_match('/^\+?[0-9]{10,15}$/', $uid)) {
            throw new CaptchaException('无效的手机号');
        }
    }

    /**
     * 从存储中检索验证码
     *
     * @param string $key
     * @param string $mode
     *
     * @return string|null
     */
    protected
    function retrieveCaptcha(string $key, string $mode): ?string
    {
        try {
            if ($mode === 'redis') {
                $code = Redis::get($key);
                Redis::del($key); // 删除验证码
            } else {
                $code = session($key);
                session()->forget($key); // 删除验证码
            }
            return $code;
        } catch (\Exception $e) {
            return null;
        }
    }
}
