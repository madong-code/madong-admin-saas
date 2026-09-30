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

use app\api\CurrentMember;
use app\dao\member\MemberThirdPartyDao;
use app\enum\member\ThirdPartyPlatform;
use core\foundation\base\BaseService;
use support\Container;
use support\Redis;

/**
 * 第三方认证服务
 */
class ThirdPartyAuthService extends BaseService
{
    public function __construct(private readonly MemberThirdPartyDao $thirdPartyDao)
    {
    }

    /**
     * 获取第三方绑定列表
     */
    public function getThirdPartyList(): array
    {
        $currentMember = Container::make(CurrentMember::class);
        $memberId = $currentMember->id();
        if (empty($memberId)) {
            throw new \Exception('用户未登录', 401);
        }
        $thirdParties = $this->thirdPartyDao->getMemberThirdParties($memberId);
        $platforms = [
            [
                'type'     => 'qq',
                'name'     => 'QQ',
                'platform' => ThirdPartyPlatform::QQ->value,
                'binded'   => false,
            ],
            [
                'type'     => 'wechat',
                'name'     => '微信',
                'platform' => ThirdPartyPlatform::WECHAT->value,
                'binded'   => false,
            ],
            [
                'type'     => 'douyin',
                'name'     => '抖音',
                'platform' => ThirdPartyPlatform::DOUYIN->value,
                'binded'   => false,
            ],
            [
                'type'     => 'weibo',
                'name'     => '微博',
                'platform' => ThirdPartyPlatform::WEIBO->value,
                'binded'   => false,
            ],
        ];

        foreach ($thirdParties as $party) {
            foreach ($platforms as &$platform) {
                if ($platform['platform'] == $party['platform']) {
                    $platform['binded'] = true;
                    $platform['info']   = [
                        'account' => $party['nickname'] ?? $party['openid'] ?? '',
                        'avatar'   => $party['avatar'] ?? '',
                        'gender'   => $party['gender'] ?? 0,
                        'openid'   => $party['openid'] ?? '',
                    ];
                    
                    $bindTime = $party['updated_at'] ?? '';
                    if ($bindTime) {
                        $platform['bind_time'] = date('Y-m-d H:i:s', strtotime($bindTime));
                    } else {
                        $platform['bind_time'] = '';
                    }
                    
                    break;
                }
            }
        }

        return $platforms;
    }

    /**
     * 绑定第三方账号
     *
     * @throws \Exception
     */
    public function bindThirdParty(array $data): void
    {
        if (empty($data['platform']) || empty($data['openid'])) {
            throw new \Exception('平台和OpenID不能为空', 400);
        }

        $platform = ThirdPartyPlatform::tryFrom((int)$data['platform']);
        if (!$platform) {
            throw new \Exception('无效的平台类型', 400);
        }

        $currentMember = Container::make(CurrentMember::class);
        $memberId = $currentMember->id();
        
        if (empty($memberId)) {
            throw new \Exception('用户未登录', 401);
        }

        if ($this->thirdPartyDao->isBound($platform->value, $data['openid'])) {
            throw new \Exception('该第三方账号已被其他用户绑定', 400);
        }

        $bindData = [
            'openid'        => $data['openid'],
            'unionid'       => $data['unionid'] ?? '',
            'nickname'      => $data['nickname'] ?? '',
            'avatar'        => $data['avatar'] ?? '',
            'gender'        => $data['gender'] ?? 0,
            'country'       => $data['country'] ?? '',
            'province'      => $data['province'] ?? '',
            'city'          => $data['city'] ?? '',
            'access_token'  => $data['access_token'] ?? '',
            'refresh_token' => $data['refresh_token'] ?? '',
        ];

        $this->thirdPartyDao->bind($memberId, $platform->value, $data['openid'], $bindData);
    }

