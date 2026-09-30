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

use Illuminate\Database\Schema\Builder;
use support\Db;

/**
 * 迁移操作 Trait
 */
trait MigrationTrait
{
    /**
     * 插件临时目录路径
     */
    const RUNTIME_PLUGIN_PATH = 'migrations/plugin';

    /**
     * 运行迁移
     */
    public function runMigrations(): void
    {
        $migrationsDir = $this->findMigrationsDir();

        if (!$migrationsDir) {
            $this->output("⚠️ No migrations directory found");
            return;
        }

        $this->output("📁 Migrations dir: {$migrationsDir}");

        // 获取已执行的迁移
        $executed = $this->getExecutedMigrations();

        // 获取所有迁移文件
        $files = glob($migrationsDir . '/*.php');

        $pending = [];
        foreach ($files as $file) {
            $basename = basename($file, '.php');
            if (!in_array($basename, $executed)) {
                $pending[] = [$file, $basename];
            }
        }

        sort($pending);

        if (empty($pending)) {
            $this->output("✅ No pending migrations");
            return;
        }

        $this->output("📋 Pending migrations: " . count($pending));

        $batch = $this->getNextBatch();
        $schema = $this->getSchemaBuilder();

        // 直接执行迁移
        foreach ($pending as $migration) {
            $this->runMigration($migration[0], $migration[1], $schema);
        }

        // 记录批次
        $this->recordBatch($batch, $pending);

        $this->output("✅ Migrations completed (batch: {$batch})");
    }
    
