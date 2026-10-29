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

namespace app\service\api\site;

use app\service\api\system\ConfigService;
use core\business\tenant\context\TenantContext;

/**
 * 站点全局配置（web 前台门户）
 *
 * 存储：sys_config group_code=default / code=web_site_setting（「网站设置→站点设置」权威行，
 *       与 ERP 后台的 site_setting 行分离：后台管理系统站点信息走 系统配置→站点设置），
 *       membership_enabled 键由 portal 插件独占写入，此处只读透出。
 * 输出：存储键 → 前台 WebSiteConfig 结构（site_copyright → copyright、site_record_no → icp_number 等），
 *       web 前端类型与取数消费方零改动。
 * 缓存：进程内静态缓存，后台/插件保存后调用 resetCache() 重置。
 *       多租户差异：workerman 常驻进程复用，缓存必须按 tenantId 分桶，
 *       否则先访问的租户配置会串给后续租户。
 */
class SiteConfigService extends \core\foundation\base\BaseService
{
    public const CONFIG_CODE = 'web_site_setting';
    public const GROUP_CODE  = 'default';

    /** 存储键 → 前台输出键（site_setting 其余键如 site_open、seo_* 、share_image 不对外） */
    private const MAP = [
        'site_name'                 => 'site_name',
        'site_logo'                 => 'site_logo',
        'contact_email'             => 'contact_email',
        'site_copyright'            => 'copyright',
        'site_record_no'            => 'icp_number',
        'site_icp_url'              => 'icp_url',
        'site_network_security'     => 'police_number',
        'site_network_security_url' => 'police_url',
        'show_apply'                => 'show_apply',
        'show_powered'              => 'show_powered',
        'tongji_enabled'            => 'tongji_enabled',
        'tongji_key'                => 'tongji_key',
        'membership_enabled'        => 'membership_enabled',
    ];

    /**
     * 进程内缓存（tenantKey => 配置数组）
     * @var array<string, array>
     */
    private static array $cache = [];

    public function __construct(protected ConfigService $configService)
    {
    }

    /**
     * 读取并映射为前台 WebSiteConfig 结构（按租户分桶的进程静态缓存）
     */
    public function get(): array
    {
        $cacheKey = self::cacheKey();
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        try {
            $saved = $this->configService->config(self::CONFIG_CODE, [], ['group_code' => self::GROUP_CODE]);
        } catch (\Throwable) {
            $saved = [];
        }
        $saved = is_array($saved) ? $saved : [];

        $cfg = [];
        foreach (self::MAP as $storageKey => $outKey) {
            $cfg[$outKey] = $saved[$storageKey] ?? null;
        }

        // 类型规整 + 默认值（历史脏数据 / 未迁移新装容错）
        $cfg['site_name']     = trim((string) ($cfg['site_name'] ?? ''));
        $cfg['site_logo']     = (string) ($cfg['site_logo'] ?? '');
        $cfg['copyright']     = (string) ($cfg['copyright'] ?? '');
        $cfg['icp_number']    = (string) ($cfg['icp_number'] ?? '');
        $cfg['icp_url']       = trim((string) ($cfg['icp_url'] ?? '')) ?: 'https://beian.miit.gov.cn/';
        $cfg['police_number'] = (string) ($cfg['police_number'] ?? '');
        $cfg['police_url']    = trim((string) ($cfg['police_url'] ?? '')) ?: 'https://beian.mps.gov.cn/';
        $cfg['show_apply']    = (bool) ($cfg['show_apply'] ?? true);
        $cfg['show_powered']  = (bool) ($cfg['show_powered'] ?? true);
        $cfg['contact_email'] = trim((string) ($cfg['contact_email'] ?? ''));
        // 百度统计 Key 仅允许字母数字（防注入到前台 script src）；无 Key 时开关不生效
        $cfg['tongji_key']     = (string) preg_replace('/[^a-z0-9]/i', '', (string) ($cfg['tongji_key'] ?? ''));
        $cfg['tongji_enabled'] = (bool) ($cfg['tongji_enabled'] ?? false);
        if ($cfg['tongji_key'] === '') {
            $cfg['tongji_enabled'] = false;
        }
        $cfg['membership_enabled'] = (bool) ($cfg['membership_enabled'] ?? false);

        return self::$cache[$cacheKey] = $cfg;
    }

    /**
     * 缓存分桶标识：多租户按 tenantId 隔离，单体模式统一 default
     */
    private static function cacheKey(): string
    {
        if (TenantContext::isSingleMode() || !TenantContext::isInitialized()) {
            return 'default';
        }
        $tenantId = TenantContext::getTenantId();
        return $tenantId === null ? 'default' : (string) $tenantId;
    }

    /**
     * 重置缓存（不传 tenantId 时清空全部，避免后台保存后其它租户桶仍是旧值）
     */
    public static function resetCache(int|string|null $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$cache = [];
            return;
        }
        unset(self::$cache[(string) $tenantId]);
    }
}
