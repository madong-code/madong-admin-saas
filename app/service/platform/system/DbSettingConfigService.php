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

/**
 * 数据源配置 Service
 */
class DbSettingConfigService extends BaseService
{
    public function __construct(DbSettingDao $dao)
    {
        $this->dao = $dao;
    }

    /** 获取数据源配置选项 */
    public function getConfigOptions(): array
    {
        return $this->dao->getEnabledConfigs();
    }

    /** 获取数据源下的表列表 */
    public function getTables(int $dataSourceId): array
    {
        $config = $this->dao->get($dataSourceId)?->toConfig();
        if (!$config) return [];
        return [];
    }

    /** 获取表结构信息 */
    public function getTableInfo(int $dataSourceId, string $tableName): array
    {
        return ['dataSourceId' => $dataSourceId, 'tableName' => $tableName, 'columns' => []];
    }

    /** 绑定数据源到租户 */
    public function bindToTenant(int $dataSourceId, int $tenantId): void {}

    /** 批量绑定 */
    public function batchBindToTenant(array $dataSourceIds, int $tenantId): void
    {
        foreach ($dataSourceIds as $id) {
            $this->bindToTenant((int)$id, $tenantId);
        }
    }

    /** 解绑 */
    public function unbind(int $configId): void {}

    /** 获取统计数据 */
    public function getStats(): array
    {
        return [
            'total'   => $this->dao->getCount([]),
            'enabled' => $this->dao->getCount(['enabled' => 1]),
        ];
    }

    /** 测试连接 */
    public function testConnection(array $data): bool
    {
        try {
            $dsn = sprintf('%s:host=%s;port=%d;dbname=%s', $data['driver'], $data['host'], $data['port'], $data['database']);
            new \PDO($dsn, $data['username'], $data['password']);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** 同步表结构 */
    public function syncStructure(int $dataSourceId, string $tableName): void {}
}
