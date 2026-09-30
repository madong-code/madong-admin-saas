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
 * sys_upload 增加存储空间标识 space
 *
 * 背景:
 *   存储私有化后, 同一份文件在「公开空间(default)」与「私有空间(private)」是两个
 *   相互独立的桶, 各自持有自己的访问地址。若附件去重仍仅按 hash 判定, 切换空间后
 *   会复用另一空间的旧记录, 导致返回当前空间访问不到(甚至 403)的地址。
 *
 * 语义:
 *   space = default  公开空间(可直接拼接地址访问)
 *   space = private  私有空间(需换取签名直链)
 *   由 core\io\upload\UploadFile::spaceMark() 计算, 与 sys_upload.platform 一起构成
 *   去重键(hash + platform + space)。
 *
 * 兼容:
 *   存量数据一律回填为 'default'(改造前恒为公开空间), 保证历史附件地址语义不变。
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class
{
    public function up(Builder $schema): void
    {
        if (!$schema->hasTable('sys_upload')) {
            return;
        }

        if (!$schema->hasColumn('sys_upload', 'space')) {
            $schema->table('sys_upload', function (Blueprint $table) {
                $table->string('space', 20)->default('default')
                    ->after('platform')
                    ->comment('存储空间: default=公开 private=私有');
                $table->index(['hash', 'platform', 'space'], 'idx_upload_hash_platform_space');
            });
        }
    }

    public function down(Builder $schema): void
    {
        if (!$schema->hasTable('sys_upload') || !$schema->hasColumn('sys_upload', 'space')) {
            return;
        }

        $schema->table('sys_upload', function (Blueprint $table) {
            $table->dropIndex('idx_upload_hash_platform_space');
            $table->dropColumn('space');
        });
    }
};
