<?php
declare(strict_types=1);

use app\command\install\InstallCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI 安装租户参数回归：确保 enable_tenant 被正确组装，
 * 多租户启用在账户之前询问，且多租户平台超管默认使用 root。
 */
final class InstallCommandTenantTest extends TestCase
{
    private function collect(array $options): array
    {
        $command = new InstallCommand();
        $method  = new \ReflectionMethod($command, 'collectInstallParams');
        $method->setAccessible(true);

        $input  = new ArrayInput($options, $command->getDefinition());
        $output = new BufferedOutput();
        $io     = new SymfonyStyle($input, $output);

        return $method->invoke($command, $io, $input, false);
    }

    private function source(): string
    {
        return (string)file_get_contents(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/command/install/InstallCommand.php'
        );
    }

    public function testTenantIsEnabledByDefault(): void
    {
        $params = $this->collect([]);

        self::assertSame('manual', $params['tenant_mode']);
        self::assertSame('1', $params['enable_tenant']);
        self::assertSame('0', $params['create_default_tenant']);
    }

    public function testNoneModeDisablesTenant(): void
    {
        self::assertSame('0', $this->collect(['--tenant-mode' => 'none'])['enable_tenant']);
    }

    public function testExplicitEnableTenantOverridesMode(): void
    {
        self::assertSame('0', $this->collect(['--enable-tenant' => '0'])['enable_tenant']);
        self::assertSame(
            '1',
            $this->collect(['--tenant-mode' => 'none', '--enable-tenant' => '1'])['enable_tenant']
        );
    }

    public function testTenantModeMatchesFinalToggle(): void
    {
        $params = $this->collect(['--tenant-mode' => 'none', '--enable-tenant' => '1']);

        self::assertSame('manual', $params['tenant_mode']);
        self::assertSame('1', $params['enable_tenant']);
    }

    public function testInvalidTenantModeFallsBackToManual(): void
    {
        $params = $this->collect(['--tenant-mode' => 'auto']);

        self::assertSame('manual', $params['tenant_mode']);
        self::assertSame('1', $params['enable_tenant']);
    }

    public function testSiteNameIsCollected(): void
    {
        self::assertSame('Madong SaaS', $this->collect(['--site-name' => 'Madong SaaS'])['site_name']);
    }

    public function testTenantIsAskedBeforeAdminAccount(): void
    {
        $source = $this->source();

        $tenantAsk = strpos($source, '是否启用多租户？');
        $rootDefault = strpos($source, "? 'root' : 'admin'");
        self::assertNotFalse($tenantAsk);
        self::assertNotFalse($rootDefault);
        self::assertLessThan(
            (int)$rootDefault,
            (int)$tenantAsk,
            '必须先决定是否启用多租户，再按模式确定超级管理员默认账号'
        );

        self::assertLessThan(
            (int)strpos($source, "\$params['admin_username']"),
            (int)strpos($source, "\$params['enable_tenant']"),
            'enable_tenant 必须在 admin_username 之前组装'
        );
    }

    public function testMultiTenantUsesRootPlatformSuperAdminByDefault(): void
    {
        self::assertSame('root', $this->collect([])['admin_username']);
        self::assertSame('root', $this->collect(['--tenant-mode' => 'manual'])['admin_username']);
    }

    public function testSingleTenantUsesAdminByDefault(): void
    {
        self::assertSame('admin', $this->collect(['--tenant-mode' => 'none'])['admin_username']);
        self::assertSame('admin', $this->collect(['--enable-tenant' => '0'])['admin_username']);
    }

    public function testExplicitAdminUsernameWins(): void
    {
        $params = $this->collect(['--admin-username' => 'ops']);

        self::assertSame('ops', $params['admin_username']);
        self::assertSame('1', $params['enable_tenant']);
    }

    public function testDefaultDatabaseNameMatchesEnvTemplate(): void
    {
        self::assertSame('madong_saas', $this->collect([])['db_database']);
    }
}
