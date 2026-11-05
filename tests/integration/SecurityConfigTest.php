<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * 静态回归：确保修复点不被后续改动回退。
 */
final class SecurityConfigTest extends TestCase
{
    private function source(string $relative): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relative);
    }

    public function testDownloadControllerUsesGuardAndWhitelistedQueuePayload(): void
    {
        $source = $this->source('app/adminapi/controller/system/FilesController.php');

        self::assertStringContainsString('ExportPathGuard::resolve', $source);
        self::assertStringContainsString("'file_path' => \$safeRelative", $source);
        self::assertStringNotContainsString('$filePath     = runtime_path() . $file;', $source);
    }

    public function testQueueConsumerHasItsOwnGuard(): void
    {
        $source = $this->source('app/queue/redis/RemoveExcelFile.php');

        self::assertStringContainsString('ExportPathGuard::resolve', $source);
        self::assertStringNotContainsString('runtime_path() . $filePath', $source);
    }

    public function testQueueControllerValidatesQueueNameAndPath(): void
    {
        $source = $this->source('app/platform/controller/ops/QueueController.php');

        self::assertStringContainsString("array_column(\$this->availableQueues, 'name')", $source);
        self::assertStringContainsString('ExportPathGuard::normalize', $source);
    }

    public function testInstallerGeneratesSecretInBothEnvWritePaths(): void
    {
        $source = $this->source('core/business/install/InstallService.php');

        self::assertGreaterThanOrEqual(2, substr_count($source, 'JwtSecret::ensureInEnv'));
        self::assertStringContainsString('JWT_SECRET=', $this->source('.example.env'));
    }

    public function testSecretRotationCommandExists(): void
    {
        $source = $this->source('app/command/JwtSecretGenerateCommand.php');

        self::assertStringContainsString("name: 'jwt:secret'", $source);
        self::assertStringContainsString('JwtSecret::ensureInEnv', $source);
    }

    public function testCliInstallCreatesEnvBeforeLock(): void
    {
        $source = $this->source('core/business/install/InstallService.php');
        $start  = strpos($source, 'public function install(array $params): \Generator');
        self::assertNotFalse($start);

        $installMethod = substr($source, (int)$start, 6000);
        self::assertLessThan(
            (int)strpos($installMethod, '$this->createInstallLock();'),
            (int)strpos($installMethod, '$this->copyDatabaseTemplateAndCreateEnv('),
            '.env 必须在 install.lock 之前生成，避免留下无密钥的已安装态'
        );
    }

    public function testCliInstallReportsRuntimeErrorsAndFinishesGenerator(): void
    {
        $base = $this->source('app/command/BaseCommand.php');
        $cli  = $this->source('app/command/install/InstallCommand.php');

        self::assertStringContainsString("case 'runtime_error'", $base);
        self::assertStringContainsString('$completed ? Command::SUCCESS : Command::FAILURE', $cli);
    }

    public function testEnvironmentCheckNeverLeavesKeylessEnv(): void
    {
        $source = $this->source('core/business/install/InstallService.php');

        self::assertStringContainsString("JwtSecret::ensureInEnv(\$content)", $source);
        self::assertStringContainsString('JwtSecret::isValid($existingSecret)', $source);
    }

    public function testFrontendBuildIsOptionalAndNonBlocking(): void
    {
        $installService = $this->source('core/business/install/InstallService.php');
        self::assertStringContainsString('前端构建/依赖安装失败，安装继续', $installService);

        $cli = $this->source('app/command/install/InstallCommand.php');
        self::assertStringContainsString('默认不构建', $cli);
        self::assertStringContainsString("\$params['build_project'] = \$buildProject === '1' ? '1' : '0';", $cli);

        $webStore = (string)file_get_contents(
            dirname(__DIR__, 3) . '/template/mono/apps/install/src/store/module/install.ts'
        );
        self::assertStringContainsString('build_project: false', $webStore);
    }

    public function testInstallerWritesAppTenantToggle(): void
    {
        $reflection = new \ReflectionClass(\core\business\install\InstallService::class);
        $service    = $reflection->newInstanceWithoutConstructor();
        $method     = $reflection->getMethod('setEnvTenantToggle');
        $method->setAccessible(true);

        $template = (string)file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.example.env');
        $enabled  = (string)$method->invoke($service, $template, true);
        $disabled = (string)$method->invoke($service, $template, false);

        self::assertMatchesRegularExpression('/^APP_TENANT_ENABLED=true$/m', $enabled);
        self::assertMatchesRegularExpression('/^APP_TENANT_ENABLED=false$/m', $disabled);
    }

    public function testCliInstallPassesTenantParams(): void
    {
        $cli = $this->source('app/command/install/InstallCommand.php');

        self::assertStringContainsString("'tenant-mode'", $cli);
        self::assertStringContainsString("'enable-tenant'", $cli);
        self::assertStringContainsString("\$params['enable_tenant']", $cli);
    }

    public function testCliAsksTenantBeforeAccountsAndUsesRoot(): void
    {
        $cli = $this->source('app/command/install/InstallCommand.php');

        self::assertStringContainsString('是否启用多租户？', $cli);
        self::assertStringContainsString("? 'root' : 'admin'", $cli);
        self::assertLessThan(
            (int)strpos($cli, "\$params['admin_username']"),
            (int)strpos($cli, "\$params['enable_tenant']"),
            '多租户开关必须先于超级管理员账户组装'
        );
    }

    public function testReservedRootAdminIsEditableButCannotBeCreated(): void
    {
        foreach ([
            'app/adminapi/validate/system/admin/AdminValidate.php',
            'app/platform/validate/system/AdminValidate.php',
        ] as $relative) {
            $source = $this->source($relative);
            self::assertStringContainsString('not_in:root', $source);
            self::assertStringContainsString('isExistingRootAdmin', $source);
        }
    }

    public function testPasswordInputDoesNotCorruptQuestionStream(): void
    {
        $base = $this->source('app/command/BaseCommand.php');
        self::assertStringContainsString('fgets(STDIN)', $base);
        self::assertStringNotContainsString('stream_get_contents(STDIN, 1)', $base);

        $cli = $this->source('app/command/install/InstallCommand.php');
        self::assertStringContainsString('$input->isInteractive()', $cli);
    }

    public function testJwtSecretBlockStaysAtEndOfEnvTemplates(): void
    {
        $example = $this->source('.example.env');
        self::assertMatchesRegularExpression('/^JWT_SECRET=\r?$/m', $example);
        self::assertLessThan(
            (int)strpos($example, 'JWT_SECRET='),
            (int)strpos($example, '# Other Configuration'),
            '.example.env 的 JWT 段必须位于模板末尾且保持空值'
        );

        $installService = $this->source('core/business/install/InstallService.php');
        $start = strpos($installService, 'private function getDefaultEnvContent()');
        self::assertNotFalse($start);
        $heredoc = substr($installService, (int)$start, 1200);
        self::assertLessThan(
            (int)strpos($heredoc, 'JWT_SECRET='),
            (int)strpos($heredoc, 'LOG_CHANNEL = daily'),
            '默认 .env 模板的 JWT 段必须位于末尾'
        );
    }

    public function testInstallerEnvTemplateKeepsEventLoopFallback(): void
    {
        $example = $this->source('.example.env');
        self::assertMatchesRegularExpression('/^APP_EVENT_LOOP=\r?$/m', $example);
        self::assertLessThan(
            (int)strpos($example, 'JWT_SECRET='),
            (int)strpos($example, 'APP_EVENT_LOOP='),
            '事件循环空值须保留在模板中，且 JWT 段仍在末尾'
        );

        $process = $this->source('config/process.php');
        self::assertStringContainsString("extension_loaded('swow')", $process);
    }
}
