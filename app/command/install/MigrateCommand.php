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
namespace app\command\install;

use app\command\BaseCommand;
use support\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

//创建迁移（自动表名）	    madong-migrate make RoleBelongsMenu	                     生成小写蛇形文件名，提示表名
//创建迁移（手动表名）	    madong-migrate make RoleBelongsMenu --table=user_roles	 使用指定表名，生成对应文件名
//创建种子	               madong-migrate make-seed User	                        生成UserSeeder.php
//执行迁移	               madong-migrate up	                                    输出已迁移的文件名
//迁移+种子	               madong-migrate up --seed	                                先迁移，后运行种子
//回滚迁移	               madong-migrate rollback	                                输出回滚的文件名，清理日志
//运行种子	               madong-migrate seed	                                    输出已运行的种子类名
//查看状态	               madong-migrate status	                                显示迁移状态
//批量回滚	               madong-migrate rollback --batch=2	                    回滚到指定批次
//干运行模式	           madong-migrate up --dry-run	                            预览将要执行的迁移（不实际执行）
//导出SQL（含数据）        madong-migrate up --sql=install.sql                       迁移+种子+导出SQL（含表结构和数据）
//导出SQL（无数据）        madong-migrate up --sql=install.sql --no-data          只导出表结构，不包含数据

/**
 * 数据迁移
 *
 * @author Mr.April
 * @since  1.0
 */
class MigrateCommand extends BaseCommand
{
    protected static string $defaultName = 'madong-migrate';
    protected static string $defaultDescription = '数据迁移和种子数据 (迁移+种子+导出SQL)';
    protected ?string $connection = null;
    protected bool $dryRun = false;
    protected bool $exportSql = false;
    protected bool $exportWithData = true;

