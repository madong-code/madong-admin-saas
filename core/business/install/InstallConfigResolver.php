<?php
declare(strict_types=1);

/**
 *+------------------
 * madong
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 * Official Website: http://www.madong.tech
 */

namespace core\business\install;

/**
 * 安装配置解析器
 *
 * 统一解析「运行模式 → 模板清单 → 物理路径 → 插件落盘目录」，供系统安装
 * （InstallService）与插件安装（PluginInstallService）共用，消除两处各自
 * 维护 template 路径映射的重复。
 *
 * 配置缺失时提供 fallback：默认模式 separated、默认模板 admin+platform+web，
 * 保证旧部署无 config/install.php 相关键时仍可工作。
 */
final class InstallConfigResolver
{
    /** 默认模式 */
    private const DEFAULT_MODE = 'separated';

    /** 单租户构建输出应用（仅 admin + web） */
    private const DEFAULT_SINGLE_TENANT_BUILD = ['admin', 'web'];

    /** 多租户构建输出应用（所有已定义多端应用） */
    private const DEFAULT_MULTI_TENANT_BUILD = ['admin', 'platform', 'web'];

    /** 默认模式下的模板路径映射（fallback） */
    private const DEFAULT_MODES = [
        'mono' => [
            'label' => '单仓双端',
            'templates' => [
                'admin' => 'mono/apps/admin',
                'platform' => 'mono/apps/platform',
            ],
        ],
        'separated' => [
            'label' => '分离双端',
            'templates' => [
                'admin' => 'admin',
                'platform' => 'platform',
            ],
        ],
    ];

    /** 通用模板（fallback） */
    private const DEFAULT_COMMON_TEMPLATES = ['web' => 'web'];

    /** 默认插件分发模板（fallback） */
    private const DEFAULT_DISTRIBUTION_TEMPLATES = ['admin', 'platform'];

    /** 默认 admin UI 来源（fallback）；repo 为 download.repositories 的 key */
    private const DEFAULT_ADMIN_VARIANTS = [
        'single' => ['label' => '管理端（single）', 'repo' => 'single'],
        'aura'   => ['label' => '管理端（aura）',   'repo' => 'aura'],
    ];

    /**
     * 项目根目录（backend 的上一级，即 template/ 所在目录）
     */
    private function projectRoot(): string
    {
        return dirname(base_path());
    }

    /**
     * 读取 install 配置（缺失时返回空数组，由各方法 fallback）
     */
    private function config(): array
    {
        $config = config('install', []);
        return is_array($config) ? $config : [];
    }

    /**
     * 解析运行模式
     *
     * 优先使用 install_mode.default（可视化安装向导与 CLI 共用），
     * 缺少该键时回退到 default_mode，再回退到内置默认值。
     *
     * @param string|null $mode 显式指定（--mode），为空时取配置默认值
     */
    public function resolveMode(?string $mode = null): string
    {
        $modes = $this->modes();

        if ($mode !== null && $mode !== '' && isset($modes[$mode])) {
            return $mode;
        }

        $default = $this->installModeDefault();
        if (!isset($modes[$default])) {
            $keys = array_keys($modes);
            $default = $keys[0] ?? self::DEFAULT_MODE;
        }

        return $default;
    }

    /**
     * 安装模式默认值（install_mode.default → default_mode → 内置默认）
     */
    public function installModeDefault(): string
    {
        $installMode = $this->config()['install_mode'] ?? null;
        $default = is_array($installMode) ? ($installMode['default'] ?? null) : null;
        $default ??= $this->config()['default_mode'] ?? self::DEFAULT_MODE;

        if (!is_string($default) || $default === '') {
            return self::DEFAULT_MODE;
        }

        return $default;
    }

    /**
     * 安装模式可选值枚举（mono / separated），供可视化向导渲染模式选择卡片
     *
     * @return array<string,array{label:string,description:string,templates:array,apps:array}>
     */
    public function installModeOptions(): array
    {
        $installMode = $this->config()['install_mode'] ?? null;
        $options = is_array($installMode) ? ($installMode['options'] ?? null) : null;

        if (is_array($options) && $options !== []) {
            return $options;
        }

        // 配置缺失时按 modes 生成最小可用枚举
        $fallback = [];
        foreach ($this->modes() as $mode => $definition) {
            $paths = is_array($definition['templates'] ?? null) ? $definition['templates'] : [];
            $templates = [];
            foreach ($paths as $relative) {
                $templates[] = 'template/' . trim((string) $relative, '/');
            }
            foreach ($this->commonTemplates() as $relative) {
                $templates[] = 'template/' . trim((string) $relative, '/');
            }
            $fallback[$mode] = [
                'label'       => (string) ($definition['label'] ?? $mode),
                'description' => '',
                'templates'   => array_values(array_unique($templates)),
                'apps'        => array_keys($paths),
            ];
        }

        return $fallback;
    }

