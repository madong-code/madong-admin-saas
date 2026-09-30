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
namespace app\service\api\member;

use app\api\CurrentMember;
use app\dao\member\MemberThirdPartyDao;
use app\enum\member\ThirdPartyPlatform;
use core\foundation\base\BaseService;
use support\Container;
use support\Redis;

/**
 * 会员绑定服务
 */
class MemberBindService extends BaseService
{
    /**
     * 构造方法
     */
    public function __construct(MemberThirdPartyDao $dao)
    {
        $this->dao = $dao;
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
        $thirdParties = $this->dao->getMemberThirdParties($memberId);
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

        if ($this->dao->isBound($platform->value, $data['openid'])) {
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

        $this->dao->bind($memberId, $platform->value, $data['openid'], $bindData);
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

        $thirdParty = $this->dao->findByMemberAndPlatform($memberId, $platform);
        if (!$thirdParty) {
            throw new \Exception('未找到绑定记录', 404);
        }

        $this->dao->unbind($thirdParty);
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
        // 使用第三方二维码生成API
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($authUrl);
    }
}
