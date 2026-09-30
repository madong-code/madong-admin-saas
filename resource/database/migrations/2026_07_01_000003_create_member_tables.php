<?php

/**
 * 创建会员表
 * 
 * 表:
 * - member (会员表)
 * - member_bill (会员账单表)
 * - member_level (会员等级表)
 * - member_menu (会员菜单表)
 * - member_points (会员积分表)
 * - member_sign (会员签到表)
 * - member_tag (会员标签表)
 * - member_tag_permission (标签权限关系表)
 * - member_tag_relation (会员标签关系表)
 * - member_third_party (会员第三方登录表)
 * - member_withdraw (会员提现表)
 * - member_withdraw_account (会员提现账号表)
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class {

    public function up(Builder $schema): void
    {
        // 1. 会员表
        if (!$schema->hasTable('member')) {
            $schema->create('member', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('username', 50)->unique()->comment('用户名');
                $table->string('email', 100)->nullable()->comment('邮箱');
                $table->string('phone', 20)->nullable()->comment('手机号');
                $table->string('password', 255)->comment('密码');
                $table->string('nickname', 50)->nullable()->comment('昵称');
                $table->string('avatar', 255)->nullable()->comment('头像');
                $table->bigInteger('level_id')->default(1)->comment('等级ID');
                $table->integer('points')->default(0)->comment('积分');
                $table->decimal('balance', 10, 2)->default(0)->comment('余额');
                $table->tinyInteger('gender')->default(0)->comment('性别: 0-未知 1-男 2-女');
                $table->integer('birthday')->nullable()->comment('生日时间戳');
                $table->integer('last_login_time')->nullable()->comment('最后登录时间戳');
                $table->string('last_login_ip', 50)->nullable()->comment('最后登录IP');
                $table->integer('login_count')->default(0)->comment('登录次数');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1-启用 0-禁用');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->integer('deleted_at')->nullable()->comment('删除时间戳');
                $table->longText('bio')->nullable()->comment('个人简介');
                $table->index('level_id');
                $table->index('enabled');
                $table->index('created_at');
            });
        }

        // 2. 会员账单表
        if (!$schema->hasTable('member_bill')) {
            $schema->create('member_bill', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('member_id')->comment('会员ID');
                $table->tinyInteger('type')->comment('类型: 1-收入 2-支出');
                $table->tinyInteger('category')->comment('分类: 1-充值 2-提现 3-订单 4-退款 5-积分 6-签到 99-其他');
                $table->decimal('amount', 10, 2)->comment('金额');
                $table->decimal('balance', 10, 2)->nullable()->comment('余额');
                $table->string('description', 255)->nullable()->comment('描述');
                $table->string('order_sn', 50)->nullable()->comment('订单号');
                $table->tinyInteger('status')->default(0)->comment('状态: 0-待处理 1-成功 2-失败');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->index('member_id');
                $table->index('type');
                $table->index('category');
                $table->index('status');
                $table->index('created_at');
                $table->index('order_sn');
            });
        }

        // 3. 会员等级表
        if (!$schema->hasTable('member_level')) {
            $schema->create('member_level', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('name', 50)->unique()->comment('等级名称');
                $table->tinyInteger('level')->comment('等级值');
                $table->integer('min_points')->default(0)->comment('最低积分');
                $table->integer('max_points')->default(0)->comment('最高积分');
                $table->decimal('discount', 3, 2)->default(1.00)->comment('折扣率');
                $table->string('icon', 255)->nullable()->comment('等级图标');
                $table->string('color', 20)->nullable()->comment('等级颜色');
                $table->string('description', 255)->nullable()->comment('等级描述');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1-启用 0-禁用');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->index('level');
                $table->index('enabled');
            });
        }

        // 4. 会员菜单表
        if (!$schema->hasTable('member_menu')) {
            $schema->create('member_menu', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('name', 100)->comment('菜单名称');
                $table->string('url', 255)->comment('菜单链接');
                $table->string('icon', 100)->nullable()->comment('菜单图标');
                $table->bigInteger('parent_id')->default(0)->comment('父级ID');
                $table->tinyInteger('level')->default(1)->comment('级别');
                $table->tinyInteger('type')->default(1)->comment('菜单类型: 1-普通菜单 2-开通菜单');
                $table->smallInteger('sort')->default(0)->comment('排序');
                $table->tinyInteger('target')->default(2)->comment('目标窗口: 1-当前窗口 2-新窗口');
                $table->decimal('price', 10, 2)->default(0)->comment('开通价格');
                $table->tinyInteger('is_open')->default(0)->comment('是否开通: 1-已开通 0-未开通');
                $table->string('open_condition', 255)->nullable()->comment('开通条件');
                $table->tinyInteger('is_show')->default(1)->comment('显示状态: 1-显示 0-隐藏');
                $table->tinyInteger('status')->default(1)->comment('状态: 1-启用 0-禁用');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->integer('deleted_at')->nullable()->comment('删除时间戳');
                $table->index('parent_id');
                $table->index('type');
                $table->index('sort');
                $table->index('is_open');
                $table->index('is_show');
                $table->index('status');
            });
        }

        // 5. 会员积分表
        if (!$schema->hasTable('member_points')) {
            $schema->create('member_points', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('member_id')->comment('会员ID');
                $table->integer('points')->default(0)->comment('积分数量');
                $table->integer('balance')->nullable()->comment('操作后积分');
                $table->tinyInteger('type')->nullable()->comment('积分类型: 1-增加 2-减少 3-调整');
                $table->string('source', 255)->nullable()->comment('积分来源');
                $table->string('remark', 255)->nullable()->comment('备注');
                $table->string('operator', 50)->nullable()->comment('操作人');
                $table->string('order_id', 50)->nullable()->comment('订单ID');
                $table->integer('created_at')->nullable()->comment('创建时间');
                $table->integer('updated_at')->nullable()->comment('创建时间');
                $table->index('member_id');
                $table->index('type');
                $table->index('created_at');
            });
        }

        // 6. 会员签到表
        if (!$schema->hasTable('member_sign')) {
            $schema->create('member_sign', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('member_id')->comment('会员ID');
                $table->date('sign_date')->nullable()->comment('签到日期');
                $table->integer('points')->default(0)->comment('签到积分');
                $table->smallInteger('continuous_days')->default(1)->comment('连续签到天数');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable();
                $table->string('device_ip', 50)->nullable();
                $table->string('device_ua', 50)->nullable();
                $table->unique(['member_id', 'sign_date']);
                $table->index('member_id');
                $table->index('sign_date');
            });
        }

        // 7. 会员标签表
        if (!$schema->hasTable('member_tag')) {
            $schema->create('member_tag', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('name', 50)->unique()->comment('标签名称');
                $table->string('color', 20)->nullable()->comment('标签颜色');
                $table->string('description', 255)->nullable()->comment('标签描述');
                $table->smallInteger('sort')->default(0)->comment('排序');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1-启用 0-禁用');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->index('sort');
                $table->index('enabled');
            });
        }

        // 8. 标签权限关系表
        if (!$schema->hasTable('member_tag_permission')) {
            $schema->create('member_tag_permission', function (Blueprint $table) {
                $table->bigInteger('menu_id')->comment('WEB_MENU_ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('tag_id')->comment('标签ID');
                $table->primary(['menu_id', 'tag_id']);
                $table->index('menu_id');
                $table->index('tag_id');
            });
        }

        // 9. 会员标签关系表
        if (!$schema->hasTable('member_tag_relation')) {
            $schema->create('member_tag_relation', function (Blueprint $table) {
                $table->bigInteger('member_id')->comment('会员ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('tag_id')->comment('标签ID');
                $table->unique(['member_id', 'tag_id']);
                $table->index('member_id');
                $table->index('tag_id');
            });
        }

        // 10. 会员第三方登录表
        if (!$schema->hasTable('member_third_party')) {
            $schema->create('member_third_party', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('member_id')->comment('会员ID');
                $table->tinyInteger('platform')->default(0)->comment('平台类型: 1=QQ, 2=微信, 3=微博, 4=抖音');
                $table->string('openid', 255)->default('')->comment('第三方OpenID');
                $table->string('unionid', 255)->default('')->comment('第三方UnionID');
                $table->string('nickname', 50)->default('')->comment('昵称');
                $table->string('avatar', 255)->default('')->comment('头像');
                $table->tinyInteger('gender')->default(0)->comment('性别: 0=未知, 1=男, 2=女');
                $table->string('country', 50)->default('')->comment('国家');
                $table->string('province', 50)->default('')->comment('省份');
                $table->string('city', 50)->default('')->comment('城市');
                $table->string('access_token', 255)->default('')->comment('访问令牌');
                $table->string('refresh_token', 255)->default('')->comment('刷新令牌');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 0=禁用, 1=启用');
                $table->integer('expires_at')->nullable()->comment('令牌过期时间');
                $table->integer('created_at')->nullable()->comment('创建时间');
                $table->integer('updated_at')->nullable()->comment('更新时间');
            });
        }

        // 11. 会员提现表
        if (!$schema->hasTable('member_withdraw')) {
            $schema->create('member_withdraw', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('member_id')->comment('会员ID');
                $table->bigInteger('account_id')->nullable()->comment('提现账号ID');
                $table->decimal('amount', 10, 2)->comment('提现金额');
                $table->decimal('fee', 10, 2)->default(0)->comment('手续费');
                $table->decimal('actual_amount', 10, 2)->default(0)->comment('实际到账金额');
                $table->tinyInteger('status')->nullable()->comment('状态');
                $table->string('bank_name', 100)->nullable()->comment('银行名称');
                $table->string('bank_account', 50)->nullable()->comment('银行账号');
                $table->string('bank_cardholder', 50)->nullable()->comment('持卡人姓名');
                $table->string('order_sn', 50)->nullable()->comment('订单号');
                $table->string('remark', 255)->nullable()->comment('备注');
                $table->string('audit_remark', 255)->nullable()->comment('审核备注');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->integer('audit_at')->nullable()->comment('审核时间戳');
                $table->index('member_id');
                $table->index('account_id');
                $table->index('status');
                $table->index('created_at');
                $table->index('order_sn');
            });
        }

        // 12. 会员提现账号表
        if (!$schema->hasTable('member_withdraw_account')) {
            $schema->create('member_withdraw_account', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('member_id')->comment('会员ID');
                $table->tinyInteger('type')->comment('账号类型');
                $table->string('bank_name', 100)->nullable()->comment('银行名称');
                $table->string('account_name', 50)->nullable()->comment('账户名称');
                $table->string('account_number', 50)->nullable()->comment('账号号码');
                $table->string('branch_name', 100)->nullable()->comment('支行名称');
                $table->tinyInteger('is_default')->default(0)->comment('是否默认: 1-是 0-否');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1-启用 0-禁用');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->integer('deleted_at')->nullable()->comment('删除时间戳');
                $table->index('member_id');
                $table->index('type');
                $table->index('is_default');
                $table->index('enabled');
            });
        }

        echo "Created member tables.\n";
    }

    public function down(Builder $schema): void
    {
        $schema->dropIfExists('member_withdraw_account');
        $schema->dropIfExists('member_withdraw');
        $schema->dropIfExists('member_third_party');
        $schema->dropIfExists('member_tag_relation');
        $schema->dropIfExists('member_tag_permission');
        $schema->dropIfExists('member_tag');
        $schema->dropIfExists('member_sign');
        $schema->dropIfExists('member_points');
        $schema->dropIfExists('member_menu');
        $schema->dropIfExists('member_level');
        $schema->dropIfExists('member_bill');
        $schema->dropIfExists('member');
    }
};
