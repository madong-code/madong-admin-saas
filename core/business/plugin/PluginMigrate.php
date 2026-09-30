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
 * 插件迁移基类
 * 
 * 迁移文件写法（匿名类方式）：
 * 
 * <?php
 * use Illuminate\Database\Schema\Builder;
 * use Illuminate\Database\Schema\Blueprint;
 * 
 * return new class {
 *     public function up(Builder $schema): void
 *     {
 *         $schema->create('official_app', function (Blueprint $table) {
 *             $table->bigInteger('id')->primary();
 *             $table->string('name', 50);
 *         });
 *     }
 *     
 *     public function down(Builder $schema): void
 *     {
 *         $schema->dropIfExists('official_app');
 *     }
 * };
 */

namespace core\business\plugin;

use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;

abstract class PluginMigrate
{
    /**
     * 迁移向上执行（安装）
     */
    abstract public function up(Builder $schema): void;
    
    /**
     * 迁移向下执行（回滚）
     */
    public function down(Builder $schema): void
    {
        // 可选实现
    }
}
