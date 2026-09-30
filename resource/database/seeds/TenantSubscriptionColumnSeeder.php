<?php
/**
 * 租户表 subscription_id 字段移除执行器
 * 背景：已改用 1 个租户支持多个套餐（saas_tenant_subscription），不再保留 saas_tenant.subscription_id
 * 该 seeder 用于在已存在 saas_tenant 表（含旧 subscription_id 字段）的数据库中执行移除操作。
 */

declare(strict_types=1);
namespace resource\database\seeds;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Seeder;

class TenantSubscriptionColumnSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('saas_tenant')) {
            return;
        }

        if (Schema::hasColumn('saas_tenant', 'subscription_id')) {
            Schema::table('saas_tenant', function (Blueprint $table) {
                // 先移除旧索引，再移除字段
                $table->dropIndex('idx_subscription_id');
                $table->dropColumn('subscription_id');
            });
        }
    }
}
