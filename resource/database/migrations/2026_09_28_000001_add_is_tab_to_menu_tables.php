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
 * 菜单表增加 is_tab（是否显示在 tabs 标签页）
 *
 * 背景:
 *   对齐标准版 madong-admin 的 sys_menu.is_tab 语义。改造前菜单格式化监听器读取的是
 *   并不存在的列 is_hide_tab，导致 hideInTab 恒为 false（死代码）。
 *
 * 语义:
 *   is_tab = 1  显示在 tags 标签页（默认，与改造前行为一致，不改变既有表现）
 *   is_tab = 0  不显示在 tags 标签页
 *
 * 覆盖表:
 *   sys_menu            租户/管理端菜单表
 *   saas_template_menu  菜单模板表（平台端与租户菜单模板共用）
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class
{
    /** 需要补列的菜单表（不含表前缀） */
    private const TABLES = ['sys_menu', 'saas_template_menu'];

    public function up(Builder $schema): void
    {
        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table) || $schema->hasColumn($table, 'is_tab')) {
                continue;
            }

            $schema->table($table, function (Blueprint $blueprint) {
                $blueprint->tinyInteger('is_tab')->default(1)
                    ->after('is_show')
                    ->comment('是否显示在tags标签: 0否 1是');
            });
        }
    }

    public function down(Builder $schema): void
    {
        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'is_tab')) {
                continue;
            }

            $schema->table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('is_tab');
            });
        }
    }
};