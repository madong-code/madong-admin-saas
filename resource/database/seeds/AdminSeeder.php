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

namespace resource\database\seeds;

use app\model\system\admin\Admin;
use app\model\system\admin\AdminType;
use app\model\system\admin\AdminTypeRel;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    private ?array $adminParams = null;

    /**
     * 设置管理员参数
     */
    public function setAdminParams(array $params): void
    {
        $this->adminParams = $params;
    }

    public function run(): void
    {
        $isSingle = !config('tenant.enable', false);

        if ($isSingle) {
            // single 模式：仅 sys_admin（1 表）
            $this->runSingle();
        } else {
            // field/database 模式：sys_admin + sys_admin_type + sys_admin_type_rel（3 表）
            $this->runMultiTenant();
        }
    }

    private function runSingle(): void
    {
        Admin::truncate();

        $now = time();

        $existingAdmin = Admin::find(1);
        if ($existingAdmin) {
            if ($this->adminParams) {
                $existingAdmin->user_name = $this->adminParams['username'];
                $existingAdmin->password = password_hash($this->adminParams['password'], PASSWORD_DEFAULT);
                $existingAdmin->real_name = '超级管理员';
                $existingAdmin->nick_name = '超级管理员';
                $existingAdmin->is_super = 1;
                $existingAdmin->enabled = 1;
                $existingAdmin->email = $this->adminParams['email'] ?? 'admin@example.com';
                $existingAdmin->save();
            }
        } else {
            $username = $this->adminParams['username'] ?? 'admin';
            $password = $this->adminParams['password'] ?? '123456';

            Admin::create([
                'id'         => 1,
                'user_name'  => $username,
                'real_name'  => '超级管理员',
                'nick_name'  => '超级管理员',
                'password'   => password_hash($password, PASSWORD_DEFAULT),
                'email'      => 'admin@example.com',
                'avatar'     => '',
                'is_super'   => 1,
                'enabled'    => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function runMultiTenant(): void
    {
        Admin::truncate();
        AdminType::truncate();
        AdminTypeRel::truncate();

        $now = time();
        $username = $this->adminParams['username'] ?? 'admin';
        $password = $this->adminParams['password'] ?? '123456';

        // 1. 创建管理员
        $admin = Admin::find(1);
        if (!$admin) {
            $admin = Admin::create([
                'id'         => 1,
                'user_name'  => $username,
                'real_name'  => '超级管理员',
                'nick_name'  => '超级管理员',
                'password'   => password_hash($password, PASSWORD_DEFAULT),
                'email'      => 'admin@example.com',
                'avatar'     => '',
                'is_super'   => 1,
                'enabled'    => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. 创建平台管理员类型
        $type = AdminType::create([
            'code'       => 'platform',
            'name'       => '平台管理员',
            'sort'       => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. 创建关联
        AdminTypeRel::create([
            'admin_id'   => $admin->id,
            'type_id'    => $type->id,
            'created_at' => $now,
        ]);
    }
}
