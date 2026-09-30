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
use app\dao\member\MemberDao;
use app\dao\member\MemberThirdPartyDao;
use app\model\member\Member;
use app\model\member\MemberAddress;
use app\enum\common\EnabledStatus;
use core\foundation\base\BaseService;
use core\security\jwt\JwtToken;
use support\Container;
use support\Redis;

/**
 * 会员用户服务
 */
class MemberService extends BaseService
{

    /**
     * 构造方法
     */
    public function __construct(MemberDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 更新会员信息
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function updateInfo(array $data): void
    {
        $currentMember = Container::make(CurrentMember::class);
        $id            = $currentMember->id();

        if (empty($id)) {
            throw new \Exception('用户未登录', 401);
        }

        $model = $this->dao->get($id);
        if (!$model) {
            throw new \Exception('用户不存在', 401);
        }

        $allowedFields = ['nickname', 'avatar', 'gender', 'birthday','bio'];
        $updateData    = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            throw new \Exception('没有可更新的数据', 400);
        }

        $model->fill($updateData);
        
        if (!$model->isDirty()) {
            throw new \Exception('数据没有变化', 400);
        }
        
        $model->save();
    }

    /**
     * 绑定会员手机号
     */
    public function bindPhone(array $data): array
    {
        if (empty($data['phone']) || empty($data['verify_code'])) {
            throw new \Exception('手机号和验证码不能为空', 400);
        }

        // 验证短信验证码
        $this->verifySmsCode($data['phone'], $data['verify_code']);

        $memberId = $this->getCurrentMemberId();
        $member   = Member::find($memberId);

        if (!$member) {
            throw new \Exception('用户不存在', 401);
        }

        // 检查手机号是否已被其他用户绑定
        $existingMember = Member::where('phone', $data['phone'])
            ->where('id', '!=', $memberId)
            ->first();

        if ($existingMember) {
            throw new \Exception('手机号已被其他用户绑定', 400);
        }

        // 更新手机号
        $member->phone = $data['phone'];
        $member->save();

        return [
            'code' => 200,
            'msg'  => '绑定成功',
        ];
    }

    /**
     * 修改密码
     *
     * @throws \Exception
     */
    public function changePassword(array $data): void
    {
        if (empty($data['old_password']) || empty($data['new_password'])) {
            throw new \Exception('旧密码和新密码不能为空', 400);
        }

        $currentMember = Container::make(CurrentMember::class);
        $id            = $currentMember->id();

        if (empty($id)) {
            throw new \Exception('用户未登录', 401);
        }

        $model = $this->dao->get($id);
        if (!$model) {
            throw new \Exception('用户不存在', 401);
        }

        if (!$model->verifyPassword($data['old_password'])) {
            throw new \Exception('旧密码错误', 400);
        }

        $model->password = $data['new_password'];
        $model->save();
    }

    /**
     * 获取地址列表
     */
    public function getAddressList(): array
    {
        $memberId  = $this->getCurrentMemberId();
        $addresses = MemberAddress::where('member_id', $memberId)
            ->where('enabled', EnabledStatus::ENABLED->value)
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return $addresses->toArray();
    }

