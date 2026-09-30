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
namespace app\model\tenant;

use Carbon\Carbon;
use app\enum\platform\TenantSubscriptionStatus;
use core\foundation\base\SystemModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 租户模型
 * 系统模型，不受租户隔离影响
 * 用于存储和管理所有租户信息
 *
 * @property int      $id              租户ID
 * @property string   $name            租户名称
 * @property string   $code            租户编码
 * @property string   $status          状态: active/suspended/cancelled
 * @property string   $effective_mode  生效方式: immediate-永久生效 specified-指定时间
 * @property datetime $start_time      生效时间
 * @property string   $database_mode   数据库隔离模式: field/database
 * @property int      $db_setting_id   关联数据源ID
 * @property string   $database_name   独立数据库名
 * @property string   $domain          绑定域名
 * @property string   $contact_name    联系人
 * @property string   $contact_phone   联系电话
 * @property string   $contact_email   联系邮箱
 * @property string   $contact_address 联系地址
 * @property string   $system_name     系统名称
 * @property datetime $expire_time     到期时间
 * @property int      $sort            排序
 * @property array    $settings        租户配置
 * @property int      $create_time     创建时间
 * @property int      $update_time     更新时间
 * @property int      $delete_time     删除时间
 */
class Tenant extends SystemModel
{
    use SoftDeletes;

    /**
     * 数据库表名
     *
     * @var string
     */
    protected $table = 'saas_tenant';

    /**
     * 主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 自动类型转换
     *
     * @var array
     */
    protected $casts = [
        'db_setting_id'  => 'string',
        'expire_time'    => 'integer',
        'settings'       => 'array',
        'sort'           => 'integer',
        'start_time'     => 'integer',
        'subscription_id' => 'string',
        'suspend_time'   => 'integer',
    ];

    /**
     * 可填充字段
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'status',
        'effective_mode',
        'start_time',
        'database_mode',
        'subscription_id',
        'db_setting_id',
        'database_name',
        'domain',
        'contact_name',
        'contact_phone',
        'contact_email',
        'contact_address',
        'system_name',
        'expire_time',
        'sort',
        'settings',
        'suspend_reason',
        'suspend_time',
    ];

    /**
     * 隐藏字段
     *
     * @var array
     */
    protected $hidden = [
        'suspend_reason',
    ];

    /**
     * 默认预加载关联
     *
     * @var array
     */
    protected $with = ['dbSetting'];

    /**
     * 状态常量
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * 隔离模式常量
     */
    const MODE_FIELD = 'field';
    const MODE_DATABASE = 'database';

    /**
     * 生效方式常量
     */
    const EFFECTIVE_IMMEDIATE = 'immediate';
    const EFFECTIVE_SPECIFIED = 'specified';

    /**
     * 状态映射
     *
     * @var array
     */
    public static $statusMap = [
        self::STATUS_ACTIVE    => '正常',
        self::STATUS_SUSPENDED => '已暂停',
        self::STATUS_CANCELLED => '已注销',
    ];

    /**
     * 隔离模式映射
     *
     * @var array
     */
    public static $modeMap = [
        self::MODE_FIELD    => '字段隔离',
        self::MODE_DATABASE => '库隔离',
    ];

    /**
     * 获取状态文本
     *
     * @param string $status
     *
     * @return string
     */
    public function getStatusText(string $status): string
    {
        return self::$statusMap[$status] ?? $status;
    }

    /**
     * 获取隔离模式文本
     *
     * @param string $mode
     *
     * @return string
     */
    public function getModeText(string $mode): string
    {
        return self::$modeMap[$mode] ?? $mode;
    }

    /**
     * 检查是否活跃
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 检查是否过期
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (empty($this->expire_time)) {
            return false;
        }
        return Carbon::createFromTimestamp((int)$this->expire_time)->isPast();
    }

    /**
     * 检查是否在宽限期内
     *
     * @return bool
     */
    public function isInGracePeriod(): bool
    {
        if (empty($this->expire_time)) {
            return false;
        }

        $gracePeriod = config('tenant.subscription.grace_period', 7);
        $graceEnd    = Carbon::createFromTimestamp((int)$this->expire_time)->addDays($gracePeriod);

        return Carbon::now()->lt($graceEnd);
    }

    /**
     * 检查是否可以访问
     *
     * @return bool
     */
    public function canAccess(): bool
    {
        return $this->isActive() && (!$this->isExpired() || $this->isInGracePeriod());
    }

    /**
     * 获取租户订阅实例 - Laravel ORM
     */
    public function tenantSubscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'tenant_id', 'id');
    }

    /**
     * 关联当前生效的套餐（一对一）
     * 通过 subscription_id 外键直连
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id');
    }

    /**
     * 获取当前生效的订阅实例（兼容旧版）
     */
    public function activeSubscription()
    {
        return $this->hasOne(TenantSubscription::class, 'tenant_id', 'id')
            ->where('status', TenantSubscriptionStatus::ACTIVE->value)
            ->latest();
    }

    /**
     * 通过中间表关联套餐（多对多）- 已弃用
     * 请使用 subscription() 关联
     * @deprecated 建议使用 subscription() 一对一关联
     */
    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class, TenantSubscription::class, 'tenant_id', 'subscription_id');
    }

    /**
     * 关联数据源 - Laravel ORM
     */
    public function dbSetting(): BelongsTo
    {
        return $this->belongsTo(DbSetting::class, 'db_setting_id', 'id');
    }

    /**
     * 获取关联的套餐ID列表
     */
    public function getSubscriptionIds(): array
    {
        return $this->tenantSubscriptions()->pluck('subscription_id')->toArray();
    }

    /**
     * 根据编码查找
     *
     * @param string $code
     *
     * @return static|null
     */
    public static function findByCode(string $code): ?self
    {
        return self::withoutGlobalScopes()
            ->where('code', $code)
            ->first();
    }

    /**
     * 根据域名查找
     *
     * @param string $domain
     *
     * @return static|null
     */
    public static function findByDomain(string $domain): ?self
    {
        return self::withoutGlobalScopes()
            ->where('domain', $domain)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }

    /**
     * 获取活跃租户列表
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getActiveTenants()
    {
        return self::withoutGlobalScopes()
            ->where('status', self::STATUS_ACTIVE)
            ->get();
    }

    /**
     * 获取即将到期的租户
     *
     * @param int $days
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getExpiringTenants(int $days = 7)
    {
        return self::withoutGlobalScopes()
            ->where('status', self::STATUS_ACTIVE)
            ->where('expire_time', '<=', Carbon::now()->addDays($days)->timestamp)
            ->where('expire_time', '>', Carbon::now()->timestamp)
            ->get();
    }

    /**
     * 获取已过期租户
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getExpiredTenants()
    {
        return self::withoutGlobalScopes()
            ->where('status', self::STATUS_ACTIVE)
            ->where('expire_time', '<', Carbon::now()->timestamp)
            ->get();
    }

    /**
     * 验证租户代码格式
     *
     * @param string $code
     *
     * @return bool
     */
    public static function validateCode(string $code): bool
    {
        $pattern = config('tenant.security.tenant_id_pattern', '/^[a-zA-Z0-9_-]{1,64}$/');
        return preg_match($pattern, $code) === 1;
    }

    /**
     * 获取配置项
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? [];
        return $settings[$key] ?? $default;
    }

    /**
     * 设置配置项
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setSetting(string $key, $value): self
    {
        $settings       = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
        return $this;
    }
}
