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
use app\enum\member\WithdrawAccountType;
use app\model\member\MemberWithdrawAccount;

/**
 * 会员提现账号数据访问对象
 */
class MemberWithdrawAccountDao extends BaseDao
{
    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return MemberWithdrawAccount::class;
    }

    /**
     * 设置默认账号
     */
    public function setDefault(MemberWithdrawAccount $account): bool
    {
        // 先将该会员的所有提现账号设置为非默认
        $this->query()
            ->where('member_id', $account->member_id)
            ->update(['is_default' => 0]);

        // 将当前账号设置为默认
        $account->is_default = 1;
        return $account->save();
    }

    /**
     * 获取会员默认提现账号
     */
    public function getDefaultAccount(int $memberId): ?MemberWithdrawAccount
    {
        return $this->query()
            ->where('member_id', $memberId)
            ->where('is_default', 1)
            ->where('enabled', EnabledStatus::ENABLED->value)
            ->first();
    }

    /**
     * 获取会员提现账号列表
     */
    public function getMemberAccounts(int $memberId): array
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
     * 创建或更新提现账号
     */
    public function saveAccount(int $memberId, array $data): MemberWithdrawAccount
    {
        $accountId = $data['id'] ?? 0;

        if ($accountId) {
            // 更新账号
            $account = $this->find($accountId);
            if (!$account || $account->member_id != $memberId) {
                throw new \Exception('账号不存在或无权限');
            }
        } else {
            // 创建新账号
            $account = $this->getModel();
            $account->member_id = $memberId;
            $account->enabled = EnabledStatus::ENABLED->value;
        }

        // 填充数据
        $fillable = [
            'type',
            'bank_name',
            'account_name',
            'account_number',
            'branch_name',
        ];

        foreach ($fillable as $field) {
            if (isset($data[$field])) {
                $account->{$field} = $data[$field];
            }
        }

        // 设置默认账号
        if (isset($data['is_default']) && $data['is_default'] == 1) {
            // 先将其他账号设置为非默认
            $this->query()
                ->where('member_id', $memberId)
                ->update(['is_default' => 0]);
            $account->is_default = 1;
        } elseif (!$accountId) {
            // 新账号默认为非默认
            $account->is_default = 0;
        }

        $account->save();
        return $account;
    }

    /**
     * 删除提现账号
     */
    public function deleteAccount(MemberWithdrawAccount $account): bool
    {
        // 检查是否有关联的提现记录
        $withdrawCount = $account->withdraws()->count();
        if ($withdrawCount > 0) {
            throw new \Exception('该账号有关联的提现记录，无法删除');
        }

        $account->enabled = EnabledStatus::DISABLED->value;
        return $account->save();
    }

    /**
     * 验证账号数据
     */
    public function validateAccount(array $data): array
    {
        $errors = [];

        if (empty($data['type'])) {
            $errors[] = '账号类型不能为空';
        }

        if (empty($data['bank_name'])) {
            $errors[] = '银行名称不能为空';
        }

        if (empty($data['account_name'])) {
            $errors[] = '账户姓名不能为空';
        }

        if (empty($data['account_number'])) {
            $errors[] = '账号不能为空';
        }

        // 根据账号类型进行特殊验证
        if (isset($data['type'])) {
            switch ($data['type']) {
                case WithdrawAccountType::BANK->value:
                    if (empty($data['branch_name'])) {
                        $errors[] = '开户行不能为空';
                    }
                    break;
                case WithdrawAccountType::ALIPAY->value:
                    // 支付宝账号验证
                    if (!preg_match('/^1[3-9]\d{9}$|^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $data['account_number'])) {
                        $errors[] = '支付宝账号格式不正确（请输入手机号或邮箱）';
                    }
                    break;
                case WithdrawAccountType::WECHAT->value:
                    // 微信账号验证
                    if (!preg_match('/^[a-zA-Z0-9_-]{6,20}$/', $data['account_number'])) {
                        $errors[] = '微信账号格式不正确';
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * 获取会员账号数量
     */
    public function getMemberAccountCount(int $memberId): int
    {
        return $this->count([
            'member_id' => $memberId,
            'enabled' => EnabledStatus::ENABLED->value
        ]);
    }

    /**
     * 获取账号使用次数
     */
    public function getUsageCount(MemberWithdrawAccount $account): int
    {
        return $account->withdraws()->count();
    }
}