    /**
     * 解绑第三方账号
     *
     * @throws \Exception
     */
    public function unbindThirdParty(int $platform): void
    {
        $platformEnum = ThirdPartyPlatform::tryFrom($platform);
        if (!$platformEnum) {
            throw new \Exception('无效的平台类型', 400);
        }

        $currentMember = Container::make(CurrentMember::class);
        $memberId = $currentMember->id();
        
        if (empty($memberId)) {
            throw new \Exception('用户未登录', 401);
        }

        $thirdParty = $this->thirdPartyDao->findByMemberAndPlatform($memberId, $platform);
        if (!$thirdParty) {
            throw new \Exception('未找到绑定记录', 404);
        }

        $this->thirdPartyDao->unbind($thirdParty);
    }

    /**
     * 生成第三方绑定二维码
     *
     * @throws \Exception
     */
    public function generateBindQrCode(array $data): array
    {
        if (empty($data['platform'])) {
            throw new \Exception('平台不能为空');
        }

        $platform = ThirdPartyPlatform::tryFrom((int)$data['platform']);
        if (!$platform) {
            throw new \Exception('无效的平台类型');
        }

        $currentMember = Container::make(CurrentMember::class);
        $memberId = $currentMember->id();
        
        if (empty($memberId)) {
            throw new \Exception('用户未登录', 401);
        }

        $sceneId = 'bind_' . $platform->value . '_' . $memberId . '_' . time() . '_' . mt_rand(1000, 9999);

        $bindUrl = $this->buildBindUrl($platform->value, $sceneId);

        $bindInfo = [
            'scene_id'   => $sceneId,
            'member_id'  => $memberId,
            'platform'   => $platform->value,
            'status'     => 'pending',
            'created_at' => time(),
        ];
        Redis::setex('third_party_bind:' . $sceneId, 300, json_encode($bindInfo));

        return [
            'scene_id'   => $sceneId,
            'qr_code'    => $bindUrl,
            'expires_in' => 300,
        ];
    }

    /**
     * 检查绑定二维码状态
     *
     * @throws \Exception
     */
    public function checkBindQrStatus(string $sceneId): array
    {
        if (empty($sceneId)) {
            throw new \Exception('场景ID不能为空', 400);
        }

        $cacheKey = 'third_party_bind:' . $sceneId;
        $bindInfo = Redis::get($cacheKey);

        if (!$bindInfo) {
            throw new \Exception('二维码已过期或不存在', 400);
        }

        $bindInfo = json_decode($bindInfo, true);

        return [
            'scene_id' => $sceneId,
            'status'   => $bindInfo['status'] ?? 'pending',
            'platform' => $bindInfo['platform'] ?? 0,
        ];
    }

    /**
     * 构建绑定URL
     */
    private function buildBindUrl(int $platform, string $sceneId): string
    {
        $baseUrl = config('app.url', 'http://localhost');

        $bindUrls = [
            1 => 'https://graph.qq.com/oauth2.0/show?which=Login&display=pc&response_type=code&client_id=' . config('third_party.qq.app_id', '') . '&redirect_uri=' . urlencode($baseUrl . '/api/auth/qq/callback?scene=' . $sceneId),
            2 => 'https://open.weixin.qq.com/connect/qrconnect?appid=' . config('third_party.wechat.app_id', '') . '&redirect_uri=' . urlencode($baseUrl . '/api/auth/wechat/callback?scene=' . $sceneId) . '&response_type=code&scope=snsapi_login',
            3 => 'https://api.weibo.com/oauth2/authorize?client_id=' . config('third_party.weibo.app_id', '') . '&response_type=code&redirect_uri=' . urlencode($baseUrl . '/api/auth/weibo/callback?scene=' . $sceneId),
            4 => 'https://open.douyin.com/oauth2/authorize?client_key=' . config('third_party.douyin.app_id', '') . '&response_type=code&redirect_uri=' . urlencode($baseUrl . '/api/auth/douyin/callback?scene=' . $sceneId) . '&scope=user_info',
        ];

        $authUrl = $bindUrls[$platform] ?? '';
        
        if (empty($authUrl)) {
            return '';
        }
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($authUrl);
    }

