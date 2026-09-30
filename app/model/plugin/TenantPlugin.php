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
use Illuminate\Database\Eloquent\Relations\HasOne;
use app\model\plugin\TenantPluginInstall;

/**
 * 租户插件记录
 *
 * WP1 扩展: 加列 sync_status / isolation_mode / deprecated_version;
 *           version 默认 '1.0.0' 兜底。
 *
 * @property int    $id                 主键
 * @property int    $tenant_id          租户ID
 * @property string $tenant_name        租户名(冗余)
 * @property string $plugin_key         插件唯一标识
 * @property string $version            安装版本(空值补 1.0.0)
 * @property string $auth_status        授权状态
 * @property int    $status             1启用 0停用
 * @property int    $is_purchased       0=未购买 1=已购买
 * @property int    $purchased_at       授权时间
 * @property int    $expires_at         过期时间(null=永不过期)
 * @property string $config             租户级配置(JSON)
 * @property int    $installed_at       安装时间
 * @property string $sync_status        pending|running|success|failed
 * @property string $isolation_mode     field|database
 * @property string $deprecated_version 菜单废弃时记录的旧版本
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantPlugin extends SystemModel
{
    protected $table = 'saas_tenant_plugin';

    /**
     * 关联安装运行态记录(1:1, 按 id 关联)
     *
     * 拆分迁移把运行态写入 saas_tenant_plugin_install 时复用了授权记录的雪花 ID,
     * 因此两表主键 id 一一对应, 用 id 做外键最稳妥(标准单外键, 预加载不会触发
     * 跨表列比较导致的 Unknown column 错误)。
     */
    public function install(): HasOne
    {
        return $this->hasOne(TenantPluginInstall::class, 'id', 'id');
    }

    /**
     * 运行态字段代理: 优先读 saas_tenant_plugin_install, 过渡期 fallback 到本表旧列
     */
    public function getStatusAttribute($value): mixed
    {
        return $this->install ? $this->install->status : $value;
    }

    public function getInstalledAtAttribute($value): mixed
    {
        return $this->install ? $this->install->installed_at : $value;
    }

    public function getSyncStatusAttribute($value): mixed
    {
        return $this->install ? $this->install->sync_status : $value;
    }

    public function getIsolationModeAttribute($value): mixed
    {
        return $this->install ? $this->install->isolation_mode : $value;
    }

    public function getVersionAttribute($value): mixed
    {
        return $this->install ? $this->install->version : $value;
    }

    public function getDeprecatedVersionAttribute($value): mixed
    {
        return $this->install ? $this->install->deprecated_version : $value;
    }

    public function getConfigAttribute($value): mixed
    {
        return $this->install ? $this->install->config : $value;
    }

    /**
     * 始终使用主库连接
     *
     * saas_tenant_plugin 是平台级共享授权账本(平台授权列表/占用清单/CLI 均读写主库)。
     * 继承 SystemModel 后:
     *   - getConnectionName() 对 SystemModel 实例固定返回 'mysql', 不被租户库隔离切换影响;
     *   - TenantScope::isWhitelistedModel() 对 SystemModel 直接豁免, 不会自动追加
     *     tenant_id 过滤, 可正确查询"所有租户"的授权记录(tenant_id 仅作业务字段)。
     */

    public $timestamps = true;

    protected $appends = ['created_date', 'updated_date'];

    protected $dateFormat = 'U';

    protected $fillable = [
        'id',
        'tenant_id',
        'tenant_name',
        'plugin_key',
        'version',
        'auth_status',
        'status',
        'is_purchased',
        'purchased_at',
        'expires_at',
        'config',
        'installed_at',
        'sync_status',
        'isolation_mode',
        'deprecated_version',
        'allow_upgrade',
        'ignored_version',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'            => 'string',
        'tenant_id'     => 'string',
        'is_purchased'  => 'integer',
        'status'        => 'integer',
        'allow_upgrade' => 'integer',
    ];

    /**
     * 纯版本比较: 目标版本是否高于当前版本
     */
    public function isUpgradable(string $latestVersion): bool
    {
        if (!$this->version) {
            return true;
        }
        return version_compare($latestVersion, $this->version, '>');
    }

    /**
     * 平台端是否允许该租户升级此插件
     */
    public function isUpgradeAllowed(): bool
    {
        return (int)($this->allow_upgrade ?? 1) === 1;
    }

    /**
     * 租户端是否忽略了该版本的升级
     */
    public function isVersionIgnored(?string $version): bool
    {
        if ($version === null || $version === '') {
            return false;
        }
        return (string)($this->ignored_version ?? '') === (string)$version;
    }

    /**
     * 综合升级判定(平台开关 + 租户忽略 + 版本比较)
     *
     * @return array{0: bool, 1: string} [是否可升级, 原因码] 原因码: upgrade_disabled|version_ignored|up_to_date|''
     */
    public function canUpgrade(string $latestVersion): array
    {
        if (!$this->isUpgradeAllowed()) {
            return [false, 'upgrade_disabled'];
        }
        if ($this->isVersionIgnored($latestVersion)) {
            return [false, 'version_ignored'];
        }
        if (!$this->isUpgradable($latestVersion)) {
            return [false, 'up_to_date'];
        }
        return [true, ''];
    }
}
