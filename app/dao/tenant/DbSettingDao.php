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

namespace app\dao\tenant;

use app\model\tenant\DbSetting;
use core\foundation\base\BaseDao;
use Illuminate\Database\Eloquent\Collection;

/**
 * 数据库设置 DAO
 */
class DbSettingDao extends BaseDao
{
    
    protected function setModel(): string
    {
        return DbSetting::class;
    }
    
    /**
     * 获取数据源列表
     *
     * @param array $where 查询条件
     * @param string|array $field 查询字段
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string $order 排序
     * @param array $with 关联查询
     * @param bool $search 是否启用搜索
     * @param array|null $withoutScopes 排除的Scope
     *
     * @return Collection|null
     * @throws \Exception
     */
    public function getList(array $where = [], string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?Collection
    {
        return $this->selectList($where, $field, $page, $limit, $order, $with, $search, $withoutScopes);
    }
    
    /**
     * 获取启用的数据源列表
     *
     * @return Collection
     */
    public function getEnabledList(): Collection
    {
        return $this->getModel()::where('enabled', 1)
            ->orderBy('id', 'asc')
            ->get();
    }
    
    /**
     * 根据数据库名称查找
     *
     * @param string $database
     * @return DbSetting|null
     */
    public function findByDatabase(string $database): ?DbSetting
    {
        return $this->getModel()::where('database', $database)->first();
    }
    
    /**
     * 检查数据库名称是否存在
     *
     * @param string $database
     * @param int|null $excludeId 排除的ID
     * @return bool
     */
    public function existsByDatabase(string $database, ?int $excludeId = null): bool
    {
        $query = $this->getModel()::where('database', $database);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }
    
    /**
     * 获取所有启用的数据源配置
     *
     * @return array
     */
    public function getEnabledConfigs(): array
    {
        $configs = [];
        $list = $this->getEnabledList();
        
        foreach ($list as $item) {
            $configs[$item->database] = $item->toConfig();
        }
        
        return $configs;
    }
}
