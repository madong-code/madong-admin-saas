<?php

/**
 * 创建全部系统表（sys_* / ma_* 前缀表集中管理）
 *
 * 表:
 * - sys_admin (用户信息表)
 * - sys_admin_type (管理员类型定义表)
 * - sys_admin_type_rel (管理员类型关联表)
 * - sys_admin_main (用户与部门职位关联表)
 * - sys_admin_dept (用户与部门关联表)
 * - sys_admin_post (用户与岗位关联表)
 * - sys_admin_role (用户与角色关联表)
 * - sys_config (配置表, 含 template_id/source/type/sort)
 * - sys_crontab (定时任务表)
 * - sys_crontab_log (定时任务日志表)
 * - sys_dept (部门信息表)
 * - sys_dept_leader (部门领导关联表)
 * - sys_dict (字典类型表, 含 template_id)
 * - sys_dict_item (字典数据表, 含 template_id)
 * - sys_login_log (登录日志表)
 * - sys_menu (菜单表)
 * - sys_message (消息记录表)
 * - sys_message_category (消息分类表)
 * - sys_message_definition (消息定义表)
 * - sys_message_definition_template (定义-模板中间表)
 * - sys_message_subscribe (用户订阅表)
 * - sys_message_template (消息模板表)
 * - sys_notepad_document (记事本文档表)
 * - sys_notepad_folder (记事本文件夹表)
 * - sys_notice (通知公告表)
 * - sys_operate_log (系统操作日志表)
 * - sys_plugin (系统插件表)
 * - sys_plugin_log (插件日志表)
 * - sys_post (岗位信息表)
 * - sys_rate_limiter (限流规则表)
 * - sys_rate_restrictions (限制访问名单表)
 * - sys_recycle_bin (数据回收记录表)
 * - sys_role (角色信息表)
 * - sys_role_dept (角色与部门关联表)
 * - sys_role_menu (角色与菜单关联表)
 * - sys_role_scope_dept (角色与部门关联表)
 * - sys_tenant_migration (租户迁移执行记录表)
 * - sys_upload (文件信息表)
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;

return new class {

    public function up(Builder $schema): void
    {
        // 用户信息表
        if (!$schema->hasTable('sys_admin')) {
            $schema->create('sys_admin', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('用户ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('user_name', 20)->comment('账号');
                $table->unique(['user_name', 'tenant_id'], 'sys_admin_user_name_tenant_unique');
                $table->string('real_name', 30)->nullable()->comment('用户');
                $table->string('nick_name', 100)->nullable()->comment('昵称');
                $table->string('password', 100)->comment('密码');
                $table->tinyInteger('is_super')->default(0)->comment('用户类型:1系统用户 0普通用户');
                $table->string('mobile_phone', 11)->nullable()->comment('手机');
                $table->string('email', 50)->nullable()->comment('用户邮箱');
                $table->string('avatar', 255)->nullable()->comment('用户头像');
                $table->string('signed', 255)->nullable()->comment('个人签名');
                $table->string('dashboard', 100)->nullable()->comment('后台首页类型');
                $table->bigInteger('dept_id')->nullable()->comment('部门ID');
                $table->smallInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->string('login_ip', 45)->nullable()->comment('最后登陆IP');
                $table->integer('login_time')->nullable()->comment('最后登陆时间');
                $table->text('backend_setting')->nullable()->comment('后台设置数据');
                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->unsignedInteger('deleted_at')->nullable()->comment('删除时间');
                $table->tinyInteger('sex')->default(0)->comment('0=未知 1=男 2=女');
                $table->longText('remark')->nullable()->comment('备注');
                $table->string('birthday', 50)->nullable()->comment('生日');
                $table->string('tel', 255)->nullable()->comment('座机');
                $table->smallInteger('is_locked')->default(0)->comment('是否锁定: 1是 0否');
            });
        }

        // 管理员类型定义表
        if (!$schema->hasTable('sys_admin_type')) {
            $schema->create('sys_admin_type', function (Blueprint $table) {
                $table->id('id')->comment('主键ID');
                $table->string('code', 30)->unique()->comment('类型编码: platform-平台管理员');
                $table->string('name', 100)->comment('类型名称');
                $table->smallInteger('sort')->default(0)->comment('排序');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
            });
        }

        // 管理员类型关联表
        if (!$schema->hasTable('sys_admin_type_rel')) {
            $schema->create('sys_admin_type_rel', function (Blueprint $table) {
                $table->unsignedBigInteger('admin_id')->comment('管理员ID(关联sys_admin)');
                $table->unsignedBigInteger('type_id')->comment('类型ID(关联sys_admin_type)');
                $table->primary(['admin_id', 'type_id']);
                $table->index('admin_id', 'idx_admin_id');
                $table->index('type_id', 'idx_type_id');
            });

            // 数据迁移: 将现有 admin_type='platform' 的记录同步
            $connection = $schema->getConnection();
            if ($schema->hasColumn('sys_admin', 'admin_type')) {
                $now = time();

                // 1. 插入类型定义
                $typeId = $connection->table('sys_admin_type')->insertGetId([
                    'code'       => 'platform',
                    'name'       => '平台管理员',
                    'sort'       => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // 2. 插入关联记录
                $platformAdmins = $connection->table('sys_admin')
                    ->where('admin_type', 'platform')
                    ->pluck('id');

                if ($platformAdmins->isNotEmpty()) {
                    $records = $platformAdmins->map(fn($adminId) => [
                        'admin_id'   => $adminId,
                        'type_id'    => $typeId,
                    ])->toArray();
                    $connection->table('sys_admin_type_rel')->insert($records);
                }
            }
        }

        // 用户与部门职位关联表
        if (!$schema->hasTable('sys_admin_main')) {
            $schema->create('sys_admin_main', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('admin_id')->comment('用户ID(外键)');
                $table->bigInteger('main_dept_id')->nullable()->comment('主部门ID');
                $table->bigInteger('main_post_id')->nullable()->comment('主职位ID');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->index('admin_id', 'idx_admin_id');
                $table->index('main_dept_id', 'idx_main_dept_id');
                $table->index('main_post_id', 'idx_main_post_id');
            });
        }

        // 用户与部门关联表
        if (!$schema->hasTable('sys_admin_dept')) {
            $schema->create('sys_admin_dept', function (Blueprint $table) {
                $table->bigInteger('admin_id')->comment('用户主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('dept_id')->comment('角色主键');
                $table->primary(['admin_id', 'dept_id']);
            });
        }

        // 用户与岗位关联表
        if (!$schema->hasTable('sys_admin_post')) {
            $schema->create('sys_admin_post', function (Blueprint $table) {
                $table->bigInteger('admin_id')->comment('管理员主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('post_id')->comment('岗位主键');
                $table->primary(['admin_id', 'post_id']);
            });
        }

        // 用户与角色关联表
        if (!$schema->hasTable('sys_admin_role')) {
            $schema->create('sys_admin_role', function (Blueprint $table) {
                $table->bigInteger('admin_id')->comment('管理员主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('role_id')->comment('角色主键');
                $table->primary(['admin_id', 'role_id']);
            });
        }

        // 配置表
        if (!$schema->hasTable('sys_config')) {
            $schema->create('sys_config', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('配置ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->bigInteger('template_id')->nullable()->comment('配置模板ID(saas_template_config.id)');
                $table->string('source', 32)->default('template')->comment('配置来源: template-模板同步 manual-手动添加');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('group_code', 64)->nullable()->comment('分组编码');
                $table->string('code', 64)->comment('唯一编码');
                $table->string('name', 64)->comment('配置名称');
                $table->longText('content')->nullable()->comment('配置内容');
                $table->tinyInteger('is_sys')->default(0)->comment('是否系统');
                $table->tinyInteger('enabled')->default(1)->comment('是否启用');
                $table->integer('sort')->default(0)->comment('排序');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('created_by')->nullable()->comment('创建用户');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新用户');
                $table->bigInteger('deleted_at')->nullable()->comment('是否删除');
                $table->longText('remark')->nullable()->comment('备注');
                $table->index('code');
                $table->index('group_code');
            });
        }

        // 定时任务表
        if (!$schema->hasTable('sys_crontab')) {
            $schema->create('sys_crontab', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('source', 32)->default('custom')->comment('任务来源: system-系统内置 / plugin:{插件名} 插件任务 / custom-自定义');
                $table->string('biz_id', 36)->nullable()->comment('业务id');
                $table->string('title', 191)->comment('任务标题');
                $table->tinyInteger('type')->default(1)->comment('任务类型: 1 url, 2 eval, 3 shell');
                $table->tinyInteger('task_cycle')->default(1)->comment('任务周期');
                $table->json('cycle_rule')->nullable()->comment('任务周期规则');
                $table->longText('rule')->nullable()->comment('任务表达式');
                $table->longText('target')->nullable()->comment('调用任务字符串');
                $table->json('parameter')->nullable()->comment('任务运行参数(key-value)');
                $table->integer('running_times')->default(0)->comment('已运行次数');
                $table->integer('last_running_time')->default(0)->comment('上次运行时间');
                $table->tinyInteger('first_started')->default(0)->comment('是否已首次启动: 0未启动, 1已启动');
                $table->tinyInteger('enabled')->default(0)->comment('任务状态: 0禁用, 1启用');
                $table->integer('created_at')->default(0)->comment('创建时间');
                $table->bigInteger('created_by')->nullable()->comment('创建人');
                $table->bigInteger('deleted_at')->default(0)->comment('软删除时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新人');
                $table->tinyInteger('singleton')->default(1)->comment('是否循环执行');
                $table->longText('remark')->nullable()->comment('备注');
                $table->index('title', 'sys_crontab_title_index');
                $table->index('enabled');
                $table->index('first_started');
                $table->index(['enabled', 'source'], 'idx_enabled_source');
                $table->index(['tenant_id', 'source'], 'idx_tenant_source');
            });
        }

        // 定时任务日志表
        if (!$schema->hasTable('sys_crontab_log')) {
            $schema->create('sys_crontab_log', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('source', 32)->nullable()->comment('任务来源(冗余): system / plugin:{插件名} / custom');
                $table->bigInteger('crontab_id')->comment('任务id');
                $table->string('target', 255)->nullable()->comment('任务调用目标字符串');
                $table->longText('log')->nullable()->comment('任务执行日志');
                $table->tinyInteger('return_code')->default(1)->comment('执行返回状态: 1成功, 0失败');
                $table->string('running_time', 10)->comment('执行所用时间');
                $table->bigInteger('created_at')->default(0)->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->index('created_at');
                $table->index('crontab_id');
            });
        }

        // 部门信息表
        if (!$schema->hasTable('sys_dept')) {
            $schema->create('sys_dept', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('pid')->nullable()->comment('父ID');
                $table->string('level', 500)->nullable()->comment('组级集合');
                $table->string('code', 50)->nullable()->comment('部门唯一编码');
                $table->string('name', 30)->nullable()->comment('部门名称');
                $table->string('main_leader_id', 20)->nullable()->comment('负责人');
                $table->string('phone', 11)->nullable()->comment('联系电话');
                $table->smallInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->smallInteger('sort')->default(0)->comment('排序');
                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
                $table->longText('remark')->nullable()->comment('备注');
                $table->index('pid');
            });
        }

        // 部门领导关联表
        if (!$schema->hasTable('sys_dept_leader')) {
            $schema->create('sys_dept_leader', function (Blueprint $table) {
                $table->bigInteger('dept_id')->comment('部门主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('admin_id')->comment('管理员主键');
                $table->primary(['dept_id', 'admin_id']);
            });
        }

        // 字典类型表
        if (!$schema->hasTable('sys_dict')) {
            $schema->create('sys_dict', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->bigInteger('template_id')->default(0)->comment('字典模板ID(saas_template_dict.id)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('group_code', 50)->nullable()->comment('字典类型');
                $table->string('name', 50)->nullable()->comment('字典名称');
                $table->string('code', 100)->nullable()->comment('字典标示');
                $table->bigInteger('sort')->default(0)->comment('排序');
                $table->smallInteger('data_type')->default(1)->comment('数据类型');
                $table->longText('description')->nullable()->comment('描述');
                $table->smallInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
            });
        }

        // 字典数据表
        if (!$schema->hasTable('sys_dict_item')) {
            $schema->create('sys_dict_item', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->bigInteger('template_id')->default(0)->comment('字典项模板ID(saas_template_dict_item.id)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('dict_id')->nullable()->comment('字典类型ID');
                $table->string('label', 50)->nullable()->comment('字典标签');
                $table->string('value', 100)->nullable()->comment('字典值');
                $table->string('code', 100)->nullable()->comment('字典标示');
                $table->string('color', 50)->nullable()->comment('tag颜色');
                $table->string('other_class', 50)->nullable();
                $table->smallInteger('sort')->default(0)->comment('排序');
                $table->smallInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
                $table->longText('remark')->nullable()->comment('备注');
                $table->index('dict_id');
            });
        }

        // 登录日志表
        if (!$schema->hasTable('sys_login_log')) {
            $schema->create('sys_login_log', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('user_id')->nullable()->comment('关联Admin ID');
                $table->string('app', 32)->nullable()->comment('应用编码');
                $table->string('ip', 45)->nullable()->comment('登录IP地址');
                $table->string('ip_location', 255)->nullable()->comment('IP所属地');
                $table->string('os', 50)->nullable()->comment('操作系统');
                $table->string('browser', 50)->nullable()->comment('浏览器');
                $table->smallInteger('status')->default(1)->comment('登录状态: 1成功 2失败');
                $table->longText('message')->nullable()->comment('提示消息');
                $table->bigInteger('login_time')->nullable()->comment('登录时间');
                $table->longText('key')->nullable()->comment('key');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('expires_at')->nullable()->comment('过期时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
                $table->string('remark', 255)->nullable()->comment('备注');
                $table->index('user_id');
            });
        }

        // 菜单表
        if (!$schema->hasTable('sys_menu')) {
            $schema->create('sys_menu', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('菜单ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('pid')->default(0)->comment('父ID');
                $table->bigInteger('template_id')->default(0)->comment('模板ID');
                $table->string('app', 32)->default('admin')->comment('应用编码');
                $table->string('source', 50)->default('system')->comment('菜单来源');
                $table->string('title', 64)->comment('菜单名称');
                $table->string('code', 64)->nullable()->comment('唯一编码');
                $table->string('level', 255)->nullable()->comment('父ID集合');
                $table->integer('type')->nullable()->comment('菜单类型: 1目录 2菜单 3按钮 4接口 5内链 6外链');
                $table->bigInteger('sort')->default(999)->comment('排序');
                $table->string('path', 100)->nullable()->comment('路由地址');
                $table->string('component', 100)->nullable()->comment('组件地址');
                $table->string('redirect', 255)->nullable()->comment('重定向');
                $table->string('icon', 64)->nullable()->comment('菜单图标');
                $table->tinyInteger('is_show')->default(1)->comment('是否显示: 0否 1是');
                $table->tinyInteger('is_link')->default(0)->comment('是否外链: 0否 1是');
                $table->longText('link_url')->nullable()->comment('外部链接地址');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->integer('open_type')->default(0)->comment('是否外链: 1是 0否');
                $table->tinyInteger('is_cache')->default(0)->comment('是否缓存: 1是 0否');
                $table->tinyInteger('is_sync')->default(1)->comment('是否同步');
                $table->tinyInteger('is_affix')->default(0)->comment('是否固定tags无法关闭');
                $table->tinyInteger('is_global')->default(0)->comment('是否全局公共菜单');
                $table->string('variable', 500)->nullable()->comment('额外参数JSON');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('created_by')->nullable()->comment('创建用户');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新用户');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
                $table->string('methods', 10)->default('get')->comment('请求方法');
                $table->tinyInteger('is_frame')->nullable()->comment('是否外链');
                $table->index('code');
                $table->index('app');
            });
        }

        // 通知公告表
        if (!$schema->hasTable('sys_notice')) {
            $schema->create('sys_notice', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('公告ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->enum('type', ['announcement', 'notice'])->default('announcement')->comment('公告类型');
                $table->string('title', 50)->comment('公告标题');
                $table->longText('content')->nullable()->comment('公告内容');
                $table->integer('sort')->default(10)->comment('排序');
                $table->tinyInteger('enabled')->default(0)->comment('公告状态: 0正常 1关闭');
                $table->string('uuid', 50)->nullable()->comment('uuid');
                $table->bigInteger('created_dept')->nullable()->comment('创建部门');
                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->string('remark', 255)->nullable()->comment('备注');
            });
        }

        // 系统操作日志表
        if (!$schema->hasTable('sys_operate_log')) {
            $schema->create('sys_operate_log', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('name', 50)->nullable()->comment('内容');
                $table->string('app', 50)->nullable()->comment('应用名称');
                $table->string('ip', 255)->nullable()->comment('请求ip');
                $table->string('ip_location', 255)->nullable()->comment('请求ip归属地');
                $table->string('browser', 255)->nullable()->comment('浏览器');
                $table->string('os', 255)->nullable()->comment('操作系统');
                $table->string('url', 500)->nullable()->comment('请求地址');
                $table->string('class_name', 500)->nullable()->comment('类名称');
                $table->string('action', 500)->nullable()->comment('方法名称');
                $table->string('method', 255)->nullable()->comment('请求方式');
                $table->longText('param')->nullable()->comment('请求参数');
                $table->longText('result')->nullable()->comment('返回结果');
                $table->bigInteger('created_at')->nullable()->comment('操作时间');
                $table->bigInteger('updated_at')->nullable();
                $table->string('user_name', 50)->nullable()->comment('操作账号');
            });
        }

        // 岗位信息表
        if (!$schema->hasTable('sys_post')) {
            $schema->create('sys_post', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('dept_id')->nullable()->comment('部门id');
                $table->string('code', 100)->comment('岗位代码');
                $table->string('name', 50)->comment('岗位名称');
                $table->smallInteger('sort')->default(0)->comment('排序');
                $table->smallInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
                $table->string('remark', 255)->nullable()->comment('备注');
            });
        }

        // 限流规则表
        if (!$schema->hasTable('sys_rate_limiter')) {
            $schema->create('sys_rate_limiter', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('name', 64)->comment('规则名称');
                $table->tinyInteger('enabled')->default(1)->comment('状态');
                $table->integer('priority')->default(100)->comment('优先级');
                $table->enum('match_type', ['exact', 'wildcard', 'regex'])->default('exact')->comment('匹配类型');
                $table->string('ip', 50)->nullable()->comment('ip地址');
                $table->string('methods', 50)->default('GET')->comment('请求方法');
                $table->string('path', 200)->default('/')->comment('请求路径');
                $table->string('limit_type', 50)->default('ip')->comment('限制类型');
                $table->integer('limit_value')->default(0)->comment('限制值');
                $table->integer('period')->default(60)->comment('统计周期(秒)');
                $table->integer('ttl')->nullable()->comment('缓存时间(秒)');
                $table->longText('message')->nullable()->comment('提示信息');
                $table->bigInteger('created_by')->nullable()->comment('创建人');
                $table->bigInteger('updated_by')->nullable()->comment('修改人');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->index(['enabled', 'priority']);
                $table->index(['methods', 'path']);
            });
        }

        // 限制访问名单表
        if (!$schema->hasTable('sys_rate_restrictions')) {
            $schema->create('sys_rate_restrictions', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('ip', 45)->nullable()->comment('IP地址');
                $table->string('name', 64)->comment('名称');
                $table->tinyInteger('enabled')->default(1)->comment('规则状态: 0禁用 1启用');
                $table->integer('priority')->default(100)->comment('规则优先级');
                $table->string('methods', 50)->default('GET')->comment('请求方法');
                $table->string('path', 155)->default('/')->comment('路径');
                $table->string('message', 255)->nullable()->comment('提示信息');
                $table->bigInteger('start_time')->nullable()->comment('开始时间');
                $table->bigInteger('end_time')->nullable()->comment('结束时间');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('created_by')->nullable()->comment('创建人');
                $table->bigInteger('updated_by')->nullable()->comment('修改人');
                $table->longText('remark')->nullable()->comment('备注');
                $table->index(['enabled', 'priority']);
                $table->index(['ip', 'methods', 'path']);
            });
        }

        // 数据回收记录表
        if (!$schema->hasTable('sys_recycle_bin')) {
            $schema->create('sys_recycle_bin', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('original_id')->nullable()->comment('原始数据ID');
                $table->json('data')->nullable()->comment('回收的数据');
                $table->json('relation_data')->nullable()->comment('关联表回收的数据');
                $table->string('table_name', 100)->default('')->comment('数据表');
                $table->string('table_prefix', 50)->nullable()->comment('表前缀');
                $table->tinyInteger('enabled')->default(0)->comment('是否已还原');
                $table->string('ip', 50)->default('')->comment('操作者IP');
                $table->bigInteger('operate_by')->default(0)->comment('操作管理员');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
            });
        }

        // 角色信息表
        if (!$schema->hasTable('sys_role')) {
            $schema->create('sys_role', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('pid')->default(0)->comment('父级id');
                $table->string('name', 30)->nullable()->comment('角色名称');
                $table->string('code', 100)->nullable()->comment('角色代码');
                $table->tinyInteger('is_super_admin')->default(0)->comment('是否超级管理员: 1是 0否');
                $table->tinyInteger('role_type')->nullable()->comment('角色类型');
                $table->smallInteger('data_scope')->default(1)->comment('数据范围');
                $table->smallInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->smallInteger('sort')->default(0)->comment('排序');
                $table->string('remark', 255)->nullable()->comment('备注');
                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
            });
        }

        // 角色与部门关联表
        if (!$schema->hasTable('sys_role_dept')) {
            $schema->create('sys_role_dept', function (Blueprint $table) {
                $table->bigInteger('role_id')->comment('用户主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('dept_id')->comment('角色主键');
                $table->primary(['role_id', 'dept_id']);
            });
        }

        // 角色与菜单关联表
        if (!$schema->hasTable('sys_role_menu')) {
            $schema->create('sys_role_menu', function (Blueprint $table) {
                $table->bigInteger('role_id')->comment('角色主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('menu_id')->comment('菜单主键');
                $table->primary(['role_id', 'menu_id']);
            });
        }

        // 角色与部门关联表
        if (!$schema->hasTable('sys_role_scope_dept')) {
            $schema->create('sys_role_scope_dept', function (Blueprint $table) {
                $table->bigInteger('role_id')->comment('用户主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('dept_id')->comment('角色主键');
                $table->primary(['role_id', 'dept_id']);
            });
        }

        // 文件信息表
        if (!$schema->hasTable('sys_upload')) {
            $schema->create('sys_upload', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('文件信息ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->longText('url')->comment('文件访问地址');
                $table->bigInteger('size')->nullable()->comment('文件大小');
                $table->string('size_info', 64)->nullable()->comment('文件大小有单位');
                $table->string('hash', 64)->nullable()->comment('文件hash');
                $table->string('filename', 255)->nullable()->comment('文件名称');
                $table->string('original_filename', 255)->nullable()->comment('原始文件名');
                $table->longText('base_path')->nullable()->comment('基础存储路径');
                $table->longText('path')->nullable()->comment('存储路径');
                $table->string('ext', 32)->nullable()->comment('文件扩展名');
                $table->string('content_type', 100)->nullable()->comment('MIME类型');
                $table->string('platform', 32)->nullable()->comment('存储平台');
                $table->string('th_url', 255)->nullable()->comment('缩略图访问路径');
                $table->string('th_filename', 255)->nullable()->comment('缩略图文件名');
                $table->bigInteger('th_size')->nullable()->comment('缩略图大小');
                $table->string('th_size_info', 64)->nullable()->comment('缩略图大小有单位');
                $table->string('th_content_type', 32)->nullable()->comment('缩略图MIME类型');
                $table->string('object_id', 32)->nullable()->comment('文件所属对象id');
                $table->string('object_type', 32)->nullable()->comment('文件所属对象类型');
                $table->text('attr')->nullable()->comment('附加属性');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('created_by')->nullable()->comment('创建用户');
                $table->bigInteger('updated_by')->nullable()->comment('更新用户');
            });
        }

        // ====== 系统内置：记事本 ======
        if (!$schema->hasTable('sys_notepad_folder')) {
            $schema->create('sys_notepad_folder', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->bigInteger('pid')->default(0)->comment('父级ID,0为顶级');
                $table->bigInteger('user_id')->default(0)->comment('用户ID');
                $table->bigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->string('name', 100)->default('')->comment('文件夹名称');
                $table->string('icon', 100)->nullable()->comment('图标');
                $table->integer('sort')->default(0)->comment('排序');
                $table->integer('doc_count')->default(0)->comment('文档数量');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->index(['user_id', 'pid'], 'idx_user_pid');
                $table->index(['user_id', 'sort'], 'idx_user_sort');
                $table->index('tenant_id', 'idx_tenant_id');
            });
        }

        if (!$schema->hasTable('sys_notepad_document')) {
            $schema->create('sys_notepad_document', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->bigInteger('folder_id')->default(0)->comment('所属文件夹ID');
                $table->bigInteger('user_id')->default(0)->comment('用户ID');
                $table->bigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->string('title', 255)->default('未命名文档')->comment('文档标题');
                $table->longText('content')->nullable()->comment('文档内容(Markdown)');
                $table->longText('content_html')->nullable()->comment('文档内容(HTML)');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->index(['user_id', 'folder_id'], 'idx_user_folder');
                $table->index('updated_at', 'idx_updated_at');
                $table->index('tenant_id', 'idx_tenant_id');
            });
        }

        // ====== 系统内置：插件 ======
        if (!$schema->hasTable('sys_plugin')) {
            $schema->create('sys_plugin', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('title', 128)->comment('插件标题');
                $table->longText('icon')->nullable()->comment('插件图标');
                $table->string('key', 64)->unique()->comment('插件标识');
                $table->string('desc', 255)->nullable()->comment('插件描述');
                $table->tinyInteger('status')->default(1)->comment('状态: 1启用 0禁用');
                $table->string('author', 64)->nullable()->comment('作者');
                $table->string('version', 20)->comment('插件版本');
                $table->string('type', 32)->default('custom')->comment('插件类型');
                $table->longText('cover')->nullable()->comment('插件封面');
                $table->longText('variables')->nullable()->comment('插件变量配置');
                $table->string('support_app', 32)->default('admin')->comment('支持的终端');
                $table->bigInteger('installed_at')->nullable()->comment('安装时间');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->index('key');
            });
        }

        if (!$schema->hasTable('sys_plugin_log')) {
            $schema->create('sys_plugin_log', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('action', 32)->comment('操作类型');
                $table->string('key', 64)->comment('插件标识');
                $table->string('pre_upgrade_version', 20)->nullable()->comment('升级前版本');
                $table->string('post_upgrade_version', 20)->nullable()->comment('升级后版本');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->index('key');
                $table->index('action');
            });
        }



        // ====== 系统内置：消息中心 ======
        if (!$schema->hasTable('sys_message_category')) {
            $schema->create('sys_message_category', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->bigInteger('pid')->default(0)->comment('父级ID(0=顶级)');
                $table->string('key', 50)->comment('分类标识');
                $table->string('name', 100)->comment('分类名称');
                $table->string('icon', 50)->nullable()->comment('图标');
                $table->string('description', 255)->nullable()->comment('描述');
                $table->integer('sort')->default(0)->comment('排序');
                $table->integer('level')->default(0)->comment('层级(0=顶级)');
                $table->string('path', 255)->default('')->comment('路径(如: 0-1-2)');
                $table->tinyInteger('is_show')->default(1)->comment('是否显示');
                $table->tinyInteger('is_system')->default(1)->comment('是否系统内置: 1-是 0-否');
                $table->tinyInteger('enabled')->default(1)->comment('是否启用: 1-是 0-否');
                $table->bigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->unique('key', 'uk_key');
                $table->index('pid', 'idx_pid');
                $table->index('tenant_id', 'idx_tenant_id');
            });
        }

        if (!$schema->hasTable('sys_message_definition')) {
            $schema->create('sys_message_definition', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->bigInteger('category_id')->comment('所属分类ID');
                $table->string('key', 50)->comment('定义标识(同一分类内唯一)');
                $table->string('name', 100)->comment('定义名称');
                $table->string('description', 255)->nullable()->comment('描述');
                $table->tinyInteger('default_on')->default(1)->comment('默认是否开启订阅: 1-开启 0-关闭');
                $table->string('nav_type', 20)->nullable()->comment('导航类型: router-内部路由 url-外部链接 none-无导航');
                $table->string('nav_value', 500)->nullable()->comment('导航值: 路由路径或URL');
                $table->integer('sort')->default(0)->comment('排序');
                $table->tinyInteger('is_system')->default(1)->comment('是否系统内置: 1-是 0-否');
                $table->tinyInteger('enabled')->default(1)->comment('是否启用: 1-是 0-否');
                $table->bigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->unique(['category_id', 'key'], 'uk_category_definition');
                $table->index('category_id', 'idx_category_id');
                $table->index('tenant_id', 'idx_tenant_id');
            });
        }

        if (!$schema->hasTable('sys_message_template')) {
            $schema->create('sys_message_template', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->string('type', 30)->default('system')->comment('模板类型: system-系统内 sms-短信 email-邮件 webhook-webhook');
                $table->string('template_id', 100)->nullable()->comment('外部模板ID(如短信模板ID)');
                $table->string('title', 200)->nullable()->comment('消息标题(模板)');
                $table->text('content_template')->nullable()->comment('内容模板');
                $table->string('button_template', 500)->nullable()->comment('按钮模板(JSON)');
                $table->string('url', 500)->nullable()->comment('PC端跳转链接');
                $table->string('uni_url', 500)->nullable()->comment('移动端跳转链接');
                $table->string('webhook_url', 500)->nullable()->comment('Webhook地址');
                $table->string('image', 255)->nullable()->comment('消息图片');
                $table->tinyInteger('enabled')->default(1)->comment('开启状态: 1-开启 0-关闭');
                $table->tinyInteger('push_rule')->default(0)->comment('推送规则: 0-即时推送 1-延迟推送');
                $table->integer('minute')->default(0)->comment('延迟推送分钟数');
                $table->tinyInteger('is_system')->default(1)->comment('是否系统内置: 1-是 0-否');
                $table->bigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->index('tenant_id', 'idx_tenant_id');
            });
        }

        if (!$schema->hasTable('sys_message_definition_template')) {
            $schema->create('sys_message_definition_template', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('主键');
                $table->bigInteger('definition_id')->comment('消息定义ID');
                $table->bigInteger('template_id')->comment('消息模板ID');
                $table->bigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->unique(['definition_id', 'template_id'], 'uk_definition_template');
                $table->index('definition_id', 'idx_definition_id');
                $table->index('template_id', 'idx_template_id');
            });
        }

        if (!$schema->hasTable('sys_message')) {
            $schema->create('sys_message', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->bigInteger('definition_id')->nullable()->comment('消息定义ID');
                $table->bigInteger('category_id')->nullable()->comment('分类ID(冗余,快速聚合)');
                $table->string('title', 200)->comment('消息标题');
                $table->text('content')->nullable()->comment('消息内容');
                $table->bigInteger('sender_id')->nullable()->comment('发送者ID');
                $table->bigInteger('receiver_id')->comment('接收者ID');
                $table->string('status', 20)->default('unread')->comment('状态: unread-未读 read-已读');
                $table->integer('priority')->default(0)->comment('优先级: 0-低 1-中 2-高 3-紧急');
                $table->string('channel', 50)->nullable()->comment('发送渠道');
                $table->bigInteger('related_id')->nullable()->comment('关联业务ID');
                $table->string('related_type', 50)->nullable()->comment('关联业务类型');
                $table->string('action_url', 500)->nullable()->comment('跳转链接');
                $table->text('action_params')->nullable()->comment('跳转参数(JSON)');
                $table->text('extra_data')->nullable()->comment('扩展数据(JSON)');
                $table->string('message_uuid', 64)->nullable()->comment('消息唯一标识');
                $table->integer('read_at')->nullable()->comment('读取时间戳');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->integer('expired_at')->nullable()->comment('过期时间戳');
                $table->bigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->index('receiver_id', 'idx_receiver_id');
                $table->index(['receiver_id', 'status'], 'idx_receiver_status');
                $table->index('definition_id', 'idx_definition_id');
                $table->index('category_id', 'idx_category_id');
                $table->index('sender_id', 'idx_sender_id');
            });
        }

        if (!$schema->hasTable('sys_message_subscribe')) {
            $schema->create('sys_message_subscribe', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('主键');
                $table->bigInteger('user_id')->comment('用户ID');
                $table->bigInteger('definition_id')->comment('消息定义ID（有记录=该用户已退订此消息）');
                $table->bigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');
                $table->unique(['user_id', 'definition_id'], 'uk_user_definition');
                $table->index('user_id', 'idx_user_id');
                $table->index('definition_id', 'idx_definition_id');
            });
        }

        // 移除 sys_admin 表中的 admin_type 字段（已迁移到 sys_admin_type 表）
        if ($schema->hasTable('sys_admin') && $schema->hasColumn('sys_admin', 'admin_type')) {
            $schema->table('sys_admin', function (Blueprint $table) {
                $table->dropColumn('admin_type');
                $table->dropIndex('idx_admin_type');
            });
        }

        // 租户迁移执行记录表
        $tablePrefix = config('admin.database.table_prefix');
        $tenantMigrationTable = $tablePrefix . 'tenant_migration';
        if (!$schema->hasTable($tenantMigrationTable)) {
            $schema->create($tenantMigrationTable, function (Blueprint $table) use ($tenantMigrationTable) {
                $table->bigInteger('id')->unsigned()->primary()->comment('主键ID');
                $table->unsignedBigInteger('tenant_id')->comment('租户ID');
                $table->string('type', 32)->comment('操作类型: migrate/install/uninstall/upgrade');
                $table->string('target', 128)->comment('操作目标: 迁移文件名或 pluginName:version');
                $table->unsignedInteger('batch')->comment('批次号');
                $table->string('status', 16)->comment('状态: pending/running/success/failed');
                $table->string('version_before', 20)->nullable()->comment('操作前版本');
                $table->string('version_after', 20)->nullable()->comment('操作后版本');
                $table->text('error_message')->nullable()->comment('失败时的错误信息');
                $table->unsignedInteger('started_at')->nullable()->comment('开始时间');
                $table->unsignedInteger('finished_at')->nullable()->comment('完成时间');
                $table->unsignedInteger('deleted_at')->nullable()->comment('删除时间');
                $table->unsignedInteger('created_at')->comment('创建时间');
                $table->unsignedInteger('updated_at')->comment('更新时间');

                $table->index(['tenant_id', 'type', 'target'], $tenantMigrationTable . '_tenant_type_target_index');
                $table->index(['tenant_id', 'status'], $tenantMigrationTable . '_tenant_status_index');
                $table->index(['type', 'status'], $tenantMigrationTable . '_type_status_index');
            });
        }

        echo "Created all system tables.\n";
    }

    public function down(Builder $schema): void
    {
        // 系统
        $schema->dropIfExists('sys_upload');
        $schema->dropIfExists('sys_role_scope_dept');
        $schema->dropIfExists('sys_role_menu');
        $schema->dropIfExists('sys_role_dept');
        $schema->dropIfExists('sys_role');
        $schema->dropIfExists('sys_recycle_bin');
        $schema->dropIfExists('sys_rate_restrictions');
        $schema->dropIfExists('sys_rate_limiter');
        $schema->dropIfExists('saas_tenant_plugin');
        $schema->dropIfExists('sys_plugin_log');
        $schema->dropIfExists('sys_plugin');
        $schema->dropIfExists('sys_post');
        $schema->dropIfExists('sys_operate_log');
        $schema->dropIfExists('sys_notepad_document');
        $schema->dropIfExists('sys_notepad_folder');
        $schema->dropIfExists('sys_notice');
        $schema->dropIfExists('sys_message_template');
        $schema->dropIfExists('sys_message_subscribe');
        $schema->dropIfExists('sys_message_definition_template');
        $schema->dropIfExists('sys_message_definition');
        $schema->dropIfExists('sys_message_category');
        $schema->dropIfExists('sys_message');
        $schema->dropIfExists('sys_menu');
        $schema->dropIfExists('sys_login_log');
        $schema->dropIfExists('sys_dict_item');
        $schema->dropIfExists('sys_dict');
        $schema->dropIfExists('sys_dept_leader');
        $schema->dropIfExists('sys_dept');
        $schema->dropIfExists('sys_crontab_log');
        $schema->dropIfExists('sys_crontab');
        $schema->dropIfExists('sys_config');
        $schema->dropIfExists('sys_admin_role');
        $schema->dropIfExists('sys_admin_post');
        $schema->dropIfExists('sys_admin_dept');
        $schema->dropIfExists('sys_admin_main');
        $schema->dropIfExists('sys_admin_type_rel');
        $schema->dropIfExists('sys_admin_type');
        $schema->dropIfExists('sys_admin');

        $tablePrefix = config('admin.database.table_prefix');
        $schema->dropIfExists($tablePrefix . 'tenant_migration');
    }
};
