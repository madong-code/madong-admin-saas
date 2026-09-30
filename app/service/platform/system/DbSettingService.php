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
namespace app\service\platform\system;

use app\dao\tenant\DbSettingDao;
use core\foundation\base\BaseService;
use core\infrastructure\cache\CacheService;

/**
 * 数据源管理 Service
 */
class DbSettingService extends BaseService
{
    protected CacheService $cache;

    public function __construct(DbSettingDao $dao, CacheService $cache)
    {
        $this->dao   = $dao;
        $this->cache = $cache;
    }

    /**
     * 保存数据源配置并初始化数据库
     * 重写父类 save，将数据库创建与配置保存放在一起，失败时自动回滚
     *
     * 支持两种模式：
     * - is_existing=0（默认）：测试连接 → 创建库并执行迁移 → 保存记录
     * - is_existing=1：测试连接（含指定库） → 跳过建库和迁移 → 保存记录
     */
    public function save(array $data): ?\Illuminate\Database\Eloquent\Model
    {
        $isExisting = !empty($data['is_existing']);

        // 先测试连接
        $testResult = $this->testConnection($data);
        if (!$testResult['success']) {
            throw new \RuntimeException('连接测试失败: ' . $testResult['message']);
        }

        if ($isExisting) {
            // 使用现有库：额外验证指定数据库是否可达
            $testWithDb = $this->testConnectionWithDatabase($data);
            if (!$testWithDb['success']) {
                throw new \RuntimeException('数据库连接失败（请确认数据库 "' . $data['database'] . '" 已存在且可访问）: ' . $testWithDb['message']);
            }
        } else {
            // 初始化数据库（创建库并执行迁移）
            $initResult = $this->initDatabase($data);
            if (!$initResult['success']) {
                throw new \RuntimeException('数据库初始化失败: ' . ($initResult['message'] ?? '未知错误'));
            }
        }

        // 将 is_existing 存入 extra_config
        $extraConfig = $data['extra_config'] ?? [];
        if (is_string($extraConfig)) {
            $extraConfig = json_decode($extraConfig, true) ?? [];
        }
        $extraConfig['is_existing'] = $isExisting;
        $data['extra_config'] = $extraConfig;
        unset($data['is_existing']);

        // 保存数据源配置记录
        return $this->dao->save($data);
    }

    /**
     * 更新数据源配置
     * 编辑只更新配置记录，不重新初始化数据库（表由新建或初始化流程创建）
     * 如果连接信息（database/host/port）变更，仅测试新连接是否可达
     */
    public function update(int|string $id, array $data): bool
    {
        // 获取当前记录
        $old = $this->dao->get($id);
        if (!$old) {
            throw new \RuntimeException('数据源不存在');
        }

        // 检查是否修改了连接相关的关键字段
        $connectionChanged = false;
        foreach (['database', 'host', 'port'] as $field) {
            if (isset($data[$field]) && (string)$data[$field] !== (string)$old->$field) {
                $connectionChanged = true;
                break;
            }
        }

        if ($connectionChanged) {
            // 补齐密码（编辑时前端不传密码则用旧密码）
            if (empty($data['password'])) {
                $data['password'] = $old->password;
            }

            // 只测试连接，不重新建表
            $testResult = $this->testConnection($data);
            if (!$testResult['success']) {
                throw new \RuntimeException('新数据库连接测试失败: ' . $testResult['message']);
            }
        }

        return (bool) $this->dao->update($id, $data);
    }

    /** 获取支持的驱动列表 */
    public function getDriverList(): array
    {
        return [
            ['value' => 'mysql', 'label' => 'MySQL'],
            ['value' => 'pgsql', 'label' => 'PostgreSQL'],
            ['value' => 'sqlite', 'label' => 'SQLite'],
            ['value' => 'sqlsrv', 'label' => 'SQL Server'],
        ];
    }

    /** 获取已启用的数据源列表 */
    public function getEnabledList()
    {
        return $this->dao->getEnabledList();
    }

    /** 切换启用状态 */
    public function toggleEnabled(int|string $id): void
    {
        $model = $this->dao->get($id);
        $model->enabled = !$model->enabled;
        $model->save();
    }

