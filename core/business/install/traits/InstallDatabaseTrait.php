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
 * Official Website: https://madong.tech
 */

namespace core\business\install\traits;

/**
 * 安装数据库基础 Trait
 */
trait InstallDatabaseTrait
{
    protected string $prefix;
    protected ?\PDO $pdo = null;
    protected array $dbConfig = [];
    protected int $currentTime;

    /**
     * 初始化配置
     */
    protected function initDbConfig(array $dbConfig): void
    {
        $this->dbConfig = $dbConfig;
        $this->prefix = $dbConfig['prefix'] ?? 'ma_';
        $this->currentTime = time();
    }

    /**
     * 获取 PDO 连接（单例）
     */
    protected function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            $config = $this->dbConfig;
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '3306',
                $config['database'] ?? 'md_admin'
            );
            $this->pdo = new \PDO(
                $dsn,
                $config['username'] ?? 'root',
                $config['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        }
        return $this->pdo;
    }

    /**
     * 获取带前缀的表名
     */
    protected function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /**
     * 统一插入数据
     */
    protected function insert(string $tableName, array $data): bool
    {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($values), '?');
        $sql = "INSERT INTO `{$tableName}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->getPdo()->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * 插入数据并返回自增ID
     */
    protected function insertGetId(string $tableName, array $data): int
    {
        $this->insert($tableName, $data);
        return (int) $this->getPdo()->lastInsertId();
    }
}
