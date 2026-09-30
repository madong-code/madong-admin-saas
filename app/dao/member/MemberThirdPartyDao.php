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
namespace app\dao\member;

use app\enum\common\EnabledStatus;
use core\foundation\base\BaseDao;
use app\model\member\MemberThirdParty;
use app\model\member\Member;

/**
 * 会员第三方登录DAO
 */
class MemberThirdPartyDao extends BaseDao
{
    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return MemberThirdParty::class;
    }

    /**
     * 绑定第三方账号
     *
     * @throws \Exception
     */
    public function bind(int|string $memberId, int $platform, string $openid, array $data = []): MemberThirdParty
    {
        // 检查是否已绑定
        $thirdParty = $this->findByMemberAndPlatform($memberId, $platform);

        if ($thirdParty) {
            // 更新绑定信息
            foreach ($data as $key => $value) {
                if (in_array($key, $thirdParty->fillable)) {
                    $thirdParty->$key = $value;
                }
            }
            $thirdParty->openid = $openid;
            $thirdParty->enabled = EnabledStatus::ENABLED->value;
            $thirdParty->save();
            return $thirdParty;
        }

        // 创建新绑定
        $thirdParty = new MemberThirdParty();
        $thirdParty->member_id = $memberId;
        $thirdParty->platform = $platform;
        $thirdParty->openid = $openid;
        
        foreach ($data as $key => $value) {
            if (in_array($key, $thirdParty->fillable)) {
                $thirdParty->$key = $value;
            }
        }
        
        $thirdParty->enabled = EnabledStatus::ENABLED->value;
        $thirdParty->save();

        return $thirdParty;
    }

    /**
     * 根据会员ID和平台查找第三方账号
     *
     * @throws \Exception
     */
    public function findByMemberAndPlatform(int|string $memberId, int $platform): ?MemberThirdParty
    {
        return $this->query()
            ->where('member_id', $memberId)
            ->where('platform', $platform)
            ->first();
    }

    /**
     * 根据第三方账号查找会员
     *
     * @throws \Exception
     */
    public function findMemberByThirdParty(int $platform, string $openid): ?Member
    {
        $thirdParty = $this->query()
            ->where('platform', $platform)
            ->where('openid', $openid)
            ->where('enabled', EnabledStatus::ENABLED->value)
            ->with('member')
            ->first();

        /** @var TYPE_NAME $thirdParty */
        return $thirdParty?->member;
    }

    /**
     * 根据unionid查找会员
     */
    public function findMemberByUnionId(string $unionid): ?Member
    {
        $thirdParty = $this->query()
            ->where('unionid', $unionid)
            ->where('enabled', EnabledStatus::ENABLED->value)
            ->with('member')
            ->first();

        /** @var TYPE_NAME $thirdParty */
        return $thirdParty?->member;
    }

    /**
     * 获取会员的第三方账号列表
     *
     * @throws \Exception
     */
    public function getMemberThirdParties(int|string $memberId): array
    {
        return $this->query()
            ->where('member_id', $memberId)
            ->where('enabled', EnabledStatus::ENABLED->value)
            ->get()
            ->toArray();
    }

    /**
     * 解绑第三方账号
     */
    public function unbind(MemberThirdParty $thirdParty): bool
    {
        $thirdParty->enabled = EnabledStatus::DISABLED->value;
        return $thirdParty->save();
    }

    /**
     * 检查第三方账号是否已绑定
     */
    public function isBound(int $platform, string $openid): bool
    {
        return $this->query()
            ->where('platform', $platform)
            ->where('openid', $openid)
            ->where('enabled', EnabledStatus::ENABLED->value)
            ->exists();
    }

    /**
     * 更新第三方登录令牌
     */
    public function updateToken(MemberThirdParty $thirdParty, string $accessToken, string $refreshToken = '', int $expiresIn = 0): bool
    {
        $thirdParty->access_token = $accessToken;
        if ($refreshToken) {
            $thirdParty->refresh_token = $refreshToken;
        }
        if ($expiresIn > 0) {
            $thirdParty->expires_at = date('Y-m-d H:i:s', time() + $expiresIn);
        }
        return $thirdParty->save();
    }
}