    /**
     * 处理QQ回调
     */
    public function handleQqCallback(string $scene, string $code): void
    {
        $bindInfo = $this->getBindInfo($scene);
        if (!$bindInfo) {
            throw new \Exception('二维码已过期或不存在', 400);
        }

        $userInfo = $this->getQqUserInfo($code);
        if (!$userInfo) {
            throw new \Exception('获取QQ用户信息失败', 400);
        }

        $this->saveThirdPartyBind($bindInfo['member_id'], ThirdPartyPlatform::QQ->value, $userInfo);
        $this->updateBindStatus($scene, 'success');
    }

    /**
     * 处理微信回调
     */
    public function handleWechatCallback(string $scene, string $code): void
    {
        $bindInfo = $this->getBindInfo($scene);
        if (!$bindInfo) {
            throw new \Exception('二维码已过期或不存在', 400);
        }

        $userInfo = $this->getWechatUserInfo($code);
        if (!$userInfo) {
            throw new \Exception('获取微信用户信息失败', 400);
        }

        $this->saveThirdPartyBind($bindInfo['member_id'], ThirdPartyPlatform::WECHAT->value, $userInfo);
        $this->updateBindStatus($scene, 'success');
    }

    /**
     * 处理微博回调
     */
    public function handleWeiboCallback(string $scene, string $code): void
    {
        $bindInfo = $this->getBindInfo($scene);
        if (!$bindInfo) {
            throw new \Exception('二维码已过期或不存在', 400);
        }

        $userInfo = $this->getWeiboUserInfo($code);
        if (!$userInfo) {
            throw new \Exception('获取微博用户信息失败', 400);
        }

        $this->saveThirdPartyBind($bindInfo['member_id'], ThirdPartyPlatform::WEIBO->value, $userInfo);
        $this->updateBindStatus($scene, 'success');
    }

    /**
     * 处理抖音回调
     */
    public function handleDouyinCallback(string $scene, string $code): void
    {
        $bindInfo = $this->getBindInfo($scene);
        if (!$bindInfo) {
            throw new \Exception('二维码已过期或不存在', 400);
        }

        $userInfo = $this->getDouyinUserInfo($code);
        if (!$userInfo) {
            throw new \Exception('获取抖音用户信息失败', 400);
        }

        $this->saveThirdPartyBind($bindInfo['member_id'], ThirdPartyPlatform::DOUYIN->value, $userInfo);
        $this->updateBindStatus($scene, 'success');
    }

    /**
     * 获取绑定信息
     */
    private function getBindInfo(string $scene): ?array
    {
        $cacheKey = 'third_party_bind:' . $scene;
        $bindInfo = Redis::get($cacheKey);
        if (!$bindInfo) {
            return null;
        }
        return json_decode($bindInfo, true);
    }

    /**
     * 更新绑定状态
     */
    private function updateBindStatus(string $scene, string $status): void
    {
        $cacheKey = 'third_party_bind:' . $scene;
        $bindInfo = Redis::get($cacheKey);
        
        if ($bindInfo) {
            $bindInfo = json_decode($bindInfo, true);
            $bindInfo['status'] = $status;
            Redis::setex($cacheKey, 300, json_encode($bindInfo));
        }
    }

    /**
     * 保存第三方绑定信息
     */
    private function saveThirdPartyBind(int $memberId, int $platform, array $userInfo): void
    {
        $bindData = [
            'openid'        => $userInfo['openid'] ?? '',
            'unionid'       => $userInfo['unionid'] ?? '',
            'nickname'      => $userInfo['nickname'] ?? '',
            'avatar'        => $userInfo['avatar'] ?? '',
            'gender'        => $userInfo['gender'] ?? 0,
            'country'       => $userInfo['country'] ?? '',
            'province'      => $userInfo['province'] ?? '',
            'city'          => $userInfo['city'] ?? '',
            'access_token'  => $userInfo['access_token'] ?? '',
            'refresh_token' => $userInfo['refresh_token'] ?? '',
        ];

        $this->thirdPartyDao->bind($memberId, $platform, $bindData['openid'], $bindData);
    }

