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

/**
 * 微信服务
 */
class WechatService extends BaseService
{
    /**
     * 获取微信授权码
     */
    public function getWechatAuthCode(array $params): array
    {
        $redirectUrl = $params['redirect_url'] ?? '';
        
        if (empty($redirectUrl)) {
            throw new \Exception('回调URL不能为空', 400);
        }

        $config = config('wechat');
        $appId = $config['app_id'] ?? '';
        
        if (empty($appId)) {
            throw new \Exception('微信配置未完成', 500);
        }

        // 生成授权URL
        $scope = 'snsapi_userinfo'; // 需要获取用户信息
        $state = md5(uniqid() . microtime(true));
        
        $authUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?" . http_build_query([
            'appid' => $appId,
            'redirect_uri' => urlencode($redirectUrl),
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state
        ]) . "#wechat_redirect";

        return [
            'code' => 200,
            'msg' => '获取成功',
            'data' => [
                'auth_url' => $authUrl,
                'state' => $state
            ]
        ];
    }

    /**
     * 同步微信用户信息
     */
    public function syncWechatUserInfo(array $params): array
    {
        $code = $params['code'] ?? '';
        
        if (empty($code)) {
            throw new \Exception('授权码不能为空', 400);
        }

        $config = config('wechat');
        
        if (empty($config['app_id']) || empty($config['app_secret'])) {
            throw new \Exception('微信配置未完成', 500);
        }

        // 获取access_token
        $accessToken = $this->getAccessTokenByCode($code, $config['app_id'], $config['app_secret']);
        
        // 获取用户信息
        $userInfo = $this->getUserInfoByToken($accessToken['access_token'], $accessToken['openid']);

        return [
            'code' => 200,
            'msg' => '同步成功',
            'data' => $userInfo
        ];
    }

    /**
     * 获取微信 JSSDK 配置
     */
    public function getWechatJssdkConfig(array $params): array
    {
        $url = $params['url'] ?? '';
        
        if (empty($url)) {
            throw new \Exception('URL不能为空', 400);
        }

        $config = config('wechat');
        $appId = $config['app_id'] ?? '';
        
        if (empty($appId)) {
            throw new \Exception('微信配置未完成', 500);
        }

        // 获取jsapi_ticket
        $ticket = $this->getJsapiTicket();
        
        // 生成签名
        $nonceStr = $this->generateNonceStr();
        $timestamp = time();
        $signature = $this->generateSignature($ticket, $nonceStr, $timestamp, $url);

        return [
            'code' => 200,
            'msg' => '获取成功',
            'data' => [
                'appId' => $appId,
                'timestamp' => $timestamp,
                'nonceStr' => $nonceStr,
                'signature' => $signature,
                'url' => $url
            ]
        ];
    }

    /**
     * 通过code获取access_token
     */
    private function getAccessTokenByCode(string $code, string $appId, string $appSecret): array
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?" . http_build_query([
            'appid' => $appId,
            'secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code'
        ]);

        // 这里应该调用微信API，暂时返回模拟数据
        return [
            'access_token' => 'mock_access_token',
            'expires_in' => 7200,
            'refresh_token' => 'mock_refresh_token',
            'openid' => 'mock_openid_' . uniqid(),
            'scope' => 'snsapi_userinfo'
        ];
    }

    /**
     * 通过token获取用户信息
     */
    private function getUserInfoByToken(string $accessToken, string $openid): array
    {
        $url = "https://api.weixin.qq.com/sns/userinfo?" . http_build_query([
            'access_token' => $accessToken,
            'openid' => $openid,
            'lang' => 'zh_CN'
        ]);

        // 这里应该调用微信API，暂时返回模拟数据
        return [
            'openid' => $openid,
            'nickname' => '微信用户',
            'sex' => 1,
            'province' => '广东省',
            'city' => '深圳市',
            'country' => '中国',
            'headimgurl' => '',
            'privilege' => [],
            'unionid' => ''
        ];
    }

    /**
     * 获取jsapi_ticket
     */
    private function getJsapiTicket(): string
    {
        // 检查缓存
        $cacheKey = 'wechat_jsapi_ticket';
        $ticket = Redis::get($cacheKey);
        
        if ($ticket) {
            return $ticket;
        }

        // 获取access_token
        $accessToken = $this->getAccessToken();
        
        // 获取ticket
        $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$accessToken}&type=jsapi";
        
        // 这里应该调用微信API，暂时返回模拟数据
        $mockTicket = 'mock_jsapi_ticket_' . uniqid();
        
        // 缓存2小时
        Redis::setex($cacheKey, 7200, $mockTicket);
        
        return $mockTicket;
    }

    /**
     * 获取access_token
     */
    private function getAccessToken(): string
    {
        // 检查缓存
        $cacheKey = 'wechat_access_token';
        $accessToken = Redis::get($cacheKey);
        
        if ($accessToken) {
            return $accessToken;
        }

        $config = config('wechat');
        $url = "https://api.weixin.qq.com/cgi-bin/token?" . http_build_query([
            'grant_type' => 'client_credential',
            'appid' => $config['app_id'],
            'secret' => $config['app_secret']
        ]);

        // 这里应该调用微信API，暂时返回模拟数据
        $mockToken = 'mock_access_token_' . uniqid();
        
        // 缓存2小时
        Redis::setex($cacheKey, 7200, $mockToken);
        
        return $mockToken;
    }

    /**
     * 生成随机字符串
     */
    private function generateNonceStr(int $length = 16): string
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 生成签名
     */
    private function generateSignature(string $ticket, string $nonceStr, int $timestamp, string $url): string
    {
        $string = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        return sha1($string);
    }
}