<?php
declare(strict_types=1);

use core\io\excel\ExportPathGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 导出下载路径遍历（CWE-22）回归用例
 */
final class SecurityExportPathTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'madong_export_guard_' . bin2hex(random_bytes(6));

        mkdir($this->baseDir . DIRECTORY_SEPARATOR . ExportPathGuard::EXPORT_DIR, 0777, true);
        file_put_contents($this->baseDir . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'report.xlsx', 'xlsx');
        file_put_contents($this->baseDir . DIRECTORY_SEPARATOR . 'secret.env', 'SECRET=1');
    }

    protected function tearDown(): void
    {
        foreach (['exports/link.xlsx', 'exports/report.xlsx', 'secret.env'] as $relative) {
            $path = $this->baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            }
        }
        $exports = $this->baseDir . DIRECTORY_SEPARATOR . 'exports';
        if (is_dir($exports)) @rmdir($exports);
        if (is_dir($this->baseDir)) @rmdir($this->baseDir);
    }

    public function testBusinessDownloadPathStillWorks(): void
    {
        self::assertSame('exports/report.xlsx', ExportPathGuard::normalize('/exports/report.xlsx'));

        $resolved = ExportPathGuard::resolve('/exports/report.xlsx', $this->baseDir);
        self::assertFileExists($resolved);
        self::assertSame(
            realpath($this->baseDir . DIRECTORY_SEPARATOR . 'exports'),
            dirname($resolved)
        );
    }

    #[DataProvider('attackPaths')]
    public function testAttackPathsAreRejected(string $attackPath): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ExportPathGuard::resolve($attackPath, $this->baseDir);
    }

    public static function attackPaths(): array
    {
        return [
            'parent traversal'        => ['../../.env'],
            'windows traversal'       => ['..\\..\\.env'],
            'prefix plus traversal'   => ['/exports/../../secret.env'],
            'relative traversal'      => ['exports/../secret.env'],
            'double-write bypass'     => ['exports/....//secret.env'],
            'absolute unix path'      => ['/etc/passwd'],
            'windows drive'           => ['C:\\Windows\\win.ini'],
            'url scheme'              => ['file:///etc/passwd'],
            'wrong extension'         => ['exports/report.txt'],
            'prefix bypass'           => ['exports-evil/report.xlsx'],
            'directory only'          => ['exports/'],
            'empty path'              => [''],
            'null byte'               => ["exports/\0report.xlsx"],
            'missing file'            => ['exports/not-there.xlsx'],
        ];
    }

    public function testSymlinkEscapeIsRejected(): void
    {
        $link = $this->baseDir . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'link.xlsx';
        $target = $this->baseDir . DIRECTORY_SEPARATOR . 'secret.env';

        if (!@symlink($target, $link)) {
            self::markTestSkipped('当前环境不支持创建符号链接（Windows 需开发者模式/管理员权限）');
        }

        $this->expectException(\InvalidArgumentException::class);
        ExportPathGuard::resolve('exports/link.xlsx', $this->baseDir);
    }

    public function testDownloadNameIsSanitized(): void
    {
        $name = ExportPathGuard::sanitizeDownloadName("../../etc/passwd\r\nX-Injected: 1");
        self::assertStringNotContainsString('/', $name);
        self::assertStringNotContainsString('..', $name);
        self::assertStringNotContainsString("\r", $name);
        self::assertStringNotContainsString("\n", $name);
        self::assertSame('export.xlsx', ExportPathGuard::sanitizeDownloadName('/\\..'));
    }

    public function testExportFilenameIsSanitized(): void
    {
        $name = ExportPathGuard::sanitizeFilename('../../report&file=1.xlsx');
        self::assertStringNotContainsString('/', $name);
        self::assertStringNotContainsString('..', $name);
        self::assertStringNotContainsString('&', $name);
        self::assertStringNotContainsString('=', $name);
        self::assertNotSame('', $name);
    }
}