    /** 测试连接 */
    public function testConnection(array $data): array
    {
        try {
            $host = $data['host'] ?? '';
            $port = (int)($data['port'] ?? 3306);
            $user = $data['username'] ?? '';
            $pass = $data['password'] ?? '';
            // 不指定 dbname，因为数据库可能还不存在
            $dsn = sprintf('%s:host=%s;port=%d;charset=utf8mb4', $data['driver'], $host, $port);
            new \PDO($dsn, $user, $pass);
            return ['success' => true, 'message' => '连接成功'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** 测试连接（含指定数据库） */
    public function testConnectionWithDatabase(array $data): array
    {
        try {
            $host = $data['host'] ?? '';
            $port = (int)($data['port'] ?? 3306);
            $user = $data['username'] ?? '';
            $pass = $data['password'] ?? '';
            $db   = $data['database'] ?? '';
            $dsn  = sprintf('%s:host=%s;port=%d;dbname=%s;charset=utf8mb4', $data['driver'], $host, $port, $db);
            new \PDO($dsn, $user, $pass);
            return ['success' => true, 'message' => '连接成功'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 要跳过的表名
     */
    private array $skipTables = [];

    /**
     * 初始化数据源数据库（执行所有 migration）
     * @param array $data         数据源配置
     * @param array $skipTables   要跳过的表名
     * @param array $onlyTables   仅创建的表名（为空则全部）
     * @param array $skipFiles    要跳过的 migration 文件名
     */
    public function initDatabase(array $data, array $skipTables = [], array $onlyTables = [], array $skipFiles = []): array
    {
        $this->skipTables = $skipTables;

        $prefix = $data['prefix'] ?? '';
        $driver = $data['driver'] ?? 'mysql';
        $host   = $data['host'] ?? '127.0.0.1';
        $port   = (int)($data['port'] ?? 3306);
        $dbName = $data['database'];
        $user   = $data['username'];
        $pass   = $data['password'] ?? '';

        try {
            // 创建数据库
            $dsnNoDb = sprintf('%s:host=%s;port=%d;charset=utf8mb4', $driver, $host, $port);
            $pdoNoDb = new \PDO($dsnNoDb, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdoNoDb->exec(sprintf(
                "CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",
                $dbName
            ));
            $pdoNoDb = null;

            // 连接目标库
            $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=utf8mb4', $driver, $host, $port, $dbName);
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            $connection = new \Illuminate\Database\MySqlConnection($pdo, $dbName, $prefix, [
                'driver' => $driver, 'host' => $host, 'port' => $port,
                'database' => $dbName, 'username' => $user, 'password' => $pass,
                'prefix' => $prefix, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_general_ci',
                'engine' => 'InnoDB',
            ]);
            $connection->setReconnector(function () use ($driver, $host, $port, $dbName, $user, $pass) {
                $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=utf8mb4', $driver, $host, $port, $dbName);
                return new \PDO($dsn, $user, $pass, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
            });

            $schema = $connection->getSchemaBuilder();

            // 记录建表前的表列表
            $tablesBefore = array_column($pdo->query('SHOW TABLES')->fetchAll(), 'Tables_in_' . $dbName);

            $migrationDir = base_path('resource/database/migrations');
            $files = glob($migrationDir . '/*.php');
            sort($files);

            $errors = [];

            foreach ($files as $file) {
                $basename = basename($file);
                if (in_array($basename, $skipFiles)) continue;

                $source = file_get_contents($file);
                if (preg_match('/@skip\b|--\s*skip/i', substr($source, 0, strpos($source, 'use ') ?: 500))) continue;

                $migration = require $file;
                $ref = new \ReflectionObject($migration);
                if ($ref->hasProperty('schema')) {
                    $prop = $ref->getProperty('schema');
                    $prop->setAccessible(true);
                    $prop->setValue($migration, $schema);
                }

                try {
                    $refMethod = $ref->getMethod('up');
                    $refMethod->setAccessible(true);
                    $refMethod->invoke($migration, $schema);
                } catch (\Illuminate\Database\QueryException $e) {
                    // 表/索引已存在：幂等跳过（PDO SQLSTATE 非数字时 getCode() 常为 0）
                    if ($this->isIgnorableSchemaError($e)) {
                        continue;
                    }
                    $errors[] = $basename . ": {$e->getMessage()}";
                } catch (\Throwable $e) {
                    $errors[] = $basename . ": {$e->getMessage()}";
                }
            }

            // 对比建表后差异
            $tablesAfter = array_column($pdo->query('SHOW TABLES')->fetchAll(), 'Tables_in_' . $dbName);
            $created = array_values(array_diff($tablesAfter, $tablesBefore));

            $connection->disconnect();

            $message = count($created) . ' 张新建, 共 ' . count($tablesAfter) . ' 张表';
            if (!empty($errors)) {
                $message .= ', ' . count($errors) . ' 个错误: ' . implode('; ', $errors);
            }

            return [
                'success' => empty($errors),
                'created' => $created,
                'total'   => count($tablesAfter),
                'errors'  => $errors,
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
        }
    }

    /**
     * 判断是否为可忽略的 schema 错误（表/索引已存在等幂等场景）
     */
    private function isIgnorableSchemaError(\Illuminate\Database\QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        $driverCode = (string) ($e->errorInfo[1] ?? '');
        $message = $e->getMessage();

        // SQLSTATE / MySQL errno / 文案兜底
        $codes = [$sqlState, $driverCode];
        foreach (['1050', '42S01', '42P07', '1061', '1062'] as $code) {
            if (in_array($code, $codes, true)) {
                return true;
            }
        }

        return (bool) preg_match(
            '/already exists|Duplicate (table|key|entry|column)|Base table or view already exists/i',
            $message
        );
    }
}
