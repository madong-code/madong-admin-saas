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
 * 友情链接表增加 contact（申请友链提交的联系方式）
 *
 * 背景:
 *   对齐标准版前台「申请友链」能力：页脚提交的申请单除站点名称/地址/描述外，
 *   还需留存联系方式（邮箱 / QQ / 微信）供后台审核时联系。
 *
 * 多租户:
 *   web_link 已带 tenant_id 并走 TenantScope，新增列无需额外处理，
 *   申请单自动归属当前租户。
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class
{
    public function up(Builder $schema): void
    {
        if ($schema->hasTable('web_link') && !$schema->hasColumn('web_link', 'contact')) {
            $schema->table('web_link', function (Blueprint $blueprint) {
                $blueprint->string('contact', 128)
                    ->nullable()
                    ->after('description')
                    ->comment('联系方式（申请友链提交）');
            });
        }
    }

    public function down(Builder $schema): void
    {
        if ($schema->hasTable('web_link') && $schema->hasColumn('web_link', 'contact')) {
            $schema->table('web_link', function (Blueprint $blueprint) {
                $blueprint->dropColumn('contact');
            });
        }
    }
};