    /**
     * 获取QQ用户信息
     */
    private function getQqUserInfo(string $code): ?array
    {
        $appId = config('third_party.qq.app_id', '');
        $appSecret = config('third_party.qq.app_secret', '');
        $redirectUri = config('third_party.qq.redirect_uri', '');

        if (empty($appId) || empty($appSecret)) {
            throw new \Exception('QQ配置不完整', 400);
        }

        $tokenUrl = "https://graph.qq.com/oauth2.0/token";
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $tokenResponse = $this->httpGet($tokenUrl, $params);
        if (strpos($tokenResponse, 'access_token') === false) {
            return null;
        }

        parse_str($tokenResponse, $tokenData);
        $accessToken = $tokenData['access_token'] ?? '';
        $openIdUrl = "https://graph.qq.com/oauth2.0/me?access_token={$accessToken}";

        $openIdResponse = $this->httpGet($openIdUrl);
        if (strpos($openIdResponse, 'openid') === false) {
            return null;
        }

        $openIdData = json_decode(str_replace('callback(', '', str_replace(');', '', $openIdResponse)), true);
        $openid = $openIdData['openid'] ?? '';

        $userInfoUrl = "https://graph.qq.com/user/get_user_info";
        $userParams = [
            'access_token' => $accessToken,
            'oauth_consumer_key' => $appId,
            'openid' => $openid,
        ];

        $userResponse = $this->httpGet($userInfoUrl, $userParams);
        $userData = json_decode($userResponse, true);

        if ($userData['ret'] != 0) {
            return null;
        }

        return [
            'openid' => $openid,
            'unionid' => '',
            'nickname' => $userData['nickname'] ?? '',
            'avatar' => $userData['figureurl_qq_2'] ?? '',
            'gender' => $this->convertGender($userData['gender'] ?? ''),
            'country' => '',
            'province' => $userData['province'] ?? '',
            'city' => $userData['city'] ?? '',
            'access_token' => $accessToken,
            'refresh_token' => '',
        ];
    }

