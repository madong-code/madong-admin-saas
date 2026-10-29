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

namespace app\service\admin\web;

use app\service\admin\system\config\ConfigService;

/**
 * 站点设置（web 前台门户；后台「网站设置→站点设置」页读写 sys_config web_site_setting）
 *
 * 与 ERP 后台管理系统自身的站点信息（site_setting 行，系统配置→站点设置）分离存储、互不影响。
 * 注意：ConfigService::update() 是整包覆盖，保存必须「读当前 content → 合并白名单键 → 整体写回」，
 * 否则会清掉同行的 membership_enabled（portal 插件独占写）等非本页键。
 *
 * 多租户差异：
 *   - sys_config 走 TenantScope，读写只落在当前登录租户；写后需清空进程缓存的全部租户桶
 *     （见 \app\service\api\site\SiteConfigService::resetCache），避免其它租户残留旧值。
 */
class SiteConfigService extends \core\foundation\base\BaseService
{
    public const CONFIG_CODE = 'web_site_setting';
    public const GROUP_CODE  = 'default';

    /** 本页允许保存的 14 键（存储键名；site_open/site_url 为 web 前台门户开关与站点地址） */
    private const ALLOWED_KEYS = [
        'site_open', 'site_url',
        'site_name', 'site_logo', 'contact_email',
        'site_copyright', 'site_record_no', 'site_icp_url',
        'site_network_security', 'site_network_security_url',
        'show_apply', 'show_powered',
        'tongji_enabled', 'tongji_key',
    ];

    public function __construct(protected ConfigService $configService)
    {
    }

    /**
     * 读取本页 12 键（存储键原样返回，供后台表单回填）
     */
    public function get(): array
    {
        try {
            $saved = $this->configService->config(self::CONFIG_CODE, [], ['group_code' => self::GROUP_CODE]);
        } catch (\Throwable) {
            $saved = [];
        }
        $saved = is_array($saved) ? $saved : [];

        $cfg = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $cfg[$key] = $saved[$key] ?? null;
        }

        // 类型规整 + 默认值
        $cfg['site_open']                 = (int) (is_bool($saved['site_open'] ?? null) ? ($saved['site_open'] ? 1 : 0) : ($saved['site_open'] ?? 1));
        $cfg['site_url']                  = trim((string) ($cfg['site_url'] ?? ''));
        $cfg['site_name']                 = trim((string) ($cfg['site_name'] ?? ''));
        $cfg['site_logo']                 = (string) ($cfg['site_logo'] ?? '');
        $cfg['contact_email']             = trim((string) ($cfg['contact_email'] ?? ''));
        $cfg['site_copyright']            = (string) ($cfg['site_copyright'] ?? '');
        $cfg['site_record_no']            = (string) ($cfg['site_record_no'] ?? '');
        $cfg['site_icp_url']              = trim((string) ($cfg['site_icp_url'] ?? '')) ?: 'https://beian.miit.gov.cn/';
        $cfg['site_network_security']     = (string) ($cfg['site_network_security'] ?? '');
        $cfg['site_network_security_url'] = trim((string) ($cfg['site_network_security_url'] ?? ''));
        $cfg['show_apply']                = (bool) ($cfg['show_apply'] ?? true);
        $cfg['show_powered']              = (bool) ($cfg['show_powered'] ?? true);
        $cfg['tongji_key']                = (string) preg_replace('/[^a-z0-9]/i', '', (string) ($cfg['tongji_key'] ?? ''));
        $cfg['tongji_enabled']            = (bool) ($cfg['tongji_enabled'] ?? false);
        if ($cfg['tongji_key'] === '') {
            $cfg['tongji_enabled'] = false;
        }

        return $cfg;
    }

    /**
     * 白名单 merge 保存（不动 site_setting 其余键）；写后重置前台映射缓存
     */
    public function save(array $input): array
    {
        $filtered = array_intersect_key($input, array_flip(self::ALLOWED_KEYS));
        if ($filtered === []) {
            return $this->get();
        }

        foreach (['show_apply', 'show_powered', 'tongji_enabled'] as $boolKey) {
            if (array_key_exists($boolKey, $filtered)) {
                $filtered[$boolKey] = $this->asBool($filtered[$boolKey]);
            }
        }
        if (array_key_exists('site_open', $filtered)) {
            $filtered['site_open'] = $this->asBool($filtered['site_open']) ? 1 : 0;
        }
        foreach (['site_name' => 100, 'site_copyright' => 255, 'site_record_no' => 100, 'site_network_security' => 100] as $key => $max) {
            if (isset($filtered[$key]) && is_string($filtered[$key])) {
                $filtered[$key] = mb_substr(trim($filtered[$key]), 0, $max);
            }
        }
        foreach (['site_logo' => 255, 'site_icp_url' => 255, 'site_network_security_url' => 255, 'contact_email' => 100] as $key => $max) {
            if (isset($filtered[$key]) && is_string($filtered[$key])) {
                $filtered[$key] = mb_substr(trim($filtered[$key]), 0, $max);
            }
        }
        // 百度统计 Key：只保留字母数字，截断 64 位；无 Key 不允许开启
        if (isset($filtered['tongji_key']) && is_string($filtered['tongji_key'])) {
            $filtered['tongji_key'] = substr((string) preg_replace('/[^a-z0-9]/i', '', $filtered['tongji_key']), 0, 64);
        }
        if (($filtered['tongji_key'] ?? '') === '') {
            $filtered['tongji_enabled'] = false;
        }

        try {
            $current = $this->configService->config(self::CONFIG_CODE, [], ['group_code' => self::GROUP_CODE]);
        } catch (\Throwable) {
            $current = [];
        }
        $merged = array_merge(is_array($current) ? $current : [], $filtered);

        $this->configService->update(
            self::CONFIG_CODE,
            $merged,
            ['group_code' => self::GROUP_CODE, 'name' => '站点设置（前台门户）'],
        );

        // 重置前台映射缓存（按租户分桶，整体清空）；portal 插件在场时一并重置（其读取同一 web_site_setting 行）
        \app\service\api\site\SiteConfigService::resetCache();
        if (class_exists(\plugin\portal\app\service\SiteConfigService::class)) {
            \plugin\portal\app\service\SiteConfigService::resetCache();
        }

        return $this->get();
    }

    private function asBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array($value, [1, '1', 'true', 'on', 'yes'], true);
    }
}
