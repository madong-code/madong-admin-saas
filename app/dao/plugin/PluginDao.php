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

namespace app\dao\plugin;

use app\model\plugin\Plugin;
use core\foundation\base\BaseDao;
use madong\query\QueryBuilderHelper;

/**
 * Plugin数据访问层
 *
 * @author Mr.April
 * @since  1.0
 */
class PluginDao extends BaseDao
{

    protected function setModel(): string
    {
        return Plugin::class;
    }

    /**
     * 获取插件列表（分页）
     *
     * @param array $where 查询条件（标准filters格式）
     * @param int   $page  页码
     * @param int   $limit 每页数量
     *
     * @return array
     * @throws \Exception
     */
    public function getList(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->query();

        // 应用filters条件（标准格式：包含filters数组）
        if (isset($where['filters']) && is_array($where['filters'])) {
            QueryBuilderHelper::applyFiltersToQuery($query, $where['filters']);
        }

        $total = $query->count();
        $list  = $query->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->toArray();

        return [
            'total' => $total,
            'items' => $list,
        ];
    }

    /**
     * 根据key查询插件
     *
     * @param string $key 插件标识
     *
     * @return Plugin|null
     * @throws \Exception
     */
    public function findByKey(string $key): ?Plugin
    {
        return $this->query()->where('key', $key)->first();
    }

    /**
     * 获取所有插件的key列表
     *
     * @return array
     * @throws \Exception
     */
    public function getAllKeys(): array
    {
        return $this->query()->pluck('key')->toArray();
    }

    /**
     * 批量插入插件
     *
     * @param array $plugins 插件数据数组
     *
     * @return bool
     * @throws \Exception
     */
    public function batchInsert(array $plugins): bool
    {
        if (empty($plugins)) {
            return true;
        }

        $model = $this->getModel();
        foreach ($plugins as $plugin) {
            // 检查插件是否已存在
            $exists = $model->where('key', $plugin['key'])->exists();
            if (!$exists) {
                $model->create($plugin);
            }
        }
        return true;
    }
}