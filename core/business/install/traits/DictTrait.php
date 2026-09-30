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

namespace core\business\install\traits;

use app\model\system\dict\Dict as SysDict;
use app\model\system\dict\DictItem as SysDictItem;
use app\model\tenant\DictItemTemplate;
use app\model\tenant\DictTemplate;
use core\io\uuid\Snowflake;

/**
 * 字典导入 Trait
 */
trait DictTrait
{
    /**
     * 运行字典种子
     *
     * @param bool $enableTenant 是否启用多租户模式
     *        - true:  多租户模式，只写入 saas_template_dict，租户创建时同步
     *        - false: 非租户模式，直接写入 sys_dict
     */
    public function runDict(bool $enableTenant = false): void
    {
        if ($enableTenant) {
            // ==== 多租户模式 ====
            // admin 字典 → saas_template_dict
            $this->loadDictIntoTemplate('admin');
        } else {
            // ==== 非租户模式 ====
            // admin 字典 → sys_dict
            $this->runAdminDict();
        }
    }

    /**
     * 将 saas_template_dict 克隆到 sys_dict
     * 保留相同 ID，template_id = id，保证两个表 ID 一致
     */
    public function cloneTemplateToSysDict(): void
    {
        $templateTable = $this->table((new DictTemplate())->getTable());
        $dictTable = $this->table((new SysDict())->getTable());

        $this->getPdo()->exec("DELETE FROM `{$dictTable}`");

        $sql = "INSERT INTO `{$dictTable}` (
                    `id`, `template_id`, `group_code`, `name`, `code`, `sort`, `data_type`,
                    `description`, `enabled`, `created_at`, `created_by`, `updated_at`, `updated_by`
                )
                SELECT
                    `id`, `id` AS `template_id`, `group_code`, `name`, `code`, `sort`, `data_type`,
                    `description`, `enabled`, `created_at`, `created_by`, `updated_at`, `updated_by`
                FROM `{$templateTable}`";
        $this->getPdo()->exec($sql);
    }

    /**
     * 多租户模式：从数据文件加载字典到 saas_template_dict
     *
     * @param string $app admin / platform
     */
    public function loadDictIntoTemplate(string $app): void
    {
        $dictFile = base_path("resource/data/dict/dict.php");
        if (!file_exists($dictFile)) {
            return;
        }

        $dicts = require $dictFile;
        $table = $this->table((new DictTemplate())->getTable());
        $itemTable = $this->table((new DictItemTemplate())->getTable());
        $this->getPdo()->exec("DELETE FROM `{$itemTable}`");
        $this->getPdo()->exec("DELETE FROM `{$table}` WHERE `app` = '{$app}'");

        foreach ($dicts as $dict) {
            $this->insertDictTemplate($table, $itemTable, $dict, $app);
        }
    }

    /**
     * 非租户模式：admin 字典直接写入 sys_dict
     */
    public function runAdminDict(): void
    {
        $dictFile = base_path('resource/data/dict/dict.php');
        if (!file_exists($dictFile)) {
            return;
        }

        $dicts         = require $dictFile;
        $dictTable     = $this->table((new SysDict())->getTable());
        $dictItemTable = $this->table((new SysDictItem())->getTable());
        $pdo           = $this->getPdo();

        $pdo->exec("TRUNCATE TABLE `{$dictItemTable}`");
        $pdo->exec("TRUNCATE TABLE `{$dictTable}`");

        foreach ($dicts as $dict) {
            $dictId = Snowflake::generate();

            $this->insert($dictTable, [
                'id'          => $dictId,
                'group_code'  => $dict['group'] ?? 'system',
                'code'        => $dict['code'] ?? '',
                'name'        => $dict['name'] ?? '',
                'sort'        => $dict['sort'] ?? 0,
                'data_type'   => $dict['data_type'] ?? 1,
                'description' => $dict['remark'] ?? '',
                'enabled'     => $dict['status'] ?? 1,
                'created_at'  => $this->currentTime,
                'updated_at'  => $this->currentTime,
                'created_by'  => 1,
                'updated_by'  => 1,
            ]);

            if (!empty($dict['items'])) {
                foreach ($dict['items'] as $item) {
                    $this->insert($dictItemTable, [
                        'id'         => Snowflake::generate(),
                        'dict_id'    => $dictId,
                        'code'       => $dict['code'] ?? '',
                        'value'      => $item['value'] ?? '',
                        'label'      => $item['label'] ?? '',
                        'color'      => $item['color'] ?? '',
                        'sort'       => $item['sort'] ?? 0,
                        'enabled'    => $item['status'] ?? 1,
                        'created_at' => $this->currentTime,
                        'updated_at' => $this->currentTime,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]);
                }
            }
        }
    }

    /**
     * 递归插入字典模板（含模板项）
     */
    private function insertDictTemplate(string $tableName, string $itemTableName, array $dict, string $app): int|string
    {
        $id = Snowflake::generate();

        $data = [
            'id'          => $id,
            'app'         => $app,
            'group_code'  => $dict['group'] ?? 'system',
            'code'        => $dict['code'] ?? '',
            'name'        => $dict['name'] ?? '',
            'sort'        => $dict['sort'] ?? 0,
            'data_type'   => $dict['data_type'] ?? 1,
            'description' => $dict['remark'] ?? '',
            'enabled'     => $dict['status'] ?? 1,
            'created_at'  => $this->currentTime,
            'created_by'  => 0,
            'updated_at'  => $this->currentTime,
            'updated_by'  => 0,
        ];

        $this->insert($tableName, $data);

        if (!empty($dict['items'])) {
            foreach ($dict['items'] as $item) {
                $this->insert($itemTableName, [
                    'id'              => Snowflake::generate(),
                    'dict_template_id' => $id,
                    'code'             => $dict['code'] ?? '',
                    'value'            => $item['value'] ?? '',
                    'label'            => $item['label'] ?? '',
                    'color'            => $item['color'] ?? '',
                    'sort'             => $item['sort'] ?? 0,
                    'enabled'          => $item['status'] ?? 1,
                    'created_at'       => $this->currentTime,
                    'created_by'       => 0,
                    'updated_at'       => $this->currentTime,
                    'updated_by'       => 0,
                ]);
            }
        }

        return $id;
    }
}
