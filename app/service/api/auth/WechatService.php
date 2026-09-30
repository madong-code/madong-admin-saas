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
namespace app\service\api\auth;

use app\model\member\Member;
use app\model\member\MemberThirdParty;
use app\enum\common\EnabledStatus;
use app\enum\member\ThirdPartyType;
use core\foundation\base\BaseService;
use core\security\jwt\JwtToken;
use support\Redis;

/**
 * 微信认证服务
 */
class WechatService extends BaseService
{
    /**
     * 微信授权登录
     */
    public function wechatLogin(array $data): array
    {
        if (empty($data['code'])) {
            throw new \Exception('授权码不能为空', 400);
        }

        // 获取微信用户信息
        $wechatUserInfo = $this->getWechatUserInfo($data['code'], $data['encrypted_data'] ?? '', $data['iv'] ?? '');

        // 查找或创建用户
        $member = $this->findOrCreateMemberByWechat($wechatUserInfo);

        // 验证用户状态
        if ($member->enabled !== EnabledStatus::ENABLED->value) {
            throw new \Exception('用户已被禁用', 401);
        }

        // 更新登录信息
        $this->updateLoginInfo($member);

        // 生成token
        $token = $this->generateToken($member->id);

        return [
            'code' => 200,
            'msg'  => '登录成功',
            'data' => [
                'token'     => $token,
                'user_info' => [
                    'id'       => $member->id,
                    'username' => $member->username,
                    'nickname' => $member->nickname,
                    'avatar'   => $member->avatar,
                    'phone'    => $member->phone,
                    'email'    => $member->email,
                    'points'   => $member->points,
                    'balance'  => $member->balance,
                ],
            ],
        ];
    }

    /**
     * 微信小程序登录
     */
    public function weappLogin(array $data): array
    {
        if (empty($data['code'])) {
            throw new \Exception('登录code不能为空', 400);
        }

        // 获取小程序用户信息
        $weappUserInfo = $this->getWeappUserInfo($data['code'], $data['encrypted_data'] ?? '', $data['iv'] ?? '');

        // 查找或创建用户
        $member = $this->findOrCreateMemberByWeapp($weappUserInfo);

        // 验证用户状态
        if ($member->enabled !== EnabledStatus::ENABLED->value) {
            throw new \Exception('用户已被禁用', 401);
        }

        // 更新登录信息
        $this->updateLoginInfo($member);

        // 生成token
        $token = $this->generateToken($member->id);

        return [
            'code' => 200,
            'msg'  => '登录成功',
            'data' => [
                'token'     => $token,
                'user_info' => [
                    'id'       => $member->id,
                    'username' => $member->username,
                    'nickname' => $member->nickname,
                    'avatar'   => $member->avatar,
                    'phone'    => $member->phone,
                    'email'    => $member->email,
                    'points'   => $member->points,
                    'balance'  => $member->balance,
                ],
            ],
        ];
    }

    /**
     * 生成微信扫码登录二维码
     */
    public function generateWechatQrCode(): array
    {
        $sceneId   = uniqid('wechat_scan_');
        $qrCodeUrl = $this->generateQrCodeUrl($sceneId);

        // 存储扫码状态
        Redis::setex('wechat_scan:' . $sceneId, 300, json_encode([
            'status'     => 'waiting',
            'created_at' => time(),
        ]));

        return [
            'scene_id'    => $sceneId,
            'qr_code_url' => $qrCodeUrl,
            'expire_time' => 300,
        ];
    }

