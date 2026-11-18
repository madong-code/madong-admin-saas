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

    public function testDefaultModeIsSeparated(): void
    {
        self::assertSame('separated', $this->resolver->resolveMode());
    }

    public function testExplicitModeIsHonored(): void
    {
        self::assertSame('mono', $this->resolver->resolveMode('mono'));
        self::assertSame('separated', $this->resolver->resolveMode('separated'));
    }

    public function testInvalidModeFallsBackToDefault(): void
    {
        self::assertSame('separated', $this->resolver->resolveMode('unknown'));
    }

    public function testSeparatedTemplatePaths(): void
    {
        $root = rtrim(dirname(base_path()), '/') . '/template';

        self::assertSame($root . '/admin', $this->resolver->templatePath('admin', 'separated'));
        self::assertSame($root . '/platform', $this->resolver->templatePath('platform', 'separated'));
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

    public function testResolveTemplatesIncludesCommonWeb(): void
    {
        self::assertSame(['admin', 'platform', 'web'], $this->resolver->resolveTemplates('separated'));
        self::assertSame(['admin', 'platform', 'web'], $this->resolver->resolveTemplates('mono'));
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
