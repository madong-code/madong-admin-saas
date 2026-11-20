<?php
declare(strict_types=1);

use core\business\install\InstallConfigResolver;
use PHPUnit\Framework\TestCase;
use Tests\Support\ConfigInjector;

/**
 * 安装配置解析器回归测试
 *
 * 锁定双模式（mono/separated）下「模式决定路径、模板仅声明元数据、web 通用」
 * 的查找契约，确保系统安装与插件安装共用的路径解析不发生偏移。
 */
final class InstallConfigResolverTest extends TestCase
{
    private ConfigInjector $configInjector;

    private InstallConfigResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configInjector = new ConfigInjector();
        $this->configInjector->set('install', require base_path('config/install.php'));
        $this->resolver = new InstallConfigResolver();
    }

    protected function tearDown(): void
    {
        $this->configInjector->restore();
        parent::tearDown();
    }

    public function testDefaultModeFollowsConfig(): void
    {
        // 默认模式跟随 config/install.php 的 install_mode.default（不再硬编码具体值）
        $config = require base_path('config/install.php');
        $expected = $config['install_mode']['default'] ?? $config['default_mode'] ?? 'separated';
        self::assertSame($expected, $this->resolver->resolveMode());
    }

    public function testExplicitModeIsHonored(): void
    {
        self::assertSame('mono', $this->resolver->resolveMode('mono'));
        self::assertSame('separated', $this->resolver->resolveMode('separated'));
    }

    public function testInvalidModeFallsBackToDefault(): void
    {
        // 非法模式回退到配置默认值
        $config = require base_path('config/install.php');
        $expected = $config['install_mode']['default'] ?? $config['default_mode'] ?? 'separated';
        self::assertSame($expected, $this->resolver->resolveMode('unknown'));
    }

    public function testSeparatedTemplatePaths(): void
    {
        $root = rtrim(dirname(base_path()), '/') . '/template';

        self::assertSame($root . '/admin', $this->resolver->templatePath('admin', 'separated'));
        self::assertSame($root . '/platform', $this->resolver->templatePath('platform', 'separated'));
    }

    public function testAdminSourceAliasesAllResolveToAdminDir(): void
    {
        $root = rtrim(dirname(base_path()), '/') . '/template';

        self::assertSame($root . '/admin', $this->resolver->templatePath('single', 'separated'));
        self::assertSame($root . '/admin', $this->resolver->templatePath('aura', 'separated'));
        self::assertSame(['admin', 'web'], $this->resolver->resolveTemplates('separated', ['aura', 'web']));
        self::assertSame('single', $this->resolver->resolveAdminVariant());
        self::assertSame('aura', $this->resolver->resolveAdminVariant('aura'));
        self::assertSame('single', $this->resolver->adminVariants()['single']['repo'] ?? null);
        self::assertSame('aura', $this->resolver->adminVariants()['aura']['repo'] ?? null);
    }

    public function testMonoTemplatePaths(): void
    {
        $root = rtrim(dirname(base_path()), '/') . '/template';

        self::assertSame($root . '/mono/apps/admin', $this->resolver->templatePath('admin', 'mono'));
        self::assertSame($root . '/mono/apps/platform', $this->resolver->templatePath('platform', 'mono'));
    }

    public function testWebIsCommonToBothModes(): void
    {
        $web = rtrim(dirname(base_path()), '/') . '/template/web';

        self::assertSame($web, $this->resolver->templatePath('web', 'mono'));
        self::assertSame($web, $this->resolver->templatePath('web', 'separated'));
    }

    public function testUnknownTemplateHasNoPath(): void
    {
        self::assertSame('', $this->resolver->templatePath('not-exists', 'mono'));
    }

    public function testResolveTemplatesIncludesCommonTemplates(): void
    {
        self::assertSame(['admin', 'platform', 'web'], $this->resolver->resolveTemplates('separated'));
        self::assertSame(['admin', 'platform', 'web'], $this->resolver->resolveTemplates('mono'));
    }

    public function testInstallModeOptionsExposeMonoAndSeparated(): void
    {
        $options = $this->resolver->installModeOptions();

        self::assertArrayHasKey('mono', $options);
        self::assertArrayHasKey('separated', $options);
        self::assertNotSame('', trim((string) ($options['mono']['label'] ?? '')));
        self::assertNotSame('', trim((string) ($options['mono']['description'] ?? '')));
        self::assertSame(['template/mono', 'template/web'], $options['mono']['templates'] ?? null);
        self::assertSame(['template/admin', 'template/platform', 'template/web'], $options['separated']['templates'] ?? null);
        self::assertSame(['admin', 'platform'], $options['mono']['apps'] ?? null);
    }

    public function testBuildTemplatesFollowTenantRules(): void
    {
        // 单租户仅构建 admin、web；多租户构建所有已定义多端应用
        self::assertSame(['admin', 'web'], $this->resolver->buildTemplates(false, 'mono'));
        self::assertSame(['admin', 'web'], $this->resolver->buildTemplates(false, 'separated'));
        self::assertSame(['admin', 'platform', 'web'], $this->resolver->buildTemplates(true, 'mono'));
        self::assertSame(['admin', 'platform', 'web'], $this->resolver->buildTemplates(true, 'separated'));
    }

    public function testBuildTemplatesOverrideCannotBreakTenantRule(): void
    {
        // 显式清单只能收窄，单租户下 platform 必须被剔除
        self::assertSame(
            ['admin', 'web'],
            $this->resolver->buildTemplates(false, 'separated', ['admin', 'platform', 'web'])
        );
        // 多租户下显式清单正常生效
        self::assertSame(
            ['web', 'platform'],
            $this->resolver->buildTemplates(true, 'separated', ['web', 'platform'])
        );
    }

    public function testInstallModePlanResolvesPathsAndOutputDirs(): void
    {
        $root   = rtrim(dirname(base_path()), '/') . '/template';
        $public = rtrim(base_path('public'), '/');

        $mono = $this->resolver->installModePlan('mono', true);
        self::assertSame('mono', $mono['mode']);
        self::assertSame(['admin', 'platform', 'web'], $mono['templates']);
        self::assertSame($root . '/mono/apps/admin', $mono['template_paths']['admin'] ?? null);
        self::assertSame($root . '/mono/apps/platform', $mono['template_paths']['platform'] ?? null);
        self::assertSame($root . '/web', $mono['template_paths']['web'] ?? null);
        self::assertSame($public . '/admin', $mono['output_dirs']['admin'] ?? null);

        $separated = $this->resolver->installModePlan('separated', false);
        self::assertSame(['admin', 'web'], $separated['templates']);
        self::assertSame($root . '/admin', $separated['template_paths']['admin'] ?? null);
        self::assertSame($root . '/web', $separated['template_paths']['web'] ?? null);
        self::assertSame($public . '/web', $separated['output_dirs']['web'] ?? null);
    }

    public function testTemplateOutputDirUsesMeta(): void
    {
        $public = rtrim(base_path('public'), '/');

        self::assertSame($public . '/admin', $this->resolver->templateOutputDir('single'));
        self::assertSame($public . '/platform', $this->resolver->templateOutputDir('platform'));
        self::assertSame($public . '/web', $this->resolver->templateOutputDir('web'));
    }

    public function testTemplateOverrideWinsAndDeduplicates(): void
    {
        self::assertSame(
            ['web', 'admin'],
            $this->resolver->resolveTemplates('mono', ['web', 'admin', 'web'])
        );
    }

    public function testPluginTargetDirsUsePluginDirMeta(): void
    {
        $root = rtrim(dirname(base_path()), '/') . '/template';
        $dirs = $this->resolver->pluginTargetDirs(['admin', 'platform'], 'demo', 'separated');

        self::assertSame($root . '/admin/src/plugin/demo', $dirs['admin'] ?? null);
        self::assertSame($root . '/platform/src/plugin/demo', $dirs['platform'] ?? null);
    }

    public function testPluginTargetDirsSkipUnknownTemplates(): void
    {
        $dirs = $this->resolver->pluginTargetDirs(['not-exists'], 'demo', 'mono');
        self::assertSame([], $dirs);
    }

    public function testTemplateMetaCarriesBuildDirAndPluginDir(): void
    {
        self::assertSame('src/plugin', $this->resolver->templateMeta('admin')['plugin_dir'] ?? null);
        self::assertSame('dist', $this->resolver->templateMeta('admin')['build_dir'] ?? null);
        self::assertSame('.output/public', $this->resolver->templateMeta('web')['build_dir'] ?? null);
    }

    public function testPluginDefaultsTargetAdminAndPlatform(): void
    {
        self::assertSame(['admin', 'platform'], $this->resolver->pluginDefaultTemplates());
    }

    public function testPluginInstallOrderEndsWithMark(): void
    {
        $order = $this->resolver->pluginInstallOrder();
        self::assertSame('backend', $order[0] ?? null);
        self::assertSame('mark', $order[count($order) - 1] ?? null);
    }

    public function testFallbackWhenConfigMissing(): void
    {
        $this->configInjector->set('install', []);
        $fallback = new InstallConfigResolver();

        self::assertSame('separated', $fallback->resolveMode());
        self::assertSame(['admin', 'platform', 'web'], $fallback->resolveTemplates());
        self::assertSame(['admin', 'platform'], $fallback->pluginDefaultTemplates());
        self::assertSame(
            ['backend', 'menus', 'frontend', 'mark'],
            $fallback->pluginInstallOrder()
        );
    }
}