    /**
     * 检查微信扫码状态
     */
    public function checkWechatScanStatus(string $sceneId): array
    {
        if (empty($sceneId)) {
            throw new \Exception('场景ID不能为空');
        }

        $scanData = Redis::get('wechat_scan:' . $sceneId);
        if (!$scanData) {
            throw new \Exception('二维码已过期');
        }

        $data = json_decode($scanData, true);

        if ($data['status'] === 'scanned') {
            // 扫码成功，返回用户信息
            $memberId = $data['member_id'] ?? 0;
            $member   = Member::find($memberId);

            if ($member) {
                // 更新登录信息
                $this->updateLoginInfo($member);

                // 生成token
                $userInfo = [
                    'id'          => $member->id,
                    'username'    => $member->username,
                    'nickname'    => $member->nickname,
                    'avatar'      => $member->avatar,
                    'phone'       => $member->phone,
                    'email'       => $member->email,
                    'client'      => 'web',
                    'access_exp'  => 7200,
                    'refresh_exp' => 604800,
                ];
                // 使用新的 JwtToken 生成 token
                $jwt = new JwtToken();
                $tokenObj = $jwt->generate((string)$member->id, 'api', $userInfo);
                $token = [
                    'access_token' => $tokenObj->accessToken,
                    'refresh_token' => $tokenObj->refreshToken,
                    'expires_in' => $tokenObj->expiresIn
                ];
                // 删除扫码记录
                Redis::del('wechat_scan:' . $sceneId);
                $msg  = '扫描成功';
                $data = [
                    'status'    => 'success',
                    'token'     => $token,
                    'user_info' => [
                        'id'       => $member->id,
                        'username' => $member->username,
                        'nickname' => $member->nickname,
                        'avatar'   => $member->avatar,
                    ],
                ];
                return compact('msg', 'data');
            }
        }
        $msg  = '查询成功';
        $data = [
            'status' => $data['status'],
        ];
        return compact('msg', 'data');
    }

    /**
     * 检查微信登录可用性
     */
    public function checkWechatAvailability(): array
    {
        $config = config('wechat');
        return [
            'wechat_enabled' => !empty($config['app_id']) && !empty($config['app_secret']),
            'weapp_enabled'  => !empty($config['mini_program']['app_id']) && !empty($config['mini_program']['app_secret']),
        ];
    }

    /**
     * 获取微信用户信息
     */
    private function getWechatUserInfo(string $code, string $encryptedData = '', string $iv = ''): array
    {
        $config = config('wechat');

        if (empty($config['app_id']) || empty($config['app_secret'])) {
            throw new \Exception('微信配置未完成', 500);
        }

        // 获取access_token
        $accessToken = $this->getWechatAccessToken($code);

        // 获取用户信息
        $userInfo = $this->getWechatUserInfoByToken($accessToken);

        return [
            'openid'   => $userInfo['openid'] ?? '',
            'unionid'  => $userInfo['unionid'] ?? '',
            'nickname' => $userInfo['nickname'] ?? '',
            'avatar'   => $userInfo['headimgurl'] ?? '',
            'gender'   => $userInfo['sex'] ?? 0,
            'type'     => ThirdPartyType::WECHAT->value,
        ];
    }

    /**
     * 获取小程序用户信息
     */
    private function getWeappUserInfo(string $code, string $encryptedData = '', string $iv = ''): array
    {
        $config = config('wechat.mini_program');

        if (empty($config['app_id']) || empty($config['app_secret'])) {
            throw new \Exception('小程序配置未完成', 500);
        }

        // 获取session_key
        $sessionInfo = $this->getWeappSession($code);

        // 解密用户信息
        $userInfo = $this->decryptWeappData($encryptedData, $sessionInfo['session_key'], $iv);

        return [
            'openid'   => $sessionInfo['openid'] ?? '',
            'unionid'  => $sessionInfo['unionid'] ?? '',
            'nickname' => $userInfo['nickName'] ?? '',
            'avatar'   => $userInfo['avatarUrl'] ?? '',
            'gender'   => $userInfo['gender'] ?? 0,
            'type'     => ThirdPartyType::WEAPP->value,
        ];
    }

    /**
     * 根据微信信息查找或创建用户
     */
    private function findOrCreateMemberByWechat(array $wechatInfo): Member
    {
        // 查找第三方绑定
        $thirdParty = MemberThirdParty::where('openid', $wechatInfo['openid'])
            ->where('type', ThirdPartyType::WECHAT->value)
            ->first();

        if ($thirdParty) {
            // 返回已绑定的用户
            return Member::find($thirdParty->member_id);
        }

        // 创建新用户
        $memberData = [
            'username' => 'wx_' . substr($wechatInfo['openid'], -8),
            'password' => substr(md5(uniqid()), 0, 8),
            'nickname' => $wechatInfo['nickname'] ?? '微信用户',
            'avatar'   => $wechatInfo['avatar'] ?? '',
            'enabled'  => EnabledStatus::ENABLED->value,
        ];

        $member = Member::create($memberData);

        // 创建第三方绑定
        MemberThirdParty::create([
            'member_id' => $member->id,
            'type'      => ThirdPartyType::WECHAT->value,
            'openid'    => $wechatInfo['openid'],
            'unionid'   => $wechatInfo['unionid'] ?? '',
            'nickname'  => $wechatInfo['nickname'] ?? '',
            'avatar'    => $wechatInfo['avatar'] ?? '',
        ]);

        return $member;
    }

