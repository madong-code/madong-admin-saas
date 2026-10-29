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

/**
 * 字典分组归一：group_code 由 system 修正为 default，并同步「字典分组」字典项
 *
 * 背景:
 *   安装时 DictTrait 误读键名（$dict['group']），导致所有字典 group_code 落成 system，
 *   而字典种子声明的是 default；同时 sys_dict_group_code 的字典项 value 为占位值
 *   value1/value2，与真实分组无关，按分组筛选永远匹配不到数据。
 *
 * 处理:
 *   1. 字典表（模板/租户）中 group_code = 'system' 的统一改为 'default'
 *   2. sys_dict_group_code 字典项重建为 默认分组(default) / 其他分组(other)
 */

use core\io\uuid\Snowflake;
use Illuminate\Database\Schema\Builder;

return new class
{
    private const DICT_CODE = 'sys_dict_group_code';

    private const ITEMS = [
        ['label' => '默认分组', 'value' => 'default', 'code' => 'default', 'sort' => 1],
        ['label' => '其他分组', 'value' => 'other', 'code' => 'other', 'sort' => 2],
    ];

    public function up(Builder $schema): void
    {
        $this->normalizeGroupCode($schema, 'saas_template_dict');
        $this->normalizeGroupCode($schema, 'sys_dict');

        $this->rebuildItems($schema, 'saas_template_dict', 'saas_template_dict_item', 'dict_template_id');
        $this->rebuildItems($schema, 'sys_dict', 'sys_dict_item', 'dict_id');
    }

    public function down(Builder $schema): void
    {
        // 数据归一不可逆（原 system / value1 为错误值），回滚不做处理
    }

    /**
     * 将历史 group_code = system 归一为 default
     */
    private function normalizeGroupCode(Builder $schema, string $table): void
    {
        if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'group_code')) {
            return;
        }

        $schema->getConnection()->table($table)
            ->where('group_code', 'system')
            ->update(['group_code' => 'default', 'updated_at' => time()]);
    }

    /**
     * 重建「字典分组」字典项（幂等）
     */
    private function rebuildItems(Builder $schema, string $dictTable, string $itemTable, string $foreignKey): void
    {
        if (!$schema->hasTable($dictTable) || !$schema->hasTable($itemTable)) {
            return;
        }

        $connection = $schema->getConnection();
        $dictIds    = $connection->table($dictTable)
            ->where('code', self::DICT_CODE)
            ->pluck('id')
            ->all();

        foreach ($dictIds as $dictId) {
            $items = $connection->table($itemTable)->where($foreignKey, $dictId)->orderBy('sort')->get();
            $isOk  = $items->count() === count(self::ITEMS)
                && (string)$items->first()->value === 'default'
                && (string)$items->last()->value === 'other';
            if ($isOk) {
                continue;
            }

            $connection->table($itemTable)->where($foreignKey, $dictId)->delete();
            foreach (self::ITEMS as $item) {
                $connection->table($itemTable)->insert([
                    'id'         => Snowflake::generate(),
                    $foreignKey  => $dictId,
                    'label'      => $item['label'],
                    'value'      => $item['value'],
                    'code'       => $item['code'],
                    'sort'       => $item['sort'],
                    'enabled'    => 1,
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);
            }
        }
    }
};
