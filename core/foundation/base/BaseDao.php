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
namespace core\foundation\base;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use madong\helper\Arr;
use ReflectionException;
use support\Db;
use Throwable;
use madong\query\traits\WithQueryParams;

/**
 * @method count(array $where = [], bool $search = true)
 * @method selectList(array $where, array|string $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false)
 * @method selectModel(array $where, string $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false)
 * @method getCount(array $where)
 * @method getDistinctCount(array $where, $field, bool $search = true)
 * @method getPk()
 * @method getTableName()
 * @method get($id, ?array $field = null, ?array $with = [], string $order = '', ?array $withoutScopes = null): ?Model
 * @method be($map, string $field = '')
 * @method getOne(array $where, ?string $field = '*', array $with = [])
 * @method value($where, ?string $field = '')
 * @method getColumn(array $where, string $field, string $key = '')
 * @method delete(array|int|string $id, ?string $key = null)
 * @method destroy(mixed $id, bool $force = false)
 * @method update(string|int|array $id, array $data, ?string $key = null)
 * @method setWhere($where, ?string $key = null)
 * @method batchUpdate(array $ids, array $data, ?string $key = null)
 * @method save(array $data)
 * @method saveAll(array $data)
 * @method getFieldValue($value, string $filed, ?string $valueKey = '', ?array $where = [])
 * @method search(array $where = [], bool $search = true)
 * @method sum(array $where, string $field, bool $search = false)
 * @method bcInc($key, string $incField, string $inc, ?string $keyField = null, int $acc = 2)
 * @method bcDec($key, string $decField, string $dec, ?string $keyField = null, int $acc = 2)
 * @method getMax(array $where = [], string $field = '')
 * @method getMin(array $where = [], string $field = '')
 * @method decStockIncSales(array $where, int $num, string $stock = 'stock', string $sales = 'sales')
 * @method incStockDecSales(array $where, int $num, string $stock = 'stock', string $sales = 'sales')
 */

/**
 * 基础Dao
 *
 * @author Mr.April
 * @since  1.0
 */
abstract class BaseDao
{
    use WithQueryParams;

    /**
     * 获取当前模型
     *
     * @return string
     */
    abstract protected function setModel(): string;