    /**
     * 根据小程序信息查找或创建用户
     */
    private function findOrCreateMemberByWeapp(array $weappInfo): Member
    {
        // 查找第三方绑定
        $thirdParty = MemberThirdParty::where('openid', $weappInfo['openid'])
            ->where('type', ThirdPartyType::WEAPP->value)
            ->first();

        if ($thirdParty) {
            // 返回已绑定的用户
            return Member::find($thirdParty->member_id);
        }

        // 创建新用户
        $memberData = [
            'username' => 'wxapp_' . substr($weappInfo['openid'], -8),
            'password' => substr(md5(uniqid()), 0, 8),
            'nickname' => $weappInfo['nickname'] ?? '小程序用户',
            'avatar'   => $weappInfo['avatar'] ?? '',
            'enabled'  => EnabledStatus::ENABLED->value,
        ];

        $member = Member::create($memberData);

        // 创建第三方绑定
        MemberThirdParty::create([
            'member_id' => $member->id,
            'type'      => ThirdPartyType::WEAPP->value,
            'openid'    => $weappInfo['openid'],
            'unionid'   => $weappInfo['unionid'] ?? '',
            'nickname'  => $weappInfo['nickname'] ?? '',
            'avatar'    => $weappInfo['avatar'] ?? '',
        ]);

        return $member;
    }

    /**
     * 生成二维码URL
     */
    private function generateQrCodeUrl(string $sceneId): string
    {
        $config = config('wechat');
        $appId  = $config['app_id'] ?? '';

        // 这里应该调用微信API生成二维码，暂时返回模拟URL
        return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket={$sceneId}";
    }

    /**
     * 获取微信access_token
     */
    private function getWechatAccessToken(string $code): string
    {
        // 这里应该调用微信API获取access_token
        // 暂时返回模拟数据
        return 'mock_access_token_' . $code;
    }

    /**
     * 根据token获取微信用户信息
     */
    private function getWechatUserInfoByToken(string $accessToken): array
    {
        // 这里应该调用微信API获取用户信息
        // 暂时返回模拟数据
        return [
            'openid'     => 'mock_openid_' . uniqid(),
            'nickname'   => '微信用户',
            'headimgurl' => '',
            'sex'        => 0,
        ];
    }

    /**
     * 获取小程序session
     */
    private function getWeappSession(string $code): array
    {
        // 这里应该调用微信小程序API获取session
        // 暂时返回模拟数据
        return [
            'openid'      => 'mock_weapp_openid_' . uniqid(),
            'session_key' => 'mock_session_key',
        ];
    }

    /**
     * 解密小程序数据
     */
    private function decryptWeappData(string $encryptedData, string $sessionKey, string $iv): array
    {
        // 这里应该解密小程序数据
        // 暂时返回模拟数据
        return [
            'nickName'  => '小程序用户',
            'avatarUrl' => '',
            'gender'    => 0,
        ];
    }

    /**
     * 更新登录信息
     */
    private function updateLoginInfo(Member $member): void
    {
        $member->last_login_time = date('Y-m-d H:i:s');
        $member->last_login_ip   = \request()->getRealIp();
        $member->login_count     = $member->login_count + 1;
        $member->save();
    }

    /**
     * 生成token
     */
    private function generateToken(int $memberId): string
    {
        $token      = md5(uniqid() . $memberId . microtime(true));
        $expireTime = 7 * 24 * 3600; // 7天过期

        // 存储token到Redis
        Redis::setex('user_token:' . md5($token), $expireTime, json_encode([
            'member_id'  => $memberId,
            'login_time' => time(),
        ]));

        return $token;
    }
}