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
 * Official Website: https://madong.tech
 */

namespace app\model\plugin;

use core\foundation\base\SystemModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 租户插件安装运行记录
 *
 * 与 saas_tenant_plugin(授权治理) 通过 (tenant_id, plugin_key) 构成 1:1 运行态关系。
 * 仅承载"安装运行"类字段: status/installed_at/sync_status/isolation_mode/version/deprecated_version/config。
 *
 * @property int    $id               主键(复用授权记录 id)
 * @property int    $tenant_id        租户ID
 * @property string $tenant_name      租户名(冗余)
 * @property string $plugin_key       插件唯一标识
 * @property int    $status           1启用 0停用
 * @property int    $installed_at     安装时间
 * @property string $sync_status      pending|running|success|failed
 * @property string $isolation_mode   field|database
 * @property string $version          安装版本
 * @property string $deprecated_version 菜单废弃时记录的旧版本
 * @property string $config           租户级配置(JSON)
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantPluginInstall extends SystemModel
{
    protected $table = 'saas_tenant_plugin_install';

    /**
     * 始终使用主库连接
     *
     * 平台级共享运行态账本, 逻辑同 TenantPlugin(继承 SystemModel 主库 + 豁免 TenantScope)。
     */
    public $timestamps = true;

    protected $appends = ['created_date', 'updated_date'];

    protected $dateFormat = 'U';

    protected $fillable = [
        'id',
        'tenant_id',
        'tenant_name',
        'plugin_key',
        'status',
        'installed_at',
        'sync_status',
        'isolation_mode',
        'version',
        'deprecated_version',
        'config',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'         => 'string',
        'tenant_id'  => 'string',
        'status'     => 'integer',
    ];

    /**
     * 关联授权治理记录(1:1, 按 id 关联)
     *
     * 与 TenantPlugin.install() 对称: 两表主键 id 一一对应(拆分迁移复用了同一雪花 ID)。
     */
    public function auth(): BelongsTo
    {
        return $this->belongsTo(TenantPlugin::class, 'id', 'id');
    }
}
