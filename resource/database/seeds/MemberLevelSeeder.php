<?php
declare(strict_types=1);
namespace resource\database\seeds;

use app\model\member\MemberLevel;
use core\io\uuid\Snowflake;
use Illuminate\Database\Seeder;
use support\Db;

class MemberLevelSeeder extends Seeder
{
    public function run(): void
    {
        // 多租户模式（非 single）跳过租户业务数据
        if (config('tenant.enable', false)) {
            return;
        }

        $data = [
            [
                'name'        => '黄金',
                'level'       => 1,
                'min_points'  => 0,
                'max_points'  => 99,
                'discount'    => 0.00,
                'icon'        => 'ant-design:account-book-filled',
                'color'       => null,
                'description' => '',
                'enabled'     => 1,
                'created_at'  => time(),
                'updated_at'  => time(),
            ],
            [
                'name'        => '铂金',
                'level'       => 2,
                'min_points'  => 100,
                'max_points'  => 999,
                'discount'    => 9.80,
                'icon'        => 'ant-design:android-outlined',
                'color'       => null,
                'description' => '',
                'enabled'     => 1,
                'created_at'  => time(),
                'updated_at'  => time(),
            ],
            [
                'name'        => '钻石',
                'level'       => 3,
                'min_points'  => 1000,
                'max_points'  => 4999,
                'discount'    => 9.00,
                'icon'        => 'ant-design:ant-cloud-outlined',
                'color'       => null,
                'description' => '',
                'enabled'     => 1,
                'created_at'  => time(),
                'updated_at'  => time(),
            ],
            [
                'name'        => '星耀',
                'level'       => 4,
                'min_points'  => 5000,
                'max_points'  => 9999,
                'discount'    => 8.00,
                'icon'        => 'ant-design:ant-cloud-outlined',
                'color'       => null,
                'description' => '',
                'enabled'     => 1,
                'created_at'  => time(),
                'updated_at'  => time(),
            ],
            [
                'name'        => '王者',
                'level'       => 5,
                'min_points'  => 10000,
                'max_points'  => 9999999,
                'discount'    => 7.00,
                'icon'        => 'ant-design:ant-cloud-outlined',
                'color'       => null,
                'description' => '',
                'enabled'     => 1,
                'created_at'  => time(),
                'updated_at'  => time(),
            ],
        ];

        // 清空表数据
        Db::table('member_level')->truncate();
        // 插入数据
        foreach ($data as $item) {
            $item['id'] = Snowflake::generate();
            MemberLevel::create($item);
        }
        echo "Member level seed completed successfully!\n";
    }
}
