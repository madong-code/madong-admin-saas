<?php
/**
 * 数据字典种子
 */

declare(strict_types=1);
namespace resource\database\seeds;

use app\model\system\dict\Dict;
use app\model\system\dict\DictItem;
use core\io\uuid\Snowflake;
use Illuminate\Database\Seeder;

class DictSeeder extends Seeder
{
    public function run(): void
    {
        // 清空表
        DictItem::truncate();
        Dict::truncate();

        $dict = include base_path('resource/data/dict/dict.php');

        // 插入字典组
        foreach ($dict as $group) {
            $dictId=Snowflake::generate();
            Dict::create([
                'id' => $dictId,
                'group_code' => $group['group_code'] ?? '',
                'name' => $group['name'] ?? '',
                'code' => $group['code'] ?? '',
                'sort' => $group['sort'] ?? 0,
                'data_type' => $group['data_type'] ?? 0,
                'description' => $group['description'] ?? '',
                'enabled' => $group['enabled'] ?? 1,
                'created_by' => 1,
                'updated_by' => 1,
            ]);

            // 插入字典项
            if (!empty($group['items'])) {
                foreach ($group['items'] as $item) {
                    DictItem::create([
                        'id' => Snowflake::generate(),
                        'dict_id' => $dictId,
                        'label' => $item['label'] ?? '',
                        'value' => $item['value'] ?? '',
                        'code' => $item['code'] ?? '',
                        'sort' => $item['sort'] ?? 0,
                        'enabled' => $item['enabled'] ?? 1,
                        'created_by' => 1,
                        'updated_by' => 1,
                        'remark' => $item['remark'] ?? '',
                    ]);
                }
            }
        }
    }
}