    /**
     * 获取微信用户信息
     */
    private function getWechatUserInfo(string $code): ?array
    {
        $appId = config('third_party.wechat.app_id', '');
        $appSecret = config('third_party.wechat.app_secret', '');

        if (empty($appId) || empty($appSecret)) {
            throw new \Exception('微信配置不完整', 400);
        }

        $tokenUrl = "https://api.weixin.qq.com/sns/oauth2/access_token";
        $params = [
            'appid' => $appId,
            'secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];

        $tokenResponse = $this->httpGet($tokenUrl, $params);
        $tokenData = json_decode($tokenResponse, true);

        if (isset($tokenData['errcode'])) {
            return null;
        }

        $accessToken = $tokenData['access_token'] ?? '';
        $openid = $tokenData['openid'] ?? '';
        $unionid = $tokenData['unionid'] ?? '';

        $userInfoUrl = "https://api.weixin.qq.com/sns/userinfo";
        $userParams = [
            'access_token' => $accessToken,
            'openid' => $openid,
        ];

        $userResponse = $this->httpGet($userInfoUrl, $userParams);
        $userData = json_decode($userResponse, true);

        if (isset($userData['errcode'])) {
            return null;
        }

        return [
            'openid' => $openid,
            'unionid' => $unionid,
            'nickname' => $userData['nickname'] ?? '',
            'avatar' => $userData['headimgurl'] ?? '',
            'gender' => $userData['sex'] ?? 0,
            'country' => $userData['country'] ?? '',
            'province' => $userData['province'] ?? '',
            'city' => $userData['city'] ?? '',
            'access_token' => $accessToken,
            'refresh_token' => $tokenData['refresh_token'] ?? '',
        ];
    }

    /**
     * 获取微博用户信息
     */
    private function getWeiboUserInfo(string $code): ?array
    {
        $appId = config('third_party.weibo.app_id', '');
        $appSecret = config('third_party.weibo.app_secret', '');
        $redirectUri = config('third_party.weibo.redirect_uri', '');

        if (empty($appId) || empty($appSecret)) {
            throw new \Exception('微博配置不完整', 400);
        }

        $tokenUrl = "https://api.weibo.com/oauth2/access_token";
        $params = [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $tokenResponse = $this->httpPost($tokenUrl, $params);
        $tokenData = json_decode($tokenResponse, true);

        if (isset($tokenData['error'])) {
            return null;
        }

        $accessToken = $tokenData['access_token'] ?? '';
        $uid = $tokenData['uid'] ?? '';

        $userInfoUrl = "https://api.weibo.com/2/users/show.json";
        $userParams = [
            'access_token' => $accessToken,
            'uid' => $uid,
        ];

        $userResponse = $this->httpGet($userInfoUrl, $userParams);
        $userData = json_decode($userResponse, true);

        if (isset($userData['error'])) {
            return null;
        }

        return [
            'openid' => $uid,
            'unionid' => '',
            'nickname' => $userData['screen_name'] ?? '',
            'avatar' => $userData['avatar_large'] ?? '',
            'gender' => $this->convertGender($userData['gender'] ?? ''),
            'country' => '',
            'province' => $userData['province'] ?? '',
            'city' => $userData['city'] ?? '',
            'access_token' => $accessToken,
            'refresh_token' => '',
        ];
    }

    /**
     * 获取抖音用户信息
     */
    private function getDouyinUserInfo(string $code): ?array
    {
        $appId = config('third_party.douyin.app_id', '');
        $appSecret = config('third_party.douyin.app_secret', '');

        if (empty($appId) || empty($appSecret)) {
            throw new \Exception('抖音配置不完整', 400);
        }

        $tokenUrl = "https://open.douyin.com/oauth/access_token/";
        $params = [
            'client_key' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];

        $tokenResponse = $this->httpPost($tokenUrl, $params);
        $tokenData = json_decode($tokenResponse, true);

        if (isset($tokenData['error_code'])) {
            return null;
        }

        $accessToken = $tokenData['access_token'] ?? '';
        $openId = $tokenData['open_id'] ?? '';

        $userInfoUrl = "https://open.douyin.com/oauth/userinfo/";
        $userParams = [
            'access_token' => $accessToken,
            'open_id' => $openId,
        ];

        $userResponse = $this->httpGet($userInfoUrl, $userParams);
        $userData = json_decode($userResponse, true);

        if (isset($userData['error_code'])) {
            return null;
        }

        return [
            'openid' => $openId,
            'unionid' => $userData['union_id'] ?? '',
            'nickname' => $userData['nickname'] ?? '',
            'avatar' => $userData['avatar'] ?? '',
            'gender' => $userData['gender'] ?? 0,
            'country' => $userData['country'] ?? '',
            'province' => $userData['province'] ?? '',
            'city' => $userData['city'] ?? '',
            'access_token' => $accessToken,
            'refresh_token' => $tokenData['refresh_token'] ?? '',
        ];
    }

    /**
     * 转换性别
     */
    private function convertGender(string $gender): int
    {
        $genderMap = [
            '男' => 1,
            '女' => 2,
            'm' => 1,
            'f' => 2,
            'male' => 1,
            'female' => 2,
        ];

        return $genderMap[$gender] ?? 0;
    }

    /**
     * HTTP GET请求
     */
    private function httpGet(string $url, array $params = []): string
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response ?: '';
    }

    /**
     * HTTP POST请求
     */
    private function httpPost(string $url, array $params = []): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response ?: '';
    }
}
