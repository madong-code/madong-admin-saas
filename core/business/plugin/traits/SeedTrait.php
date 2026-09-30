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
namespace core\business\plugin\traits;

/**
 * 种子操作 Trait
 */
trait SeedTrait
{
    /**
     * 插件临时目录路径
     */
    const RUNTIME_PLUGIN_PATH = 'migrations/plugin';

    /**
     * 运行种子
     */
    public function runSeeds(): void
    {
        $seedsDir = $this->findSeedsDir();
        
        if (!$seedsDir) {
            $this->output("⚠️ No seeds directory found");
            return;
        }

        $this->output("📁 Seeds dir: {$seedsDir}");

        $executed = $this->getExecutedSeeds();

        $files = glob($seedsDir . '/*.php');

        $pending = [];
        foreach ($files as $file) {
            $basename = basename($file, '.php');
            $seedName = str_replace('Seeder', '', $basename);
            if (!in_array($seedName, $executed)) {
                $pending[] = [$file, $basename];
            }
        }

        sort($pending);

        if (empty($pending)) {
            $this->output("✅ No pending seeds");
            return;
        }

        $this->output("📋 Pending seeds: " . count($pending));

        foreach ($pending as $seed) {
            $this->runSeed($seed[0], $seed[1]);
        }

        $this->output("✅ Seeds completed");
    }
    
    /**
     * 运行单个种子
     */
    protected function runSeed(string $file, string $className): void
    {
        $this->output("  📝 Seeding: {$className}");

        require_once $file;

        $seeder = null;

        $classes = get_declared_classes();
        foreach ($classes as $cls) {
            if (str_ends_with($cls, '\\' . $className) || $cls === $className) {
                $seeder = new $cls();
                break;
            }
        }

        if (!$seeder) {
            $this->output("  ❌ Seeder class not found: {$className}");
            return;
        }

        try {
            $startTime = microtime(true);
            if (method_exists($seeder, 'run')) {
                $seeder->run();
            }
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->output("  ✅ Seeded: {$className} ({$duration}ms)");

            $this->recordSeed(str_replace('Seeder', '', $className));
        } catch (\Throwable $e) {
            $this->output("  ❌ Error: {$e->getMessage()}");
        }
    }

    /**
     * 查找种子目录
     */
    protected function findSeedsDir(): ?string
    {
        $resourceDir = $this->getConfig('resource.seed', 'database/seeds');
        
        $dirs = [
            $this->pluginPath . '/resource/database/seeds',
            $this->pluginPath . '/' . $resourceDir,
            $this->pluginPath . '/install/seeds',
            base_path("resource/database/seeds/plugin/{$this->pluginName}"),
        ];
        
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }
        
        return null;
    }

    /**
     * 获取种子日志文件
     */
    protected function getSeedLogFile(): string
    {
        return runtime_path(self::RUNTIME_PLUGIN_PATH . '/' . $this->pluginName . '/seeds.log');
    }

    /**
     * 获取已执行的种子（跳过 # 注释行）
     */
    protected function getExecutedSeeds(): array
    {
        $logFile = $this->getSeedLogFile();
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $content = trim(file_get_contents($logFile));
        
        if (empty($content)) {
            return [];
        }
        
        $lines = array_filter(explode(PHP_EOL, $content));
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            // 新格式: name,status,executed_at
            $parts = explode(',', $line, 2);
            $result[] = $parts[0];
        }
        
        return $result;
    }

    /**
     * 记录种子（新格式：带状态和时间）
     */
    protected function recordSeed(string $name): void
    {
        $logFile = $this->getSeedLogFile();
        
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $now = date('Y-m-d H:i:s');
        
        // 首次写入时加注释头
        if (!file_exists($logFile) || filesize($logFile) === 0) {
            $header = "# Seed Log - {$this->pluginName} - Started: {$now}" . PHP_EOL;
            $header .= "# Format: name,status,executed_at" . PHP_EOL;
            file_put_contents($logFile, $header, LOCK_EX);
        }
        
        file_put_contents($logFile, "{$name},success,{$now}" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