    /**
     * 创建地址
     */
    public function createAddress(array $data): array
    {
        if (empty($data['name']) || empty($data['phone']) || empty($data['province']) ||
            empty($data['city']) || empty($data['district']) || empty($data['address'])) {
            throw new \Exception('请填写完整的地址信息', 400);
        }

        $memberId = $this->getCurrentMemberId();

        // 如果设置为默认地址，取消其他默认地址
        if ($data['is_default'] ?? false) {
            MemberAddress::where('member_id', $memberId)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $addressData = [
            'member_id'  => $memberId,
            'name'       => $data['name'],
            'phone'      => $data['phone'],
            'province'   => $data['province'],
            'city'       => $data['city'],
            'district'   => $data['district'],
            'address'    => $data['address'],
            'is_default' => $data['is_default'] ?? false,
            'enabled'    => EnabledStatus::ENABLED->value,
        ];

        $address = MemberAddress::create($addressData);

        return ['id' => $address->id];
    }

    /**
     * 更新地址
     */
    public function updateAddress(int $id, array $data): array
    {
        $memberId = $this->getCurrentMemberId();
        $address  = MemberAddress::where('id', $id)
            ->where('member_id', $memberId)
            ->first();

        if (!$address) {
            throw new \Exception('地址不存在', 404);
        }

        // 如果设置为默认地址，取消其他默认地址
        if ($data['is_default'] ?? false) {
            MemberAddress::where('member_id', $memberId)
                ->where('id', '!=', $id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        // 允许更新的字段
        $allowedFields = ['name', 'phone', 'province', 'city', 'district', 'address', 'is_default'];
        $updateData    = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            throw new \Exception('没有可更新的数据', 400);
        }

        $address->update($updateData);

        return [];
    }

    /**
     * 删除地址
     */
    public function deleteAddress(int $id): array
    {
        $memberId = $this->getCurrentMemberId();
        $address  = MemberAddress::where('id', $id)
            ->where('member_id', $memberId)
            ->first();

        if (!$address) {
            throw new \Exception('地址不存在', 404);
        }

        // 软删除
        $address->enabled = EnabledStatus::DISABLED->value;
        $address->save();

        return [];
    }

    /**
     * 验证短信验证码
     */
    private function verifySmsCode(string $mobile, string $code): void
    {
        $cacheKey   = 'sms_code:' . $mobile;
        $cachedCode = Redis::get($cacheKey);

        if (!$cachedCode || $cachedCode !== $code) {
            throw new \Exception('验证码错误或已过期', 400);
        }

        // 验证成功后删除验证码
        Redis::del($cacheKey);
    }

    /**
     * 获取当前用户ID（通过 JWT 解析 token）
     */
    private function getCurrentMemberId(): int
    {
        $request = \request();
        $auth    = $request->header('Authorization', '');

        if (empty($auth) || $auth === 'undefined') {
            throw new \Exception('未登录', 401);
        }

        // 去除 Bearer 前缀
        $token = $auth;
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        try {
            $payload = (new JwtToken())->parse($token);
            $mid     = $payload['id'] ?? null;
            if (empty($mid)) {
                throw new \Exception('登录已过期', 401);
            }
            return (int)$mid;
        } catch (\Throwable $e) {
            throw new \Exception('登录已过期', 401);
        }
    }

    /**
     * 上传头像
     */
    public function uploadAvatar(\Webman\Http\UploadFile $file): array
    {
        $memberId = $this->getCurrentMemberId();
        
        // 验证文件类型
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getUploadMimeType(), $allowedTypes)) {
            throw new \Exception('只支持jpg、png、gif、webp格式的图片', 400);
        }
        
        // 验证文件大小（最大2MB）
        $maxSize = 2 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            throw new \Exception('图片大小不能超过2MB', 400);
        }
        
        // 生成文件名
        $extension = pathinfo($file->getUploadName(), PATHINFO_EXTENSION);
        $filename = 'avatar_' . $memberId . '_' . time() . '.' . $extension;
        
        // 保存文件
        $uploadPath = public_path() . '/uploads/avatar/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        $file->moveTo($uploadPath . $filename);
        
        // 生成访问URL
        $url = '/uploads/avatar/' . $filename;
        
        // 更新会员头像
        $member = Member::find($memberId);
        if ($member) {
            $member->avatar = $url;
            $member->save();
        }
        
        return [
            'url' => $url,
            'filename' => $filename
        ];
    }
    
    /**
     * 获取活跃用户
     */
    public function getActiveUsers(int $limit = 10): array
    {
        $member = $this->dao->getActiveUsers($limit);
        return $member->map(function ($item) {
            return [
                'id'          => $item->id,
                'username'    => $item->username,
                'nickname'    => $item->nickname,
                'avatar'      => $item->avatar,
                'intro'       => $item->intro ?? "",
                'last_active' => $item->last_time,
            ];
        })->toArray();
    }

    /**
     * 检查用户是否是管理员
     * 暂时返回 false，只有问题作者可以设置最佳答案
     */
    public function isAdmin(int|string $userId): bool
    {
        return false;
    }

    /**
     * 更新会员邮箱
     *
     * @param array $data { email, verify_code }
     * @throws \Exception
     */
    public function updateEmail(array $data): void
    {
        if (empty($data['email']) || empty($data['verify_code'])) {
            throw new \Exception('邮箱和验证码不能为空', 400);
        }

        // 验证邮箱验证码
        $this->verifySmsCode($data['email'], $data['verify_code']);

        $memberId = $this->getCurrentMemberId();
        $member   = Member::find($memberId);

        if (!$member) {
            throw new \Exception('用户不存在', 401);
        }

        // 检查邮箱是否已被其他用户绑定
        $existingMember = Member::where('email', $data['email'])
            ->where('id', '!=', $memberId)
            ->first();

        if ($existingMember) {
            throw new \Exception('邮箱已被其他用户绑定', 400);
        }

        $member->email = $data['email'];
        $member->save();
    }
}