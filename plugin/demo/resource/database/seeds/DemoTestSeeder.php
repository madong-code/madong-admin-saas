<?php

/**
 * demo插件 - demo_test 测试数据填充
 * 不依赖 use 导入，使用完全限定名避免 require 上下文问题
 */
class DemoTestSeeder
{
    public function run(): void
    {
        $db = \support\Db::connection();
        $table = 'demo_demo_test'; // Db::table() 会自动加表前缀

        $count = $db->table($table)->count();
        if ($count > 0) {
            return;
        }

        $now = time();
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'id'          => $now * 100 + $i,
                'name'        => '测试数据' . $i,
                'description' => '这是第' . $i . '条测试数据',
                'category_id' => rand(1, 5),
                'status'      => 1,
                'sort'        => $i,
                'deleted_at'  => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        $db->table($table)->insert($rows);
    }
}