    /**
     * 获取所有模式定义（含 fallback）
     */
    public function modes(): array
    {
        $modes = $this->config()['modes'] ?? null;
        if (!is_array($modes) || $modes === []) {
            return self::DEFAULT_MODES;
        }
        return $modes;
    }

    /**
     * 将 UI 展示名归一化为逻辑模板名
     *
     * single/aura 是 admin 的 UI 来源，直接按 admin_variants 的 key 推断，无需额外别名表。
     */
    public function canonicalTemplate(string $template): string
    {
        $template = trim($template);
        if (array_key_exists($template, $this->adminVariants())) {
            return 'admin';
        }

        // 兼容旧配置中单独的 template_aliases
        $aliases = $this->config()['template_aliases'] ?? [];
        if (is_array($aliases)) {
            $canonical = $aliases[$template] ?? $template;
            if (is_string($canonical) && $canonical !== '') {
                return $canonical;
            }
        }
        return $template;
    }

    /**
     * 获取 admin 的 UI 来源定义（single/aura）
     */
    public function adminVariants(): array
    {
        $download = $this->config()['download'] ?? null;
        $variants = is_array($download) ? ($download['admin_variants'] ?? null) : null;

        // 兼容旧配置中顶层的 admin_variants
        if (!is_array($variants) || $variants === []) {
            $variants = $this->config()['admin_variants'] ?? null;
        }
        if (!is_array($variants) || $variants === []) {
            return self::DEFAULT_ADMIN_VARIANTS;
        }
        return $variants;
    }

    /**
     * 解析 admin 实际使用的 UI 来源
     *
     * @param string|null $variant 显式指定（--admin-variant），为空取 download.admin_variant
     */
    public function resolveAdminVariant(?string $variant = null): string
    {
        $variants = $this->adminVariants();
        if ($variant !== null && $variant !== '' && isset($variants[$variant])) {
            return $variant;
        }

        $download = $this->config()['download'] ?? null;
        $default = is_array($download) ? ($download['admin_variant'] ?? null) : null;
        $default ??= $this->config()['default_admin_variant'] ?? 'single';
        if (!is_string($default) || !isset($variants[$default])) {
            $default = 'single';
        }
        if (!isset($variants[$default])) {
            $keys = array_keys($variants);
            $default = $keys[0] ?? 'single';
        }
        return $default;
    }

    /**
     * 解析指定模式下的模板清单（含通用模板）
     *
     * @param string|null $mode     模式，为空取默认模式
     * @param array|null  $override 显式覆盖的模板名列表（--templates），为空则按配置
     * @return string[] 模板名列表（去重、保序）
     */
    public function resolveTemplates(?string $mode = null, ?array $override = null): array
    {
        if ($override !== null) {
            $templates = array_values(array_unique(array_filter(
                array_map(fn ($value) => $this->canonicalTemplate(trim((string) $value)), $override),
                static fn ($v) => $v !== ''
            )));
            if ($templates !== []) {
                return $templates;
            }
        }

        $resolvedMode = $this->resolveMode($mode);
        $modes = $this->modes();
        $modeTemplates = $modes[$resolvedMode]['templates'] ?? [];
        $names = is_array($modeTemplates) ? array_keys($modeTemplates) : [];

        // 并入通用模板（web）
        foreach (array_keys($this->commonTemplates()) as $common) {
            if (!in_array($common, $names, true)) {
                $names[] = $common;
            }
        }

        return $names;
    }

    /**
     * 解析「需要构建并输出到 backend/public」的应用清单
     *
     * 规则（可通过 config/install.php 的 build_output 覆盖）：
     *  - 单租户：仅 admin、web
     *  - 多租户：admin、platform、web 等所有已定义多端应用
     *
     * @param bool       $tenantEnabled 是否启用多租户
     * @param string|null $mode         模式，为空取默认模式
     * @param array|null  $override     显式模板清单（只能收窄，不会突破租户规则）
     * @return string[]
     */
    public function buildTemplates(bool $tenantEnabled, ?string $mode = null, ?array $override = null): array
    {
        $candidates = $this->resolveTemplates($mode, $override);

        $buildOutput = $this->config()['build_output'] ?? null;
        $allowed = is_array($buildOutput)
            ? ($tenantEnabled ? ($buildOutput['multi_tenant'] ?? null) : ($buildOutput['single_tenant'] ?? null))
            : null;
        if (!is_array($allowed) || $allowed === []) {
            $allowed = $tenantEnabled ? self::DEFAULT_MULTI_TENANT_BUILD : self::DEFAULT_SINGLE_TENANT_BUILD;
        }

        $allowed = array_values(array_unique(array_map(
            fn ($value) => $this->canonicalTemplate((string) $value),
            $allowed
        )));

        return array_values(array_filter(
            $candidates,
            static fn (string $template): bool => in_array($template, $allowed, true)
        ));
    }