    protected function configure(): void
    {
        $this->addArgument(
            'operate',
            InputArgument::OPTIONAL,
            'Operation: make/make-seed/up/rollback/seed/status',
            'up',
            ['make', 'make-seed', 'up', 'rollback', 'seed', 'status']
        );
        $this->addArgument('name', InputArgument::OPTIONAL, 'Migration/Seeder name (camelCase)');
        $this->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'DB connection [default: "default"]');
        $this->addOption('seed', null, InputOption::VALUE_NONE, 'Run seeders after migration');
        $this->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'Specify table name for migration (auto-generate if not provided)');
        $this->addOption('batch', 'b', InputOption::VALUE_OPTIONAL, 'Batch number to rollback to (for rollback operation)');
        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Preview migrations without executing (for up operation)');
        $this->addOption('sql', 's', InputOption::VALUE_OPTIONAL, 'Export SQL to file (包含表结构和种子数据), e.g: --sql=install.sql');
        $this->addOption('no-data', null, InputOption::VALUE_NONE, 'Export SQL without data (only table structure)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $operate          = strtolower($input->getArgument('operate'));
        $name             = $input->getArgument('name');
        $this->connection = $input->getOption('connection');
        $runSeed          = $input->getOption('seed');
        $this->dryRun     = $input->getOption('dry-run');
        $sqlOption        = $input->getOption('sql');
        $this->exportWithData = !$input->getOption('no-data');
        // 检测是否使用 --sql 选项（必须指定文件名）
        $this->exportSql  = is_string($sqlOption) && strlen(trim($sqlOption)) > 0;

        $validOperations = ['make', 'make-seed', 'up', 'rollback', 'seed', 'status'];
        if (!in_array($operate, $validOperations)) {
            $output->writeln("<error>❌ Invalid operation: {$operate}</error>");
            return Command::INVALID;
        }

        if ($this->dryRun && !in_array($operate, ['up', 'rollback'])) {
            $output->writeln("<error>❌ --dry-run is only supported for 'up' and 'rollback' operations</error>");
            return Command::INVALID;
        }

        if ($this->exportSql && !in_array($operate, ['up', 'rollback'])) {
            $output->writeln("<error>❌ --sql is only supported for 'up' and 'rollback' operations</error>");
            return Command::INVALID;
        }

        if ($this->dryRun) {
            $output->writeln("<comment>🔍 DRY RUN MODE - No changes will be made</comment>");
        }

        if ($this->exportSql) {
            $output->writeln("<comment>📄 SQL EXPORT MODE - SQL will be saved to file</comment>");
        }

        match ($operate) {
            'make' => $this->runMakeMigration($name, $input, $output),
            'make-seed' => $this->runMakeSeeder($name, $output),
            'up' => $this->runMigrations($output, $runSeed, $sqlOption),
            'rollback' => $this->runRollback($output, $input->getOption('batch'), $sqlOption),
            'seed' => $this->runSeeders($output),
            'status' => $this->runStatus($output),
        };

        if (!$this->dryRun) {
            $output->writeln('<info>✅ Operation completed successfully!</info>');
        }
        return Command::SUCCESS;
    }

    /**
     * 驼峰转下划线（小写）
     */
    protected function camelToSnake(string $str): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $str));
    }

    /**
     * 下划线转驼峰（首字母大写）
     */
    protected function snakeToCamel(string $str): string
    {
        return str_replace('_', '', ucwords($str, '_'));
    }

    /**
     * 生成表名（驼峰转下划线 + 复数）
     */
    protected function generateTableName(string $name): string
    {
        return $this->camelToSnake($name);
    }

    /**
     * 创建迁移文件（修复多余下划线问题）
     */
    protected function runMakeMigration(string $name, InputInterface $input, OutputInterface $output)
    {
        $migrationDir = base_path('resource/database/migrations');
        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0777, true);
        }

        // 1. 获取表名（手动指定或自动生成）
        $specifiedTableName = $input->getOption('table');
        $tableName          = $specifiedTableName ?? $this->generateTableName($name);

        // 提示表名来源
        if ($specifiedTableName) {
            $output->writeln("<info>ℹ️ Using specified table: {$tableName}</info>");
        } else {
            $output->writeln("<info>ℹ️ Generated table: {$tableName}</info>");
        }

        // 2. 生成「文件名前缀」（纯驼峰格式：create + 表名驼峰）
        $tableCamel     = $this->snakeToCamel($tableName); // 如 role_belongs_menus → RoleBelongsMenus
        $filenamePrefix = 'create' . $tableCamel; // 如 createRoleBelongsMenus（关键修正：去掉下划线）

        // 3. 转换为小写蛇形（最终文件名核心：create_role_belongs_menus）
        $snakeFilenamePrefix = $this->camelToSnake($filenamePrefix);

        // 4. 生成完整文件名（时间戳 + 小写蛇形前缀 + .php）
        $fileName = date('Y_m_d_His_') . $snakeFilenamePrefix . '.php';
        $filePath = "{$migrationDir}/{$fileName}";

        if (file_exists($filePath)) {
            $output->writeln("<error>❌ Migration file exists: {$fileName}</error>");
            return;
        }

        // 5. 生成迁移模板（匿名类，文件名小写蛇形）
        $template = $this->generateMigrationTemplate($tableName);
        file_put_contents($filePath, $template);
        $output->writeln("<info>📝 Migration created: {$fileName}</info>");
    }

    /**
     * 生成迁移模板（保持不变）
     */
    protected function generateMigrationTemplate(string $tableName): string
    {
        return <<<EOF
<?php

use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

return new class {

    public function up(Builder \$schema): void
    {
        // 创建数据表（表名：{$tableName}）
        \$schema->create('{$tableName}', function(Blueprint \$table) {
            \$table->bigInteger('id')->primary()->comment('主键');
            \$table->timestamps();
        });
    }

    public function down(Builder \$schema): void
    {
        // 回滚：删除表（表名：{$tableName}）
        \$schema->dropIfExists('{$tableName}');
    }
};
EOF;
    }

    /**
     * 创建种子
     *
     * @param string                                            $name
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function runMakeSeeder(string $name, OutputInterface $output)
    {
        $seederClassPrefix = $this->snakeToCamel($name);

        $seederDir = base_path('resource/database/seeds');
        if (!is_dir($seederDir)) {
            mkdir($seederDir, 0777, true);
        }

        // 🔑 文件名用驼峰前缀 + Seeder.php（确保与类名一致）
        $fileName = "{$seederClassPrefix}Seeder.php";
        $filePath = "{$seederDir}/{$fileName}";

        if (file_exists($filePath)) {
            $output->writeln("<error>❌ Seeder exists: {$fileName}</error>");
            return;
        }

        // 🔑 传入驼峰前缀生成模板（类名直接拼接前缀）
        $template = $this->generateSeederTemplate($seederClassPrefix);
        file_put_contents($filePath, $template);

        // ✅ 明确提示生成的文件和类名
        $output->writeln("<info>📝 Seeder created: {$fileName}</info>");
        $output->writeln("<comment>ℹ️ Seeder class: {$seederClassPrefix}Seeder</comment>");
    }

    /**
     * 生成种子模板
     *
     * @param string $name
     *
     * @return string
     */
    protected function generateSeederTemplate(string $name): string
    {
        $className = ucfirst($name) . 'Seeder';
        return <<<EOF
<?php

declare(strict_types=1);

use support\Db;

class {$className}
{
    public function run(): void
    {
        // 示例：插入用户数据
        /*
        Db::table('users')->insert([
            'username' => 'seed_user',
            'email' => 'seed@example.com',
            'password' => password_hash('secure', PASSWORD_DEFAULT),
        ]);
        */
    }
}
EOF;
    }

    protected function runSeeders(OutputInterface $output)
    {
        $seederDir = base_path('resource/database/seeds');
        if (!is_dir($seederDir)) {
            $output->writeln("<comment>ℹ️ No seeders directory</comment>");
            return;
        }

        $files = glob("{$seederDir}/*.php");
        if (empty($files)) {
            $output->writeln("<comment>ℹ️ No seeders available</comment>");
            return;
        }

        foreach ($files as $file) {
            // 拼接完整命名空间（resource\database\seeds\文件名）
            $fileNameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
            $fullClassName      = 'resource\\database\\seeds\\' . $fileNameWithoutExt; // 完整类名：resource\database\seeds\AdminSeeder

            try {
                // 1. 加载种子文件（注册类到自动加载）
                require_once $file; // 用require_once避免重复加载

                // 2. 检查类是否已正确定义（用完整类名）
                if (!class_exists($fullClassName)) {
                    throw new \InvalidArgumentException("Seeder class '{$fullClassName}' not found in file '{$file}'");
                }

                // 3. 实例化种子类（用完整类名）
                $seederInstance = new $fullClassName();

                // 4. 执行run方法
                $seederInstance->run();

                $output->writeln("<info>✅ Seeded: {$fullClassName}</info>");
            } catch (\Throwable $e) {
                $output->writeln("<error>❌ Error seeding {$fullClassName}: {$e->getMessage()}</error>");
            }
        }
    }

    /**
     * 查看迁移状态
     */
    protected function runStatus(OutputInterface $output)
    {
        $logFile            = $this->getMigrationLogFile();
        $migrationDir       = base_path('resource/database/migrations');
        
        if (!is_dir($migrationDir)) {
            $output->writeln("<comment>ℹ️ No migrations directory found</comment>");
            return;
        }

        $rows = $this->fetchMigrationRows($logFile);
        $existingMigrations = array_column($rows, 1);
        
        // 获取所有迁移文件
        $files = glob("{$migrationDir}/*.php");
        $allMigrations = [];
        foreach ($files as $file) {
            $basename = basename($file, '.php');
            $allMigrations[] = [
                'filename' => $basename,
                'status' => in_array($basename, $existingMigrations) ? '✅ Migrated' : '⏳ Pending'
            ];
        }

        if (empty($allMigrations)) {
            $output->writeln("<comment>ℹ️ No migration files found</comment>");
            return;
        }

        // 按文件名排序
        sort($allMigrations);

        // 按批次分组显示
        $batches = [];
        foreach ($rows as $row) {
            $batches[$row[0]][] = $row[1];
        }

        $output->writeln("<info>📊 Migration Status</info>");
        $output->writeln(str_repeat('=', 50));
        
        $totalMigrated = count($existingMigrations);
        $totalPending = count($allMigrations) - $totalMigrated;
        
        $output->writeln("<info>Migrated: {$totalMigrated} | Pending: {$totalPending} | Total: " . count($allMigrations) . "</info>");
        $output->writeln("<info>Log file: {$logFile}</info>");
        $output->writeln(str_repeat('-', 50));

        // 显示所有迁移及其状态
        foreach ($allMigrations as $migration) {
            if ($migration['status'] === '✅ Migrated') {
                $output->writeln("<info>{$migration['status']}: {$migration['filename']}</info>");
            } else {
                $output->writeln("<comment>{$migration['status']}: {$migration['filename']}</comment>");
            }
        }

        if (!empty($batches)) {
            $output->writeln(str_repeat('-', 50));
            $output->writeln("<info>📦 Migration Batches</info>");
            krsort($batches);
            foreach ($batches as $batch => $migrations) {
                $output->writeln("<comment>Batch {$batch}:</comment> " . implode(', ', $migrations));
            }
        }

        // 日志文件摘要信息（新格式）
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            if (preg_match('/# Format: (.*)/', $logContent, $fmtMatch)) {
                $output->writeln(str_repeat('-', 50));
                $output->writeln("<info>📋 Log Format: {$fmtMatch[1]}</info>");
            }
        }
    }

    /**
     * 迁移
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param bool                                              $runSeed
     * @param string|bool                                       $sqlOption
     */
    protected function runMigrations(OutputInterface $output, bool $runSeed = false, $sqlOption = false)
    {
        $logFile            = $this->getMigrationLogFile();
        $rows               = $this->fetchMigrationRows($logFile);
        $latestBatch        = empty($rows) ? 0 : (int)end($rows)[0];
        $existingMigrations = array_column($rows, 1);
        $newMigrations      = [];


        $migrationDir = base_path('resource/database/migrations');
        
        if (!is_dir($migrationDir)) {
            $output->writeln("<comment>ℹ️ No migrations directory found</comment>");
            return;
        }

        // 使用 glob 替代 DirectoryIterator 提高性能
        $files = glob("{$migrationDir}/*.php");
        
        foreach ($files as $file) {
            $basename = basename($file, '.php');
            if (!in_array($basename, $existingMigrations)) {
                $newMigrations[] = [$file, $basename];
            }
        }

        if (empty($newMigrations)) {
            $output->writeln("<comment>ℹ️ No pending migrations</comment>");
            if ($runSeed) {
                $this->runSeeders($output);
            }
            return;
        }

        sort($newMigrations);
        $schema = $this->getSchemaBuilderWithInnoDB();

        if ($this->dryRun) {
            $output->writeln("<comment>🔍 Pending migrations to execute:</comment>");
            foreach ($newMigrations as $migration) {
                $output->writeln("  - {$migration[1]}");
            }
            return;
        }

        // 如果是导出 SQL 模式
        if ($this->exportSql) {
            $this->exportMigrationsToSql($output, $newMigrations, $sqlOption);
            return;
        }

        $batchNum = $latestBatch + 1;
        $capturedSql = [];
        
        // 启用查询监听以捕获 SQL
        Db::connection($this->connection)->listen(function ($query) use (&$capturedSql) {
            $capturedSql[] = $query->sql;
        });
        
        // MySQL DDL 会触发隐式提交，无法使用事务保护，直接顺序执行
        foreach ($newMigrations as $migration) {
            try {
                $migrationClass = require $migration[0];
                $migrationClass->up($schema);
                $output->writeln("<info>⬆️ Migrated: {$migration[1]}</info>");
            } catch (\Throwable $e) {
                $output->writeln("<error>❌ Error migrating {$migration[1]}: {$e->getMessage()}</error>");
                throw $e;
            }
        }

        // 记录日志（在事务外，确保成功后才记录）- 新格式带状态和耗时
        $now = date('Y-m-d H:i:s');
        
        // 首次写入时加注释头
        if (!file_exists($logFile) || filesize($logFile) === 0) {
            $dir = dirname($logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $connName = $this->connection ?? 'default';
            $header = "# Migration Log - {$connName} - Started: {$now}" . PHP_EOL;
            $header .= "# Format: batch,filename,status,started_at,finished_at,duration(ms)" . PHP_EOL;
            file_put_contents($logFile, $header, LOCK_EX);
        }
        
        $logLines = array_map(
            fn($item) => "{$batchNum},{$item[1]},success,{$now},{$now},0",
            $newMigrations
        );
        file_put_contents($logFile, implode(PHP_EOL, $logLines) . PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($runSeed) {
            $this->runSeeders($output);
        }
    }

    /**
     * 导出迁移 SQL 到文件
     */
    protected function exportMigrationsToSql(OutputInterface $output, array $newMigrations, $sqlOption): void
    {
        $connection = Db::connection($this->connection);
        $prefix = config('database.connections.mysql.prefix', 'ma_');
        
        // 如果需要导出数据且不是干运行模式
        if ($this->exportWithData && !$this->dryRun) {
            // 先运行 seeders（插入初始数据）
            $output->writeln("<comment>🔄 Running seeders before SQL export...</comment>");
            $this->runSeeders($output);
            $output->writeln("<comment>✅ Seeders completed, now generating SQL...</comment>");
        }
        
        // 生成 SQL 文件
        $sqlContent = "-- Migration SQL Export\n";
        $sqlContent .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
        $sqlContent .= "-- Connection: {$this->connection}\n";
        $sqlContent .= "-- Include Data: " . ($this->exportWithData ? 'YES' : 'NO') . "\n\n";
        $sqlContent .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        // 从迁移文件解析并生成 SQL（通过分析迁移文件内容）
        $sqlStatements = $this->parseMigrationsToSql($connection, $newMigrations, $prefix, $this->exportWithData);
        $sqlContent .= $sqlStatements;

        $sqlContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        // 保存文件
        $filename = $sqlOption === true 
            ? 'migration_' . date('YmdHis') . '.sql' 
            : $sqlOption;
        $filepath = base_path($filename);
        
        file_put_contents($filepath, $sqlContent);
        
        $output->writeln("<info>📄 SQL exported to: {$filepath}</info>");
    }

    /**
     * 解析迁移文件生成 SQL
     */
    protected function parseMigrationsToSql($connection, array $migrations, string $prefix, bool $exportData = true): string
    {
        $sqlContent = '';
        
        foreach ($migrations as $migration) {
            $migrationName = $migration[1];
            $tableName = $this->extractTableNameFromMigration($migrationName);
            $fullTableName = $prefix . $tableName;
            
            $sqlContent .= "-- Migration: {$migrationName}\n";
            $sqlContent .= "-- Table: {$fullTableName}\n\n";
            
            // 尝试从数据库获取表结构
            $createSql = $this->generateCreateTableSql($connection, $fullTableName);
            
            if ($createSql) {
                // 表已存在，使用现有结构
                $sqlContent .= "DROP TABLE IF EXISTS `{$fullTableName}`;\n";
                $sqlContent .= $createSql . ";\n\n";
                
                // 导出表中的数据（如果启用）
                if ($exportData) {
                    $insertSql = $this->generateInsertDataSql($connection, $fullTableName);
                    if ($insertSql) {
                        $sqlContent .= "-- Data from {$fullTableName}\n";
                        $sqlContent .= $insertSql . "\n\n";
                    }
                }
            } else {
                // 表不存在，生成基本的 CREATE TABLE 语句
                $sqlContent .= "-- Table {$fullTableName} does not exist, please run migration first\n";
                $sqlContent .= "-- Or manually create the table based on: {$migrationName}\n\n";
            }
        }
        
        return $sqlContent;
    }
    
    /**
     * 生成 INSERT 数据 SQL
     */
    protected function generateInsertDataSql($connection, string $tableName): ?string
    {
        try {
            $rows = $connection->select("SELECT * FROM `{$tableName}`");
            if (empty($rows)) {
                return null;
            }
            
            $insertStatements = [];
            foreach ($rows as $row) {
                $row = (array)$row;
                $columns = array_keys($row);
                $values = array_values($row);
                
                // 处理值
                $escapedValues = array_map(function ($value) use ($connection) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return "'" . $connection->getPdo()->quote($value) . "'";
                }, $values);
                
                $insertStatements[] = sprintf(
                    "INSERT INTO `{$tableName}` (`%s`) VALUES (%s);",
                    implode('`, `', $columns),
                    implode(', ', $escapedValues)
                );
            }
            
            return implode("\n", $insertStatements);
        } catch (\Throwable) {
            // 如果查询失败（如视图），返回 null
            return null;
        }
    }

    /**
     * 从迁移文件名提取表名
     */
    protected function extractTableNameFromMigration(string $migrationName): string
    {
        // 例如: 2025_10_10_162850_create_cache -> cache
        // 例如: 2025_10_10_162851_create_sys_admin -> sys_admin
        if (preg_match('/create_(\w+)$/', $migrationName, $matches)) {
            return $matches[1];
        }
        // 对于修改表的迁移，尝试提取表名
        if (preg_match('/_(add|alter|drop|optimize)_(.+)$/', $migrationName, $matches)) {
            return $matches[2];
        }
        return $migrationName;
    }

    /**
     * 生成 CREATE TABLE SQL
     */
    protected function generateCreateTableSql($connection, string $tableName): ?string
    {
        try {
            $result = $connection->select("SHOW CREATE TABLE `{$tableName}`");
            if (!empty($result)) {
                $row = (array)$result[0];
                return $row['Create Table'] ?? $row['Create View'] ?? null;
            }
        } catch (\Throwable) {
            // 表不存在，忽略
        }
        return null;
    }

    /**
     * 回滚
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param int|null                                           $batchNum
     * @param string|bool                                         $sqlOption
     */
    protected function runRollback(OutputInterface $output, ?int $batchNum = null, $sqlOption = false)
    {
        $logFile       = $this->getMigrationLogFile();
        $rows          = $this->fetchMigrationRows($logFile);

        if (empty($rows)) {
            $output->writeln("<comment>ℹ️ No migrations to rollback</comment>");
            return;
        }

        if ($batchNum !== null) {
            // 回滚到指定批次
            $rollbackBatch = array_filter($rows, fn($row) => (int)$row[0] > $batchNum);
            if (empty($rollbackBatch)) {
                $output->writeln("<comment>ℹ️ No migrations to rollback for batch {$batchNum}</comment>");
                return;
            }
        } else {
            // 回滚最新批次（默认行为）
            $latestBatch   = (int)end($rows)[0];
            $rollbackBatch = array_filter($rows, fn($row) => (int)$row[0] === $latestBatch);
        }
        
        $rollbackBatch = array_reverse($rollbackBatch);

        if ($this->dryRun) {
            $output->writeln("<comment>🔍 Migrations to rollback:</comment>");
            foreach ($rollbackBatch as $item) {
                $output->writeln("  - {$item[1]}");
            }
            return;
        }

        // 如果是导出 SQL 模式
        if ($this->exportSql) {
            $this->exportRollbackToSql($output, $rollbackBatch, $sqlOption);
            return;
        }

        $schema = $this->getSchemaBuilderWithInnoDB();
        
        // MySQL DDL 会触发隐式提交，无法使用事务保护，直接顺序执行
        foreach ($rollbackBatch as $item) {
            try {
                $migrationClass = require base_path("resource/database/migrations/{$item[1]}.php");
                $migrationClass->down($schema);
                $output->writeln("<info>⬇️ Rolled back: {$item[1]}</info>");
            } catch (\Throwable $e) {
                $output->writeln("<error>❌ Error rolling back {$item[1]}: {$e->getMessage()}</error>");
                throw $e;
            }
        }

        // 清理日志
        $latestBatch = (int)end($rows)[0];
        $remainingLogs = array_filter($rows, fn($row) => $batchNum !== null 
            ? (int)$row[0] <= $batchNum 
            : (int)$row[0] < $latestBatch);
        file_put_contents($logFile, implode(PHP_EOL, array_map(fn($row) => implode(',', $row), $remainingLogs)));
    }

    /**
     * 导出回滚 SQL 到文件
     */
    protected function exportRollbackToSql(OutputInterface $output, array $rollbackBatch, $sqlOption): void
    {
        $prefix = config('database.connections.mysql.prefix', 'ma_');
        
        // 生成 SQL 文件
        $sqlContent = "-- Rollback SQL Export\n";
        $sqlContent .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
        $sqlContent .= "-- Connection: {$this->connection}\n\n";
        $sqlContent .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        // 为每个要回滚的迁移生成 DROP TABLE 语句
        foreach ($rollbackBatch as $item) {
            $tableName = $this->extractTableNameFromMigration($item[1]);
            $fullTableName = $prefix . $tableName;
            
            // 尝试获取表结构用于生成 DROP 语句
            $sqlContent .= "-- Rollback: {$item[1]}\n";
            $sqlContent .= "-- Table: {$fullTableName}\n";
            
            // 添加 DROP TABLE 语句
            $sqlContent .= "DROP TABLE IF EXISTS `{$fullTableName}`;\n\n";
        }

        $sqlContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        // 保存文件
        $filename = $sqlOption === true 
            ? 'rollback_' . date('YmdHis') . '.sql' 
            : $sqlOption;
        $filepath = base_path($filename);
        
        file_put_contents($filepath, $sqlContent);
        
        $output->writeln("<info>📄 Rollback SQL exported to: {$filepath}</info>");
    }

    protected function getMigrationLogFile(): string
    {
        return runtime_path('migrations/main/' . ($this->connection ?? 'default') . '-migrations.log');
    }

    protected function fetchMigrationRows(string $logFile): array
    {
        if (!file_exists($logFile)) {
            $dir = dirname($logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            touch($logFile);
        }
        $content = trim(file_get_contents($logFile));
        if (empty($content)) {
            return [];
        }
        
        $rows = [];
        foreach (explode(PHP_EOL, $content) as $line) {
            $line = trim($line);
            // 跳过注释行
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            $rows[] = explode(',', $line);
        }
        return $rows;
    }

    protected function getSchemaBuilder(): \Illuminate\Database\Schema\Builder
    {
        $schema = Db::connection($this->connection)->getSchemaBuilder();
        // 设置默认使用 InnoDB 引擎
        $schema->blueprintResolver(function ($table, $callback) {
            return new \Illuminate\Database\Schema\Blueprint($table, $callback);
        });
        return $schema;
    }

    /**
     * 获取带引擎配置的表创建回调
     */
    protected function getSchemaBuilderWithInnoDB(): \Illuminate\Database\Schema\Builder
    {
        $schema = $this->getSchemaBuilder();
        // 确保使用 InnoDB 引擎
        Db::connection($this->connection)->statement("SET default_storage_engine=INNODB");
        return $schema;
    }
}
