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
namespace core\infrastructure\logger;

use core\infrastructure\logger\enum\LogLevel;
use Monolog\Logger as Monolog;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

final class LoggerService
{
    private Monolog $logger;
    private static ?array $configCache = null;
    private static float $configCacheTime = 0;
    private const CONFIG_CACHE_TTL = 60;

    public function __construct()
    {
        $this->initialize();
    }

    private function initialize(): void
    {
        if (!self::getConfig('enable')) {
            throw new \RuntimeException('Logger is disabled in config');
        }

        $this->logger = new Monolog(self::getConfig('base.channel'));

        if (self::getConfig('handlers.stream')) {
            $this->addStreamHandlers();
        }

        if (self::getConfig('handlers.syslog')) {
            $this->addSyslogHandler();
        }
    }

    private function addStreamHandlers(): void
    {
        $formatter = new LineFormatter(
            self::getConfig('format.output'),
            self::getConfig('format.date'),
            true,
            true
        );

        foreach (LogLevel::cases() as $level) {
            if ($this->shouldRecord($level)) {
                $handler = new StreamHandler(
                    $this->getLogFile($level),
                    $level->value,
                    true,
                    null,
                    true
                );
                $handler->setFormatter($formatter);
                $handler->setLevel($level->value);
                $handler->setBubble(false);
                $this->logger->pushHandler($handler);
            }
        }
    }

    public function handleLog(string $levelName, array $arguments): void
    {
        $level = LogLevel::fromName(strtoupper($levelName));
        if ($level === null) {
            return;
        }

        if (!self::getConfig('enable') || !$this->shouldRecord($level)) {
            return;
        }

        $this->logger->log(
            $level->value,
            $arguments[0] ?? '',
            $arguments[1] ?? []
        );
    }

    private function shouldRecord(LogLevel $level): bool
    {
        if (!self::getConfig('enable')) {
            return false;
        }

        if ($level === LogLevel::DEBUG &&
            false === self::getConfig('levels.debug', true)) {
            return false;
        }

        $minLevel = LogLevel::fromName(
            strtoupper(self::getConfig('levels.min_level', 'debug'))
        );

        return $level->value >= $minLevel->value;
    }

    private function addSyslogHandler(): void
    {
        // syslog处理器实现
    }

    /**
     * @param string|null $key
     * @param             $default
     *
     * @return array|mixed|null
     */
    public static function getConfig(?string $key = null, $default = null): mixed
    {
        $now = microtime(true);

        if (self::$configCache === null || ($now - self::$configCacheTime) > self::CONFIG_CACHE_TTL) {
            self::$configCache = config('core.infrastructure.logger', []);
            self::$configCacheTime = $now;
        }

        return data_get(self::$configCache, $key, $default);
    }

    /**
     * 清除配置缓存
     */
    public static function clearConfigCache(): void
    {
        self::$configCache = null;
        self::$configCacheTime = 0;
    }

    /**
     * 获取按通道和月份分类的日志文件路径
     */
    private function getLogFile(LogLevel $level): string
    {
        $basePath  = $this->getBaseLogPath();
        $monthDir  = date('Y-m');
        $dayPrefix = date('d');

        $fullPath = "{$basePath}/{$monthDir}";

        if (!is_dir($fullPath) && !mkdir($fullPath, 0755, true)) {
            throw new \RuntimeException("Cannot create directory: {$fullPath}");
        }

        return sprintf('%s/%s-%s.log', $fullPath, $dayPrefix, $level->name);
    }

    /**
     * 获取基础日志路径（包含通道目录）
     */
    private function getBaseLogPath(): string
    {
        $path    = rtrim(self::getConfig('base.path'), '/');
        $channel = self::getConfig('base.channel', 'app');

        $fullPath = "{$path}/{$channel}";

        if (!is_dir($fullPath) && !mkdir($fullPath, 0755)) {
            throw new \RuntimeException("Channel directory creation failed: {$fullPath}");
        }

        return $fullPath;
    }

    public function cleanup(bool $forceAll = false, ?string $channel = null, bool $removeEmptyDirs = true): void
    {
        if (!self::getConfig('enable')) {
            return;
        }

        $basePath = rtrim(self::getConfig('base.path'), '/');
        $channel  = $channel ?? self::getConfig('base.channel', 'app');
        $logPath  = "{$basePath}/{$channel}";

        if (!is_dir($logPath)) {
            return;
        }

        $this->deleteExpiredFiles($logPath, $forceAll);

        if ($removeEmptyDirs) {
            $this->deleteEmptyDirectories($logPath);
        }
    }

    private function deleteExpiredFiles(string $logPath, bool $forceAll): void
    {
        $retentionDays = (int)self::getConfig('base.retention_days', 7);
        $cutoffDate    = strtotime("-{$retentionDays} days");

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($logPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'log') {
                continue;
            }

            $fileName = $file->getFilename();
            $filePath = $file->getRealPath();
            $dirPath  = dirname($filePath);

            $monthDir = basename($dirPath);
            if (!preg_match('/^(\d{4})-(\d{2})$/', $monthDir, $monthMatches)) {
                continue;
            }

            if (!preg_match('/^(\d{2})-(.+)\.log$/', $fileName, $matches)) {
                continue;
            }

            $fileDay  = $matches[1];
            $logLevel = strtoupper($matches[2]);
            $year     = $monthMatches[1];
            $month    = $monthMatches[2];

            $fileDateStr = "{$year}-{$month}-{$fileDay}";
            $fileDate    = strtotime($fileDateStr);

            if ($fileDate === false) {
                continue;
            }

            if (self::getConfig('debug') && $logLevel === 'INFO') {
                continue;
            }

            if ($forceAll || $fileDate < $cutoffDate) {
                $this->safeUnlink($filePath);
            }
        }
    }

    private function deleteEmptyDirectories(string $logPath): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($logPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $dir) {
            if ($dir->isDir() && $this->isEmptyDirectory($dir->getRealPath())) {
                $this->safeRmdir($dir->getRealPath());
            }
        }
    }

    private function isEmptyDirectory(string $dirPath): bool
    {
        if (!is_dir($dirPath)) {
            return false;
        }

        $dir = new \FilesystemIterator($dirPath);
        return !$dir->valid();
    }

    private function safeUnlink(string $filePath): bool
    {
        try {
            return @unlink($filePath);
        } catch (\Throwable $e) {
            error_log("Failed to delete file: {$filePath} - " . $e->getMessage());
            return false;
        }
    }

    private function safeRmdir(string $dirPath): bool
    {
        try {
            return @rmdir($dirPath);
        } catch (\Throwable $e) {
            error_log("Failed to remove directory: {$dirPath} - " . $e->getMessage());
            return false;
        }
    }
}