    /**
     * 获取条数
     *
     * @param array $where
     * @param bool  $search
     *
     * @return int
     * @throws Exception
     */
    public function count(array $where = [], bool $search = false): int
    {
        $query = $this->getModel()->query();
        
        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions($search));
        }

        return $query->count();
    }

    /**
     * 查询列表
     *
     * @param array        $where
     * @param string|array $field
     * @param int          $page
     * @param int          $limit
     * @param string       $order
     * @param array        $with
     * @param bool         $search
     * @param array|null   $withoutScopes
     *
     * @return Collection|null
     * @throws \Exception
     */
    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?Collection
    {
        // 使用 selectModel 方法获取查询构建器
        $query = $this->selectModel($where, $field, $page, $limit, $order, $with, $search, $withoutScopes);

        // 如果字段不是 '*' 或 ['*']，则应用 selectRaw()
        $isWildcard = ($field === '*' || ($field === ['*']));
        if (!$isWildcard) {
            if (is_array($field)) {
                // 如果是数组，转换为字符串
                $field = implode(',', $field);
            }
            $query->selectRaw($field);
        }

        // 应用分页
        if ($page > 0 && $limit > 0) {
            // 只返回数据部分
            return $query->paginate($limit, ['*'], 'page', $page)->getCollection();
        }
        return $query->get(); // 返回所有数据
    }

    /**
     * 获取某些条件数据
     *
     * @param array        $where
     * @param array|string $field
     * @param int          $page
     * @param int          $limit
     * @param string       $order
     * @param array        $with
     * @param bool         $search
     * @param array|null   $withoutScopes
     *
     * @return Builder|\Illuminate\Database\Query\Builder|LengthAwarePaginator|null
     * @throws Exception
     */
    public function selectModel(array $where, array|string $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): Builder|\Illuminate\Database\Query\Builder|LengthAwarePaginator|null
    {
        // 获取模型的查询构建器
        $query = $this->getModel()->query();

        // 作用域处理
        if (!empty($withoutScopes)) {
            $this->applyScopeRemoval($query, $withoutScopes);
        }

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $options = [
                'keyword_fields' => $this->getKeywordFields(),
            ];
            
            // $search 为 false 时,禁用搜索器
            if (!$search) {
                $options['scopes'] = [];
            }
            
            $query = $this->applyQueryParams($query, $where, $options);
        }

        // 应用字段选择
        $isWildcard = ($field === '*' || ($field === ['*']));
        if (!$isWildcard) {
            if (is_array($field)) {
                // 过滤空值并合并为字符串
                $field = array_filter($field, function ($f) {
                    return !empty($f);
                });
                $field = implode(',', $field);
            }

            if (!empty($field)) {
                $query->selectRaw($field);
            }
        }
        // 应用分页和其他查询条件
        if ($page > 0 && $limit > 0) {
            $query->paginate($limit, ['*'], 'page', $page);
        }
        if ($order !== '') {
            $query->orderByRaw($order);
        }
        if (!empty($with)) {
            $query->with($with);
        }
        return $query; // 返回查询构建器
    }

    /**
     * 获取条数
     *
     * @param array $where
     *
     * @return int
     * @throws Exception
     */
    public function getCount(array $where): int
    {
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }

        return $query->count();
    }

    /**
     * 获取条数
     *
     * @param array  $where
     * @param string $field
     *
     * @return int
     * @throws Exception
     */
    public function getDistinctCount(array $where, string $field): int
    {
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }

        return $query->distinct()->count($field);
    }

    /**
     * 获取模型
     *
     * @return mixed
     * @throws Exception
     */
    public function getModel(): mixed
    {
        try {
            $className = $this->setModel();
            if (!class_exists($className)) {
                throw new Exception($className . ' 不是一个有效的模型类');
            }
            return new $className();
        } catch (Throwable $e) {
            throw new Exception($className . ' 未知模型: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取查询构建器
     *
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws Exception
     */
    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->getModel()->query();
    }
    
    /**
     * 根据ID查找记录
     *
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model|null
     * @throws Exception
     */
    public function find(int $id): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->query()->find($id);
    }
    
    /**
     * 添加查询条件
     *
     * @param mixed $column
     * @param mixed $operator
     * @param mixed $value
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws Exception
     */
    public function where($column, $operator = null, $value = null): \Illuminate\Database\Eloquent\Builder
    {
        return $this->query()->where($column, $operator, $value);
    }

    /**
     * 获取模型主键
     *
     * @return string
     * @throws Exception
     */
    public function getPk(): string
    {
        return $this->getModel()->getKeyName();
    }

    /**
     * 获取表名
     *
     * @return string
     * @throws ReflectionException|Exception
     */
    public function getTableName(): string
    {
        return $this->getModel()->getTable();
    }

    /**
     * 获取一条数据
     *
     * @param            $id
     * @param array|null $field
     * @param array|null $with
     * @param string     $order
     * @param array|null $withoutScopes
     *
     * @return Model|null
     * @throws \Exception
     */
    public function get($id, ?array $field = null, ?array $with = [], string $order = '', ?array $withoutScopes = null): ?Model
    {
        $where = is_array($id) ? $id : [$this->getPk() => $id];
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }

        $this->applyScopeRemoval($query, $withoutScopes);
        // 添加关联加载
        if (!empty($with)) {
            $query->with($with);
        }
        // 添加排序条件
        if ($order !== '') {
            $query->orderByRaw($order);
        }
        return $query->select($field ?? ['*'])->first();
    }

    /**
     * 查询一条数据是否存在
     *
     * @param        $map
     * @param string $field
     *
     * @return bool
     * @throws Exception
     */
    public function be($map, string $field = ''): bool
    {
        // 如果 $map 不是数组且 $field 为空，使用主键
        if (!is_array($map) && empty($field)) {
            $field = $this->getPk();
        }

        // 如果 $map 不是数组，将其转换为数组
        $map = !is_array($map) ? [$field => $map] : $map;

        // 使用 Eloquent 查询构建器检查记录是否存在
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($map)) {
            $query = $this->applyQueryParams($query, $map, $this->getQueryOptions(true));
        }

        return $query->exists();
    }

    /**
     * 根据条件获取一条数据
     *
     * @param array       $where
     * @param string|null $field
     * @param array       $with
     *
     * @return Model|null
     * @throws Exception
     */
    public function getOne(array $where, ?string $field = '*', array $with = []): ?Model
    {
        // 将字段字符串转换为数组
        $fieldArray = $field === '*' ? ['*'] : explode(',', $field);

        // 使用 Eloquent 查询构建器获取一条数据
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }

        // 添加关联加载
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->select($fieldArray)->first();
    }

    /**
     * 获取某字段的值
     *
     * @param             $where
     * @param string|null $field
     *
     * @return mixed
     * @throws Exception
     */
    public function value($where, ?string $field = null): mixed
    {
        $pk    = $this->getPk(); // 获取主键
        $where = $this->setWhere($where); // 设置查询条件

        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }

        return $query->value($field ?? $pk); // 返回指定字段的值，默认为主键
    }

    /**
     * 获取某个字段数组
     *
     * @param array  $where
     * @param string $field
     * @param string $key
     *
     * @return array
     * @throws ReflectionException|Exception
     */
    public function getColumn(array $where, string $field, string $key = ''): array
    {
        // 使用 Eloquent 查询构建器获取字段数组
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件（禁用排序，避免 Pivot 表没有 id 字段的问题）
        if (!empty($where)) {
            $options = $this->getQueryOptions(true);
            $options['apply_sort'] = false; // 禁用排序
            $query = $this->applyQueryParams($query, $where, $options);
        }

        // 如果指定了键，则使用 keyBy 方法
        if ($key) {
            return $query->pluck($field, $key)->toArray();
        }
        // 否则，直接获取字段数组
        return $query->pluck($field)->toArray();
    }

    /**
     * 删除
     *
     * @param array|int|string $id
     * @param string|null      $key
     *
     * @return mixed
     * @throws Exception
     */
    public function delete(array|int|string $id, ?string $key = null): int
    {
        try {
            $query = $this->getModel()->query();
            if (is_array($id)) {
                if (array_is_list($id)) {
                    //主键列表批量删除
                    return $this->getModel()->destroy($id);
                } else {
                    foreach ($id as $field => $value) {
                        $query->where($field, $value);
                    }
                }
            } else {
                $query->where($key ?: $this->getPk(), $id);
            }
            $models = $query->get();
            if ($models->isEmpty()) {
                return 0;
            }
            foreach ($models as $model) {
                $model->delete();//触发事件
            }
            return $models->count();

        } catch (Exception $e) {
            $code = (int)$e->getCode();
            throw new Exception("删除失败:" . $e->getMessage(), $code);
        }
    }

    /**
     * 删除记录
     *
     * @param mixed $id
     * @param bool  $force
     *
     * @return bool
     * @throws Exception
     */
    public function destroy(mixed $id, bool $force = false): bool
    {
        // 使用 Eloquent 的 destroy 方法删除记录
        return $this->getModel()->destroy($id, $force) > 0;
    }

    /**
     * 更新
     *
     * @param string|int|array $id
     * @param array            $data
     * @param string|null      $key
     *
     * @return mixed
     * @throws Exception
     */
    public function update(string|int|array $id, array $data, ?string $key = null): mixed
    {
        $where = is_array($id) ? $id : [is_null($key) ? $this->getPk() : $key => $id];
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, []);
        }

        return $query->update($data);
    }

    /**
     * setWhere
     *
     * @param             $where
     * @param string|null $key
     *
     * @return array
     * @throws Exception
     */
    protected function setWhere($where, ?string $key = null): array
    {
        // 如果 $where 不是数组，则构建数组
        if (!is_array($where)) {
            $where = [is_null($key) ? $this->getPk() : $key => $where];
        }
        return $where;
    }

    /**
     * 批量更新
     *
     * @param array       $ids
     * @param array       $data
     * @param string|null $key
     *
     * @return mixed
     * @throws Exception
     */
    public function batchUpdate(array $ids, array $data, ?string $key = null): bool
    {
        return $this->getModel()->whereIn(is_null($key) ? $this->getPk() : $key, $ids)->update($data);
    }

    /**
     * 保存返回模型
     *
     * @param array $data
     *
     * @return Model|null
     * @throws Exception
     */
    public function save(array $data): ?Model
    {
        return $this->getModel()->create($data);
    }

    /**
     * 批量插入
     *
     * @param array $data
     *
     * @return bool
     */
    public function saveAll(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        try {
            // 对于 Pivot 表或没有复杂逻辑的表，使用批量插入更高效
            if ($this->getModel() instanceof \Illuminate\Database\Eloquent\Relations\Pivot || 
                (method_exists($this->getModel(), 'usesTimestamps') && !$this->getModel()->usesTimestamps())) {
                
                // 检查是否已存在（避免重复插入）
                $existing = $this->getExistingRecords($data);
                $newData = $this->filterExistingRecords($data, $existing);
                
                if (empty($newData)) {
                    // 所有记录都已存在
                    return true;
                }
                
                // 使用批量插入
                $result = $this->getModel()->insert($newData);
                
                if (!$result) {
                    throw new \Exception("批量插入失败，受影响的行数为 0");
                }
                
                return $result;
            }
            
            // 对于有复杂逻辑（如事件、观察者）的模型，使用逐个保存
            $models = $this->getModel()->newCollection();

            foreach ($data as $item) {
                $models->push($this->getModel()->newInstance($item));
            }

            $savedAll = true;
            $errors = [];
            
            $models->each(function ($model) use (&$savedAll, &$errors) {
                if (!$model->save()) {
                    $savedAll = false;
                    // 收集验证错误
                    if (method_exists($model, 'getErrors')) {
                        $errors[] = $model->getErrors();
                    }
                }
            });

            if (!$savedAll && !empty($errors)) {
                throw new \Exception("批量保存失败: " . json_encode($errors));
            }

            return $savedAll;
        } catch (Exception $e) {
            // 重新抛出异常，让调用者知道具体错误
            throw new \Exception("批量保存数据失败: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取已存在的记录（用于避免重复插入）
     *
     * @param array $data
     * @return array
     */
    protected function getExistingRecords(array $data): array
    {
        if (empty($data)) {
            return [];
        }
        
        // 假设数据有 member_id 和 tag_id 这样的复合唯一键
        // 可以根据实际情况重写此方法
        $query = $this->getModel()->query();
        
        foreach ($data as $item) {
            $query->orWhere(function($q) use ($item) {
                foreach ($item as $field => $value) {
                    $q->where($field, $value);
                }
            });
        }
        
        return $query->get()->toArray();
    }

    /**
     * 过滤掉已存在的记录
     *
     * @param array $data
     * @param array $existing
     * @return array
     */
    protected function filterExistingRecords(array $data, array $existing): array
    {
        if (empty($existing)) {
            return $data;
        }
        
        $existingMap = [];
        foreach ($existing as $record) {
            $key = '';
            foreach ($data[0] as $field => $value) {
                $key .= $record[$field] ?? '';
            }
            $existingMap[$key] = true;
        }
        
        $newData = [];
        foreach ($data as $item) {
            $key = '';
            foreach ($item as $field => $value) {
                $key .= $value;
            }
            
            if (!isset($existingMap[$key])) {
                $newData[] = $item;
            }
        }
        
        return $newData;
    }

    /**
     * 获取某字段内的值
     *
     * @param             $value
     * @param string      $field
     * @param string|null $valueKey
     * @param array|null  $where
     *
     * @return mixed
     * @throws Exception
     */
    public function getFieldValue($value, string $field, ?string $valueKey = null, ?array $where = []): mixed
    {
        // 如果提供了 $valueKey，则构建查询条件
        if ($valueKey) {
            $where[$valueKey] = $value; // 将 valueKey 和 value 加入条件
        } else {
            $where[$this->getPk()] = $value; // 默认使用主键作为条件
        }

        // 使用 Eloquent 查询构建器获取字段值
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }

        return $query->value($field);
    }

    /**
     * 根据搜索器获取内容
     *
     * @param array $where
     *
     * @return \Illuminate\Database\Query\Builder
     * @throws Exception
     */
    protected function withSearchSelect(array $where): mixed
    {
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));

        return $query;
    }

    /**
     * 获取关键词搜索字段（子类可重写）
     *
     * @return array
     */
    protected function getKeywordFields(): array
    {
        return [];
    }

    /**
     * 获取使用搜索器的字段列表（子类可重写）
     * 返回空数组表示自动检测所有字段
     *
     * @return array
     */
    protected function getScopeFields(): array
    {
        return [];
    }

    /**
     * 获取查询参数配置选项
     *
     * @param bool $search 是否启用搜索器
     * @return array
     */
    protected function getQueryOptions(bool $search = false): array
    {
        $options = [
            'keyword_fields' => $this->getKeywordFields(),
        ];
        
        // $search 为 false 时,禁用搜索器
        if (!$search) {
            $options['scopes'] = [];
        }
        
        return $options;
    }

    /**
     * 过滤数据表中不存在的字段
     *
     * @param array $where
     *
     * @return array
     * @throws Exception
     */
    protected function filterWhere(array $where = []): array
    {
        $fields = $this->getModel()->getFields(); // 获取模型的可填充字段
        foreach ($where as $key => $item) {
            // 检查键是否在可填充字段中
            if (!in_array($key, $fields)) {
                unset($where[$key]); // 过滤掉不存在的字段
            }
        }
        return $where; // 返回过滤后的条件
    }

    /**
     * 搜索
     *
     * @param array $where
     *
     * @return mixed
     * @throws Exception
     */
    public function search(array $where = []): mixed
    {
        if ($where) {
            return $this->withSearchSelect($where); // 返回查询构建器
        } else {
            return $this->getModel(); // 返回模型实例
        }
    }

    /**
     * 求和
     *
     * @param array  $where
     * @param string $field
     * @param bool   $search
     *
     * @return float
     * @throws Exception
     */
    public function sum(array $where, string $field, bool $search = false): float
    {
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions($search));
        }

        return (float)$query->sum($field);
    }

    /**
     * 高精度加法（修正精度问题）
     *
     * @param mixed       $key      主键值或条件值
     * @param string      $incField 要增加的字段
     * @param string      $inc      增加的值
     * @param string|null $keyField 条件字段名，默认为'id'
     * @param int         $acc      精度（小数位数）
     *
     * @return bool
     * @throws Exception
     */
    public function bcInc(mixed $key, string $incField, string $inc, ?string $keyField = null, int $acc = 2): bool
    {
        // 获取模型实例
        $model = $this->getModel();
        // 构建查询条件
        $query = $keyField ? $model->where($keyField, $key) : $model->where('id', $key);
        // 执行增量操作，使用合适的精度 DECIMAL(10, $acc)
        return $query->update([$incField => Db::raw("COALESCE($incField, 0) + CAST($inc AS DECIMAL(10, $acc))")]) > 0;
    }

    /**
     * 高精度 减法
     *
     * @param             $key
     * @param string      $decField
     * @param string      $dec
     * @param string|null $keyField
     * @param int         $acc
     *
     * @return bool
     * @throws ReflectionException
     */
    public function bcDec($key, string $decField, string $dec, ?string $keyField = null, int $acc = 2): bool
    {
        return $this->bc($key, $decField, $dec, $keyField, 2, $acc);
    }

    /**
     * 高精度计算并保存
     *
     * @param             $key
     * @param string      $field
     * @param string      $value
     * @param string|null $keyField
     * @param int         $type
     * @param int         $acc
     *
     * @return bool
     * @throws ReflectionException
     */
    public function bc($key, string $field, string $value, ?string $keyField = null, int $type = 1, int $acc = 2): bool
    {
        // 获取记录
        $result = $keyField === null ? $this->get($key) : $this->getOne([$keyField => $key]);
        if (!$result) return false;
        $newValue = 0;
        if ($type === 1) {
            // 加法
            $newValue = bcadd($result->{$field}, $value, $acc);
        } elseif ($type === 2) {
            // 减法
            if ($result->{$field} < $value) return false; // 检查是否足够减去
            $newValue = bcsub($result->{$field}, $value, $acc);
        }
        // 更新字段
        $result->{$field} = $newValue;
        // 保存更新
        return $result->save();
    }

    /**
     * 减库存加销量
     *
     * @param array  $where
     * @param int    $num
     * @param string $stock
     * @param string $sales
     *
     * @return bool
     * @throws Exception
     */
    public function decStockIncSales(array $where, int $num, string $stock = 'stock', string $sales = 'sales'): bool
    {
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }

        $product = $query->first();
        if ($product) {
            // 重新构建查询以执行更新操作
            $updateQuery = $this->getModel()->query();

            // 使用查询参数模块处理条件
            if (!empty($where)) {
                $updateQuery = $this->applyQueryParams($updateQuery, $where, $this->getQueryOptions(true));
            }

            return $updateQuery->decrement($stock, $num)->increment($sales, $num);
        }
        return false;
    }

    /**
     * 加库存减销量
     *
     * @param array  $where
     * @param int    $num
     * @param string $stock
     * @param string $sales
     *
     * @return bool
     * @throws Exception
     */
    public function incStockDecSales(array $where, int $num, string $stock = 'stock', string $sales = 'sales'): bool
    {
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }

        $product = $query->first();
        if ($product) {
            // 重新构建查询以执行更新操作
            $updateQuery1 = $this->getModel()->query();
            $updateQuery2 = $this->getModel()->query();

            // 使用查询参数模块处理条件
            if (!empty($where)) {
                $updateQuery1 = $this->applyQueryParams($updateQuery1, $where, $this->getQueryOptions(true));
                $updateQuery2 = $this->applyQueryParams($updateQuery2, $where, $this->getQueryOptions(true));
            }

            $updateQuery1->increment($stock, $num);
            $updateQuery2->decrement($sales, $num);
            return true;
        }
        return false;
    }

    /**
     * 获取条件数据中的某个值的最大值
     *
     * @param array  $where
     * @param string $field
     *
     * @return mixed
     * @throws Exception
     */
    public function getMax(array $where = [], string $field = ''): mixed
    {
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }

        return $query->max($field);
    }

    /**
     * 获取条件数据中的某个值的最小值
     *
     * @param array  $where
     * @param string $field
     *
     * @return mixed
     * @throws Exception
     */
    public function getMin(array $where = [], string $field = ''): mixed
    {
        $query = $this->getModel()->query();

        // 使用查询参数模块处理条件
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }

        return $query->min($field);
    }

    private function studly(string $string): string
    {
        // 用下划线分隔单词
        $words = preg_split('/[\s_]+/', $string);
        // 将每个单词的首字母大写并连接
        $studlyCase = array_map('ucfirst', $words);
        // 返回连接后的结果
        return implode('', $studlyCase);
    }

    /**
     * 执行作用域移除操作
     *
     * @param            $query
     * @param array|null $scopes
     */
    protected function applyScopeRemoval($query, ?array $scopes): void
    {
        if (empty($scopes)) return;
        foreach ($scopes as $scope) {
            // 全局作用域移除
            if (is_string($scope) && class_exists($scope)) {
                $query->withoutGlobalScope($scope);
            } // 本地作用域移除（@method withoutNamedScope 在某些版本不可用）
            elseif (is_string($scope) && method_exists($query, 'withoutNamedScope')) {
                $query->withoutNamedScope($scope);
            } // 闭包动态移除
            elseif ($scope instanceof \Closure) {
                $scope($query);
            }
        }
    }

    /**
     * 是否in条件
     *
     * @param array $condition
     *
     * @return bool
     */
    protected function isInCondition(array $condition): bool
    {
        return count($condition) === 3
            && in_array(strtolower($condition[1] ?? ''), ['in', 'not in'], true);
    }

    /**
     * 检测表是否存在
     *
     * @param $table
     *
     * @return bool
     */
    public function tableExists($table): bool
    {
        return Db::schema()->hasTable($table);
    }
}