    /**
     * 生成安装计划：模式、需要构建的应用、模板物理路径、构建产物输出目录
     *
     * 供 CLI 安装摘要与可视化安装向导展示，避免前后端各自推导路径。
     *
     * @return array{mode:string,label:string,description:string,tenant_enabled:bool,templates:string[],template_paths:array<string,string>,output_dirs:array<string,string>}
     */
    public function installModePlan(string $mode, bool $tenantEnabled, ?array $override = null): array
    {
        $resolvedMode = $this->resolveMode($mode);
        $options      = $this->installModeOptions();
        $option       = is_array($options[$resolvedMode] ?? null) ? $options[$resolvedMode] : [];
        $templates    = $this->buildTemplates($tenantEnabled, $resolvedMode, $override);

        $templatePaths = [];
        $outputDirs    = [];
        foreach ($templates as $template) {
            $templatePaths[$template] = $this->templatePath($template, $resolvedMode);
            $outputDirs[$template]    = $this->templateOutputDir($template);
        }

        return [
            'mode'           => $resolvedMode,
            'label'          => (string) ($option['label'] ?? $resolvedMode),
            'description'    => (string) ($option['description'] ?? ''),
            'tenant_enabled' => $tenantEnabled,
            'templates'      => $templates,
            'template_paths' => $templatePaths,
            'output_dirs'    => $outputDirs,
        ];
    }

    /**
     * 获取通用模板映射（含 fallback）
     */
    public function commonTemplates(): array
    {
        $common = $this->config()['common_templates'] ?? null;
        if (!is_array($common) || $common === []) {
            return self::DEFAULT_COMMON_TEMPLATES;
        }
        return $common;
    }

    /**
     * 解析模板物理路径（绝对路径，无末尾斜杠）
     *
     * 优先取 modes[模式].templates[模板]，未命中取 common_templates[模板]。
     *
     * @return string 找不到时返回空字符串
     */
    public function templatePath(string $template, string $mode): string
    {
        $template = $this->canonicalTemplate($template);
        $modes = $this->modes();
        $relative = $modes[$mode]['templates'][$template] ?? null;

        if (!is_string($relative) || $relative === '') {
            $common = $this->commonTemplates();
            $relative = $common[$template] ?? null;
        }

        if (!is_string($relative) || $relative === '') {
            return '';
        }

        return rtrim($this->projectRoot() . '/template/' . trim($relative, '/'), '/');
    }

    /**
     * 解析模板构建产物的输出目录（backend/public 下的子目录）
     *
     * 优先取 template_meta.output_dir；缺失时使用逻辑模板名（admin/platform/web）。
     */
    public function templateOutputDir(string $template): string
    {
        $canonical = $this->canonicalTemplate($template);
        $meta      = $this->templateMeta($canonical);
        $relative  = $meta['output_dir'] ?? $canonical;

        return rtrim(base_path('public') . '/' . trim((string) $relative, '/'), '/');
    }

    /**
     * 解析模板元数据（label / plugin_dir / default），缺失返回安全默认值
     */
    public function templateMeta(string $template): array
    {
        $template = $this->canonicalTemplate($template);
        $meta = $this->config()['template_meta'] ?? null;
        if (is_array($meta) && isset($meta[$template]) && is_array($meta[$template])) {
            return $meta[$template];
        }
        return [
            'label' => $template,
            'plugin_dir' => 'src/plugin',
            'default' => false,
        ];
    }

    /**
     * 解析插件在各模板的落盘目录（绝对路径）
     *
     * @param string[] $templates 模板名列表
     * @param string   $plugin    插件标识
     * @param string   $mode      模式（用于解析模板路径）
     * @return array<string,string> 模板名 => 落盘目录，无法解析路径的模板被跳过
     */
    public function pluginTargetDirs(array $templates, string $plugin, string $mode): array
    {
        $dirs = [];
        foreach ($templates as $template) {
            $name = $this->canonicalTemplate((string) $template);
            $base = $this->templatePath($name, $mode);
            if ($base === '') {
                continue;
            }
            $pluginDir = $this->templateMeta($name)['plugin_dir'] ?? 'src/plugin';
            $dirs[$name] = rtrim($base . '/' . trim((string) $pluginDir, '/') . '/' . trim($plugin, '/'), '/');
        }
        return $dirs;
    }

    /**
     * 插件默认分发模板（含 fallback）
     *
     * @return string[]
     */
    public function pluginDefaultTemplates(): array
    {
        $distribution = $this->config()['plugin_distribution'] ?? null;
        $templates = is_array($distribution) ? ($distribution['default_templates'] ?? null) : null;
        if (!is_array($templates) || $templates === []) {
            return self::DEFAULT_DISTRIBUTION_TEMPLATES;
        }
        return array_values(array_map('strval', $templates));
    }

    /**
     * 插件安装环节顺序（含 fallback）
     *
     * @return string[]
     */
    public function pluginInstallOrder(): array
    {
        $distribution = $this->config()['plugin_distribution'] ?? null;
        $order = is_array($distribution) ? ($distribution['install_order'] ?? null) : null;
        if (!is_array($order) || $order === []) {
            return ['backend', 'menus', 'frontend', 'mark'];
        }
        return array_values(array_map('strval', $order));
    }
}
