<?php

/**
 * 内容审核表结构（合并单文件，全新库）
 *
 * 将原分散的审核迁移合并为单文件，包含审核系统全部三张表（仅面向全新库）：
 *  - sys_review          审核运行表（仅存待审/审批中热数据）
 *  - sys_review_archive  审核记录表（创建即与运行表同建、共用雪花ID，
 *                        运行期实时同步、终结算就地更新，归档视图仅含已终结行）
 *  - sys_review_log      审核操作日志表（每次 approve/reject/cancel 的审计轨迹）
 *
 * 运行表字段 flow_type（审核模式）、cancel_reason（取消原因）已直接纳入建表定义。
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class {
    public function up(Builder $schema): void
    {
        // ============ 1. 审核运行表 sys_review ============
        if (!$schema->hasTable('sys_review')) {
            $schema->create('sys_review', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');

                $table->string('reviewable_type', 191)->comment('关联模型类型');
                $table->bigInteger('reviewable_id')->comment('关联模型ID');
                $table->tinyInteger('status')->default(0)->comment('审核状态:0待审 1通过 2拒绝 3取消 4审批中');
                $table->longText('reason')->nullable()->comment('审核原因/备注');
                $table->bigInteger('reviewer_id')->nullable()->comment('审核人ID');
                $table->integer('reviewed_at')->nullable()->comment('审核时间戳');
                $table->string('flow_instance_id', 100)->nullable()->comment('审批流实例ID');
                $table->json('extra_data')->nullable()->comment('扩展数据');
                $table->string('flow_type', 20)->default('simple')->comment('审核模式:simple|workflow');
                $table->string('cancel_reason', 255)->nullable()->comment('取消原因');
                $table->bigInteger('created_by')->comment('创建人ID');
                $table->bigInteger('updated_by')->nullable()->comment('更新人ID');
                $table->integer('created_at')->comment('创建时间戳');
                $table->integer('updated_at')->comment('更新时间戳');
                $table->integer('deleted_at')->nullable()->comment('软删除时间戳');

                $table->index(['reviewable_type', 'reviewable_id']);
                $table->index('status');
                $table->index('reviewer_id');
                $table->index('flow_instance_id');
                $table->index('created_at');
            });
        }

        // ============ 2. 审核记录表（归档） sys_review_archive ============
        if (!$schema->hasTable('sys_review_archive')) {
            $schema->create('sys_review_archive', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键(沿用审核ID)');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->index('tenant_id', 'idx_tenant_id');

                $table->string('reviewable_type', 50)->nullable()->comment('审核类型键');
                $table->bigInteger('reviewable_id')->nullable()->comment('审核对象ID');
                $table->index(['reviewable_type', 'reviewable_id'], 'idx_reviewable');

                $table->tinyInteger('status')->default(0)->comment('审核状态:0待审 1通过 2拒绝 3取消 4审批中');
                $table->string('reason', 255)->nullable()->comment('审核意见');
                $table->bigInteger('reviewer_id')->nullable()->comment('审核人ID');
                $table->integer('reviewed_at')->nullable()->comment('审核时间');

                $table->json('extra_data')->nullable()->comment('业务快照(标题/内容/申请人等)');

                $table->string('flow_type', 20)->default('simple')->comment('审核模式:simple|workflow');
                $table->string('flow_instance_id', 100)->nullable()->comment('外部审批流实例ID');
                $table->string('cancel_reason', 255)->nullable()->comment('取消原因');

                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->unsignedInteger('deleted_at')->nullable()->comment('删除时间');
                $table->integer('archived_at')->nullable()->comment('归档时间');

                $table->index('status', 'idx_status');
            });
        }

        // ============ 3. 审核操作日志表 sys_review_log ============
        if (!$schema->hasTable('sys_review_log')) {
            $schema->create('sys_review_log', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键(雪花ID)');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->index('tenant_id', 'idx_tenant_id');

                $table->bigInteger('review_id')->nullable()->comment('审核记录ID');
                $table->index('review_id', 'idx_review_id');

                $table->string('action', 20)->comment('操作:approve|reject|cancel');
                $table->bigInteger('operator_id')->nullable()->comment('操作人ID');
                $table->text('reason')->nullable()->comment('原因');

                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->unsignedInteger('deleted_at')->nullable()->comment('删除时间');
            });
        }
    }

    public function down(Builder $schema): void
    {
        $schema->dropIfExists('sys_review_log');
        $schema->dropIfExists('sys_review_archive');
        $schema->dropIfExists('sys_review');
    }
};
