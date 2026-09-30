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
 * Official Website: https://madong.tech
 */

namespace core\business\install\traits;

use app\model\system\admin\Admin;
use app\model\system\admin\AdminType;
use app\model\system\admin\AdminTypeRel;

/**
 * 管理员创建 Trait
 */
trait AdminTrait
{

    /**
     * 创建管理员
     *
     * @param array $adminParams 管理员参数
     * @param bool  $enableTenant 是否启用多租户（启用时设置平台管理员类型）
     */
    public function createAdmin(array $adminParams, bool $enableTenant = false): void
    {
        $adminTable = $this->table((new Admin())->getTable());
        $pdo = $this->getPdo();
        
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `{$adminTable}`");
        $result = $stmt->fetch();
        $adminExists = $result && $result['cnt'] > 0;

        if (!$adminExists) {
            $username = $adminParams['username'] ?? 'admin';
            $password = $adminParams['password'] ?? '123456';
            $email = $adminParams['email'] ?? 'admin@example.com';

            $this->insert($adminTable, [
                'id' => 1,
                'user_name' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'real_name' => '超级管理员',
                'nick_name' => '超级管理员',
                'email' => $email,
                'is_super' => 1,
                'enabled' => 1,
                'created_at' => $this->currentTime,
                'updated_at' => $this->currentTime,
            ]);
        }

        // 启用了多租户时，给管理员添加"平台管理员"账号类型
        // 注意：无论管理员是否已存在（重新安装场景）都需要执行此逻辑
        if ($enableTenant) {
            $this->ensurePlatformAdminType($pdo);
        }
    }

    /**
     * 确保管理员拥有平台管理员类型
     */
    private function ensurePlatformAdminType(\PDO $pdo): void
    {
        $typeTable = $this->table((new AdminType())->getTable());
        $relTable = $this->table((new AdminTypeRel())->getTable());

        // 1. 查找或创建 platform 类型
        $stmt = $pdo->query("SELECT `id` FROM `{$typeTable}` WHERE `code` = 'platform' LIMIT 1");
        $typeRow = $stmt->fetch();

        if ($typeRow) {
            $typeId = $typeRow['id'];
        } else {
            $now = time();
            $typeId = $this->insertGetId($typeTable, [
                'code'       => 'platform',
                'name'       => '平台管理员',
                'sort'       => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. 插入管理员-平台类型关联（admin_id=1 为超级管理员）
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `{$relTable}` WHERE `admin_id` = 1 AND `type_id` = {$typeId}");
        $relRow = $stmt->fetch();
        if ($relRow && $relRow['cnt'] == 0) {
            $this->insert($relTable, [
                'admin_id' => 1,
                'type_id'  => $typeId,
            ]);
        }
    }
}
