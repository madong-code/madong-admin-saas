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

use core\foundation\base\BaseDao;
use app\enum\common\EnabledStatus;
use app\model\member\MemberAddress;


/**
 * 会员地址数据访问对象
 */
class MemberAddressDao extends BaseDao
{
    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return MemberAddress::class;
    }

    /**
     * 设置默认地址
     *
     * @throws \Exception
     */
    public function setDefault(MemberAddress $address): bool
    {
        // 先将该会员的所有地址设置为非默认
        $this->query()->where('member_id', $address->member_id)->update(['is_default' => 0]);

        // 将当前地址设置为默认
        $address->is_default = 1;
        return $address->save();
    }

    /**
     * 获取会员默认地址
     */
    public function getDefaultAddress(int $memberId): ?MemberAddress
    {
        return $this->query()
            ->where('member_id', $memberId)
            ->where('is_default', 1)
            ->where('enabled', EnabledStatus::ENABLED->value)
            ->first();
    }

    /**
     * 获取会员地址列表
     */
    public function getMemberAddresses(int $memberId): array
    {
        return $this->query()
            ->where('member_id', $memberId)
            ->where('enabled', EnabledStatus::ENABLED->value)
            ->orderBy('is_default', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 创建或更新地址
     */
    public function saveAddress(int $memberId, array $data): MemberAddress
    {
        $addressId = $data['id'] ?? 0;

        if ($addressId) {
            // 更新地址
            $address = $this->find($addressId);
            if (!$address || $address->member_id != $memberId) {
                throw new \Exception('地址不存在或无权限');
            }
        } else {
            // 创建新地址
            $address = $this->getModel();
            $address->member_id = $memberId;
            $address->enabled = EnabledStatus::ENABLED->value;
        }

        // 填充数据
        $fillable = [
            'name',
            'phone',
            'province',
            'city',
            'district',
            'address',
            'zipcode',
        ];

        foreach ($fillable as $field) {
            if (isset($data[$field])) {
                $address->{$field} = $data[$field];
            }
        }

        // 设置默认地址
        if (isset($data['is_default']) && $data['is_default'] == 1) {
            // 先将其他地址设置为非默认
            $this->query()
                ->where('member_id', $memberId)
                ->update(['is_default' => 0]);
            $address->is_default = 1;
        } elseif (!$addressId) {
            // 新地址默认为非默认
            $address->is_default = 0;
        }

        $address->save();
        return $address;
    }

    /**
     * 删除地址
     */
    public function deleteAddress(MemberAddress $address): bool
    {
        $address->enabled = EnabledStatus::DISABLED->value;
        return $address->save();
    }

    /**
     * 验证地址数据
     */
    public function validateAddress(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = '收货人姓名不能为空';
        }

        if (empty($data['phone'])) {
            $errors[] = '联系电话不能为空';
        } elseif (!preg_match('/^1[3-9]\d{9}$/', $data['phone'])) {
            $errors[] = '联系电话格式不正确';
        }

        if (empty($data['province'])) {
            $errors[] = '省份不能为空';
        }

        if (empty($data['city'])) {
            $errors[] = '城市不能为空';
        }

        if (empty($data['district'])) {
            $errors[] = '区县不能为空';
        }

        if (empty($data['address'])) {
            $errors[] = '详细地址不能为空';
        }

        return $errors;
    }

    /**
     * 获取会员地址数量
     */
    public function getMemberAddressCount(int $memberId): int
    {
        return $this->count([
            'member_id' => $memberId,
            'enabled' => EnabledStatus::ENABLED->value
        ]);
    }
}