    /**
     * 运行单个迁移（捕获耗时）
     */
    protected function runMigration(string $file, string $name, Builder $schema): void
    {
        $this->output("  📝 Migrating: {$name}");

        try {
            $startTime = microtime(true);
            $migration = require $file;
            $migration->up($schema);
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->output("  ✅ Migrated: {$name} ({$duration}ms)");
        } catch (\Throwable $e) {
            $this->output("  ❌ Error: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * 回滚迁移
     * @param bool $dropTables 是否删除表
     */
    public function rollbackMigrations(bool $dropTables = false): void
    {
        if ($dropTables) {
            $this->dropAllTables();
            $this->saveMigrationRows([]);
            $this->output("✅ All plugin tables dropped");
            return;
        }

        // 不删除表，只清理迁移日志
        $this->saveMigrationRows([]);
        $this->output("📝 Migration records cleared (tables preserved)");
    }

    /**
     * 执行所有迁移文件的 down() 来删除表
     * 不通过表名猜测，而是由每个迁移文件自行处理
     */
    protected function dropAllTables(): void
    {
        $migrationsDir = $this->findMigrationsDir();
        if (!is_dir($migrationsDir)) {
            $this->output("  ⚠️ No migrations directory found");
            return;
        }

        $files = glob($migrationsDir . '/*.php');
        if (empty($files)) {
            $this->output("  ⚠️ No migration files found");
            return;
        }

        $schema = $this->getSchemaBuilder();
        foreach ($files as $file) {
            $name = basename($file, '.php');
            try {
                $migration = require $file;
                $migration->down($schema);
                $this->output("  🗑️ Rolled back: {$name}");
            } catch (\Throwable $e) {
                $this->output("  ❌ Error rolling back {$name}: {$e->getMessage()}");
            }
        }
    }
    
    /**
     * 回滚单个迁移
     */
    protected function rollbackMigration(string $name, Builder $schema): void
    {
        $this->output("  📝 Rolling back: {$name}");

        $migrationsDir = $this->findMigrationsDir();
        $file = $migrationsDir . '/' . $name . '.php';

        if (!file_exists($file)) {
            $this->output("  ⚠️ Migration file not found: {$file}");
            return;
        }

        try {
            $migration = require $file;
            $migration->down($schema);
            $this->output("  ✅ Rolled back: {$name}");
        } catch (\Throwable $e) {
            $this->output("  ❌ Error: {$e->getMessage()}");
        }
    }

    /**
     * 查找迁移目录
     */
    protected function findMigrationsDir(): ?string
    {
        $resourceDir = $this->getConfig('resource.migration', 'database/migrations');
        
        $dirs = [
            $this->pluginPath . '/resource/database/migrations',
            $this->pluginPath . '/' . $resourceDir,
            $this->pluginPath . '/install/migrations',
            base_path("resource/database/migrations/plugin/{$this->pluginName}"),
        ];
        
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }
        
        return null;
    }

    /**
     * 获取 Schema Builder
     */
    protected function getSchemaBuilder(): Builder
    {
        return Db::connection($this->connection ?? null)->getSchemaBuilder();
    }

    /**
     * 获取迁移日志文件
     *
     * 按连接区分日志文件：库隔离租户在各自独立库执行迁移时，
     * 不能复用平台/其它租户的迁移日志，否则会被误判为"已执行"而跳过。
     */
    protected function getMigrationLogFile(): string
    {
        $suffix = $this->connection
            ? str_replace(['tenant_', ' '], ['', '_'], (string)$this->connection)
            : 'default';
        return runtime_path(self::RUNTIME_PLUGIN_PATH . '/' . $this->pluginName . '/migrations_' . $suffix . '.log');
    }

    /**
     * 获取已执行的迁移
     */
    protected function getExecutedMigrations(): array
    {
        $rows = $this->getMigrationRows();
        return array_column($rows, 1);
    }

    /**
     * 获取迁移记录行（兼容旧格式：跳过 # 注释行）
     */
    protected function getMigrationRows(): array
    {
        $logFile = $this->getMigrationLogFile();
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $content = trim(file_get_contents($logFile));
        
        if (empty($content)) {
            return [];
        }
        
        $rows = [];
        foreach (explode(PHP_EOL, $content) as $line) {
            $line = trim($line);
            // 跳过注释行（新格式头）
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode(',', $line);
            if (count($parts) >= 2) {
                // 只取 batch 和 filename，忽略后面的 status/duration 字段
                $rows[] = [$parts[0], $parts[1]];
            }
        }
        
        return $rows;
    }

    /**
     * 保存迁移记录行（新格式：带注释头）
     */
    protected function saveMigrationRows(array $rows): void
    {
        $logFile = $this->getMigrationLogFile();
        
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        if (empty($rows)) {
            @unlink($logFile);
            return;
        }
        
        $header = "# Migration Log - {$this->pluginName} - Started: " . date('Y-m-d H:i:s') . PHP_EOL;
        $header .= "# Format: batch,filename,status,started_at,finished_at,duration(ms)" . PHP_EOL;
        
        // 兼容旧格式：如果行数不够5个字段，补齐
        $lines = array_map(function ($r) {
            if (count($r) >= 5) {
                return implode(',', $r);
            }
            return "{$r[0]},{$r[1]},success," . date('Y-m-d H:i:s') . ',' . date('Y-m-d H:i:s') . ',0';
        }, $rows);
        
        file_put_contents($logFile, $header . implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    }

    /**
     * 获取下一批次号
     */
    protected function getNextBatch(): int
    {
        $rows = $this->getMigrationRows();
        
        if (empty($rows)) {
            return 1;
        }
        
        $batches = array_map(fn($row) => (int)$row[0], $rows);
        
        return max($batches) + 1;
    }

    /**
     * 记录批次（新格式：带状态和耗时）
     */
    protected function recordBatch(int $batch, array $migrations): void
    {
        $logFile = $this->getMigrationLogFile();
        
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $now = date('Y-m-d H:i:s');
        
        // 首次写入时加注释头
        if (!file_exists($logFile) || filesize($logFile) === 0) {
            $header = "# Migration Log - {$this->pluginName} - Started: {$now}" . PHP_EOL;
            $header .= "# Format: batch,filename,status,started_at,finished_at,duration(ms)" . PHP_EOL;
            file_put_contents($logFile, $header, LOCK_EX);
        }
        
        $lines = array_map(fn($m) => "{$batch},{$m[1]}," . ($m[2] ?? 'success') . "," . ($m[3] ?? $now) . "," . ($m[4] ?? $now) . "," . ($m[5] ?? 0), $migrations);
        file_put_contents($logFile, implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * 清理日志
     */
    protected function clearLogs(): void
    {
        @unlink($this->getMigrationLogFile());
        @unlink($this->getSeedLogFile());
    }

    /**
     * 导出 SQL（从迁移文件生成）
     * 
     * @param bool $withData 是否包含数据
     * @param string|null $outputFile 输出文件路径（null 则输出到插件目录）
     * @return bool 是否成功
     */
    public function exportSql(bool $withData = false, ?string $outputFile = null): bool
    {
        $migrationsDir = $this->findMigrationsDir();

        if (!$migrationsDir || !is_dir($migrationsDir)) {
            $this->output("⚠️ No migrations directory found");
            return false;
        }

        $files = glob($migrationsDir . '/*.php');

        if (empty($files)) {
            $this->output("⚠️ No migration files found");
            return false;
        }

        $this->output("📤 Exporting SQL from " . count($files) . " migration file(s)...");

        // 从迁移文件生成 SQL
        $sql = $this->generateSqlFromMigrations($files, $withData);

        // 默认输出到插件的 resource/database 目录
        if (!$outputFile) {
            $outputDir = $this->pluginPath . '/resource/database';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            $filename = $withData ? 'install.sql' : 'structure.sql';
            $outputFile = $outputDir . '/' . $filename;
        }

        $dir = dirname($outputFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($outputFile, $sql);

        $this->output("✅ SQL exported to: {$outputFile}");

        return true;
    }
    
    /**
     * 从迁移文件生成 SQL
     */
    protected function generateSqlFromMigrations(array $files, bool $withData): string
    {
        $sql = "-- Plugin: {$this->pluginName}\n";
        $sql .= "-- Exported at: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Generated from migration files\n\n";
        
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        // 按文件名排序
        sort($files);
        
        foreach ($files as $file) {
            $basename = basename($file, '.php');
            $this->output("  📤 Processing: {$basename}");

            $sql .= $this->generateSqlFromMigrationFile($file, $withData);
            $sql .= "\n";
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        
        return $sql;
    }
    
    /**
     * 从单个迁移文件生成 SQL
     */
    protected function generateSqlFromMigrationFile(string $file, bool $withData): string
    {
        $sql = "";
        $content = file_get_contents($file);
        
        // 提取所有表名
        preg_match_all('/->create\([\'"](\w+)[\'"]\s*,/', $content, $tableMatches);
        
        if (empty($tableMatches[1])) {
            return $sql;
        }
        
        foreach ($tableMatches[1] as $tableName) {
            $sql .= "-- ----------------------------\n";
            $sql .= "-- Table structure for {$tableName}\n";
            $sql .= "-- ----------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            
            // 从迁移代码提取 CREATE TABLE 部分
            // 匹配 ->create('table_name', function (Blueprint $table) { ... });
            $pattern = '/->create\([\'"]' . preg_quote($tableName, '/') . '[\'"]\s*,\s*function\s*\([^)]*\)\s*\{([\s\S]*?)\}\s*\)/';
            
            if (preg_match($pattern, $content, $matches)) {
                $body = $matches[1];
                $sql .= $this->generateCreateTableFromBlueprint($tableName, $body);
            }
            
            $sql .= ";\n\n";
            
            // 如果需要数据，尝试从数据库获取（如果表存在）
            if ($withData) {
                $sql .= $this->generateInsertDataSql($tableName);
                $sql .= "\n";
            }
        }
        
        return $sql;
    }
    
    /**
     * 从 Blueprint 代码生成 CREATE TABLE
     */
    protected function generateCreateTableFromBlueprint(string $tableName, string $body): string
    {
        $columns = [];
        $indexes = [];
        
        // 匹配 $table->列方法
        // $table->id();
        // $table->string('name', 100);
        // $table->text('content')->nullable();
        // $table->foreignId('user_id')->references('id')->on('users');
        
        // 匹配各种列定义
        $lines = explode("\n", $body);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line === '$table->') {
                continue;
            }
            
            // 移除 $table-> 前缀和结尾分号
            $line = ltrim($line, '$table->');
            $line = rtrim($line, ';');
            
            // 匹配方法调用
            if (preg_match('/^(\w+)\s*\(([^)]*)\)(.*)/', $line, $matches)) {
                $method = $matches[1];
                $args = $matches[2];
                $modifiers = $matches[3] ?? '';
                
                $colDef = $this->parseBlueprintMethod($method, $args, $modifiers);
                
                if ($colDef) {
                    $columns[] = $colDef;
                }
            }
        }
        
        if (empty($columns)) {
            return "CREATE TABLE `{$tableName}` ()";
        }
        
        return "CREATE TABLE `{$tableName}` (\n  " . implode(",\n  ", $columns) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
    
    /**
     * 解析 Blueprint 方法
     */
    protected function parseBlueprintMethod(string $method, string $args, string $modifiers): ?string
    {
        // 解析参数
        $argList = [];
        if ($args) {
            // 简单解析逗号分隔的参数
            $depth = 0;
            $current = '';
            for ($i = 0; $i < strlen($args); $i++) {
                $char = $args[$i];
                if ($char === '(') {
                    $depth++;
                    $current .= $char;
                } elseif ($char === ')') {
                    $depth--;
                    $current .= $char;
                } elseif ($char === ',' && $depth === 0) {
                    $argList[] = trim($current);
                    $current = '';
                } else {
                    $current .= $char;
                }
            }
            if ($current) {
                $argList[] = trim($current);
            }
        }
        
        // 处理修饰符
        $nullable = strpos($modifiers, '->nullable()') !== false;
        $default = null;
        if (preg_match('/->default\(([^)]+)\)/', $modifiers, $dm)) {
            $default = $dm[1];
        }
        $autoIncrement = strpos($modifiers, '->autoIncrement()') !== false 
                      || strpos($modifiers, '->autoIncrements()') !== false;
        $primary = strpos($modifiers, '->primary()') !== false;
        
        // 根据方法名生成列定义
        return match ($method) {
            'id' => $this->formatColumn('id', 'bigint', $nullable, $default, $autoIncrement, $primary),
            'bigIncrements' => $this->formatColumn(null, 'bigint', false, null, true, true),
            'bigInteger' => $this->formatColumn($argList[0] ?? null, 'bigint', $nullable, $default, $autoIncrement, $primary),
            'integer', 'increments', 'mediumIncrements', 'smallIncrements', 'tinyIncrements' => 
                $this->formatColumn($argList[0] ?? null, 'int', $nullable, $default, $autoIncrement, $primary),
            'bigInteger' => $this->formatColumn($argList[0] ?? null, 'bigint', $nullable, $default, $autoIncrement, $primary),
            'string' => $this->formatColumn($argList[0] ?? null, 'varchar(' . ($argList[1] ?? 255) . ')', $nullable, $default, false, false),
            'text' => $this->formatColumn($argList[0] ?? null, 'text', $nullable, $default, false, false),
            'longText' => $this->formatColumn($argList[0] ?? null, 'longtext', $nullable, $default, false, false),
            'mediumText' => $this->formatColumn($argList[0] ?? null, 'mediumtext', $nullable, $default, false, false),
            'json' => $this->formatColumn($argList[0] ?? null, 'json', $nullable, $default, false, false),
            'boolean' => $this->formatColumn($argList[0] ?? null, 'tinyint(1)', $nullable, $default, false, false),
            'date' => $this->formatColumn($argList[0] ?? null, 'date', $nullable, $default, false, false),
            'datetime' => $this->formatColumn($argList[0] ?? null, 'datetime', $nullable, $default, false, false),
            'timestamp' => $this->formatColumn($argList[0] ?? null, 'timestamp', $nullable, $default, false, false),
            'time' => $this->formatColumn($argList[0] ?? null, 'time', $nullable, $default, false, false),
            'float' => $this->formatColumn($argList[0] ?? null, 'float', $nullable, $default, false, false),
            'double' => $this->formatColumn($argList[0] ?? null, 'double', $nullable, $default, false, false),
            'decimal' => $this->formatColumn($argList[0] ?? null, 'decimal(' . ($argList[1] ?? '10,2') . ')', $nullable, $default, false, false),
            'enum' => $this->formatColumn($argList[0] ?? null, 'enum(' . ($argList[1] ?? '') . ')', $nullable, $default, false, false),
            'uuid' => $this->formatColumn($argList[0] ?? null, 'char(36)', $nullable, $default, false, false),
            'foreignId', 'foreignIdFor' => $this->formatColumn($argList[0] ?? null, 'bigint', $nullable, $default, false, false),
            'foreignUuid' => $this->formatColumn($argList[0] ?? null, 'char(36)', $nullable, $default, false, false),
            'rememberToken' => $this->formatColumn('remember_token', 'varchar(100)', true, null, false, false),
            'timestamps' => "`created_at` datetime NULL,\n  `updated_at` datetime NULL",
            'timestampable' => "`created_at` datetime NULL,\n  `updated_at` datetime NULL",
            'softDeletes' => "`deleted_at` datetime NULL",
            'softDeletesTz' => "`deleted_at` datetime NULL",
            default => null,
        };
    }
    
    /**
     * 格式化列定义
     */
    protected function formatColumn(?string $name, string $type, bool $nullable, ?string $default, bool $autoIncrement, bool $primary): ?string
    {
        if (!$name) {
            return null;
        }
        
        $col = "`{$name}` {$type}";
        
        if ($nullable) {
            $col .= " NULL";
        } else {
            $col .= " NOT NULL";
        }
        
        if ($autoIncrement) {
            $col .= " AUTO_INCREMENT";
        }
        
        if ($default !== null) {
            $col .= " DEFAULT " . $default;
        }
        
        if ($primary) {
            $col .= ", PRIMARY KEY (`{$name}`)";
        }
        
        return $col;
    }
    
    /**
     * 生成 INSERT DATA SQL（从数据库）
     */
    protected function generateInsertDataSql(string $table): string
    {
        $sql = "-- ----------------------------\n";
        $sql .= "-- Data for {$table}\n";
        $sql .= "-- ----------------------------\n";
        
        try {
            $connection = $this->connection ?? null;
            $pdo = Db::connection($connection)->getPdo();
            
            // 检查表是否存在
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if ($stmt->rowCount() === 0) {
                $sql .= "-- Table does not exist, skipping data\n";
                return $sql;
            }
            
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($rows)) {
                $sql .= "-- No data\n";
                return $sql;
            }
            
            foreach ($rows as $row) {
                $values = array_map([$this, 'quoteValue'], array_values($row));
                $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', array_keys($row)) . "`) VALUES (" . implode(', ', $values) . ");\n";
            }
            
        } catch (\Throwable $e) {
            $sql .= "-- Error: {$e->getMessage()}\n";
        }
        
        return $sql;
    }
    
    /**
     * 转义值
     */
    protected function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        
        // 使用 PDO 的 quote
        $connection = $this->connection ?? null;
        $pdo = Db::connection($connection)->getPdo();
        return $pdo->quote($value);
    }
}
