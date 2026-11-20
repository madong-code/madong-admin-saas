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
 * 修正「字典分组」字典项取值，使其与字典实际使用的 group_code 一致
 *
 * 背景:
 *   sys_dict_group_code 的字典项 value 为占位值 value1/value2，
 *   而字典表 group_code 实际取值是 system（DictTrait 安装时写入），
 *   导致 /template/dict 按分组筛选永远匹配不到数据。
 *
 * 处理:
 *   模板字典（saas_template_dict_item，多租户模式）与租户字典（sys_dict_item，
 *   单体模式）中的该项统一重建为单条「系统分组 / system」。
 */

use core\io\uuid\Snowflake;
use Illuminate\Database\Schema\Builder;

return new class
{
    private const DICT_CODE = 'sys_dict_group_code';

    public function up(Builder $schema): void
    {
        $this->rebuild(
            $schema,
            'saas_template_dict',
            'saas_template_dict_item',
            'dict_template_id'
        );
        $this->rebuild($schema, 'sys_dict', 'sys_dict_item', 'dict_id');
    }

    public function down(Builder $schema): void
    {
        // 数据修正不可逆（原占位值无业务含义），回滚不做处理
    }

    /**
     * 重建指定字典表下「字典分组」字典项（幂等）
     */
    private function rebuild(Builder $schema, string $dictTable, string $itemTable, string $foreignKey): void
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
            $items = $connection->table($itemTable)->where($foreignKey, $dictId)->get();
            $isOk  = $items->count() === 1 && (string)$items->first()->value === 'system';
            if ($isOk) {
                continue;
            }

            $connection->table($itemTable)->where($foreignKey, $dictId)->delete();
            $connection->table($itemTable)->insert([
                'id'         => Snowflake::generate(),
                $foreignKey  => $dictId,
                'label'      => '系统分组',
                'value'      => 'system',
                'code'       => 'system',
                'sort'       => 1,
                'enabled'    => 1,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }
    }
};
