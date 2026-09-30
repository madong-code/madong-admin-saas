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

namespace app\adminapi\controller;

use core\io\excel\ExcelExportService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use Illuminate\Database\Eloquent\Model;
use madong\helper\Arr;
use madong\helper\DateTime;
use madong\helper\Tree;
use madong\query\QueryBuilderHelper;
use madong\query\QuerySelectConfig;
use madong\query\QuerySelectHelper;
use support\Request;

/**
 * @author Mr.April
 * @since  1.0
 */
class Crud extends Base
{
    /**
     * SelectInput 配置
     * @var array|null
     */
    protected ?array $selectInputConfig = null;

    /**
     * 列表
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $methods         = [
                'select'     => 'formatSelect',
                'tree'       => 'formatTree',
                'table_tree' => 'formatTableTree',
                'normal'     => 'formatNormal',
            ];
            $format_function = $methods[$format] ?? 'formatNormal';
            $total           = $this->service->getCount($where);
            $list            = $this->service->selectList($where, $field, $page, $limit, $order, [], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function select(Request $request): \support\Response
    {

        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);

            $data = $this->service->selectList($where, $field, 0, 99999, $order, [], true);
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }

    }

    /**
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function create(Request $request): \support\Response
    {
        try {
            $id = $request->get('id');
            throw new AdminException('表单不存在');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 插入数据
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('store')->check($data)) {

                    throw new \Exception($this->validate->getError());
                }
            }
            $model = $this->service->save($data);
            if (empty($model)) {
                throw new AdminException('插入失败');
            }
            $pk = $model->getPk();
            return Json::success('ok', [$pk => $model->getAttribute($pk)]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }

    }

    /**
     * 查询详情
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function show(Request $request): \support\Response
    {
        try {
            $id   = $this->normalizeId($request->route->param('id'));
            $data = $this->service->get($id);
            if (empty($data)) {
                throw new AdminException('数据未找到', 400);
            }
            return Json::success('ok', $data->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    public function edit(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            throw new AdminException('表单不存在');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 更新
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function update(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('update')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            // 路由模式兼容：路由中无 id 时取请求体主键
            $id = $this->resolveId($request, $data);

            $result = $this->service->update($id, $data);
            if ($result === false) {
                throw new AdminException('更新失败');
            }
            // affected rows 为 0 时区分「记录不存在」与「内容未变化」，避免数据没变却提示成功
            if ((int)$result === 0 && empty($this->service->get($id))) {
                throw new AdminException('数据不存在或已被删除');
            }
            return Json::success('ok', []);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 更新状态
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function changeStatus(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);
            // 路由 id 与请求体主键双兼容，并统一做合法性校验与类型转换
            $id   = $this->resolveId($request, $data);
            $model = $this->service->getModel();
            // 响应式请求体可能同时携带 id，避免其覆盖已归一化的主键
            $data[$model->getKeyName()] = $id;

            $targetModel = $model->findOrFail($id);
            if (empty($targetModel)) {
                throw new \Exception('资源不存在:' . $id);
            }
            $targetModel->fill($data);
            if (!$targetModel->save()) {
                throw new \RuntimeException('数据保存失败');
            }
            return Json::success('ok', []);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * Excel导出
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function export(Request $request): \support\Response
    {
        try {
            $params = $request->all();
            $args   = (array)$params['query'] ?? [];
            $result = ExcelExportService::export($params, function ($chunkHandler) use ($args) {
                $model        = $this->service->getModel();
                $allow_column = $model->getFields();
                $query        = $model->query();
                $where        = $this->convertToStandardFormat($args, $allow_column);
                if (!empty($where)) {
                    $whereInConditions = [];
                    foreach ($where as $key => $condition) {
                        if (is_array($condition) && count($condition) === 3 && ($condition[1] === 'in' || $condition[1] === 'IN')) {
                            $whereInConditions[] = $condition;
                            unset($where[$key]);//移除分离后的条件
                        }
                    }
                    //普通条件格式直接传入构建
                    if (!empty($where)) {
                        $query->where($where);
                    }

                    //特殊条件格式额外处理
                    foreach ($whereInConditions as $condition) {
                        if (empty($condition[2])) {
                            continue;
                        }
                        $value = Arr::normalize($condition[2]);
                        $query->whereIn($condition[0], $value);
                    }
                }
                //切片查询数据
                $query->chunk(1000, $chunkHandler);
            });
            return Json::success('导出成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 删除资源
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function destroy(Request $request): \support\Response
    {
        try {
            // 获取要删除的ID集合
            $data = $this->getDeleteIds($request);

            if (empty($data)) {
                throw new AdminException('删除参数不能为空');
            }
            $result = $this->service->transaction(function () use ($data) {
                $data       = is_array($data) ? $data : explode(',', $data);
                $deletedIds = [];
                foreach ($data as $id) {
                    $item = $this->service->get($id);
                    if (!$item) {
                        continue; // 如果找不到项，跳过
                    }
                    $item->delete();
                    $primaryKey   = $item->getPk();
                    $deletedIds[] = $item->{$primaryKey};
                }
                return $deletedIds;
            });
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 获取要删除的ID集合
     * 支持多种参数来源：
     * 1. 路由参数中的 'id'
     * 2. 请求体中的 'ids' 数组
     * 3. 请求体中的 'data' 数组（兼容旧版）
     *
     * @param Request $request
     *
     * @return array
     */
    protected function getDeleteIds(Request $request): array
    {
        // 优先从路由参数获取单个ID
        $routeId = $request->route->param('id');

        if (!empty($routeId) && $routeId !== '0') {
            return [$this->normalizeId($routeId)];
        }

        // 尝试从请求体中获取 'ids' 参数
        $ids = $request->input('ids', []);

        // 如果 'ids' 不存在，尝试兼容旧版的 'data' 参数
        if (empty($ids)) {
            $ids = $request->input('data', []);
        }

        // 无有效参数
        if (empty($ids) || (!is_array($ids) && !is_string($ids))) {
            return [];
        }

        return $this->normalizeIds($ids);
    }

    /**
     * 解析主键：路由参数优先，缺失时回退到请求体主键
     *
     * @param Request $request
     * @param array   $data 已过滤的请求数据
     *
     * @return string|int
     */
    protected function resolveId(Request $request, array $data = []): string|int
    {
        $id = $request->route->param('id');

        if (empty($id) || $id === '0') {
            $model      = $this->service->getModel();
            $primaryKey = $model->getKeyName();
            if (!array_key_exists($primaryKey, $data)) {
                throw new \Exception('参数异常缺少参数:' . $primaryKey);
            }
            $id = $data[$primaryKey];
        }

        return $this->normalizeId($id);
    }

    /**
     * 主键归一化：校验取值合法，并按模型主键类型转换
     *
     * - 拒绝数组/对象，避免非法条件被当作关联数组传入 DAO（BaseDao::update 支持 array 条件）
     * - 去除首尾空白并限制字符集，防止拼接出异常查询条件
     * - 自增整型主键统一转为 int，避免严格模式下 bigint 与字符串比较匹配失败
     *
     * @param mixed $id
     *
     * @return string|int
     */
    protected function normalizeId(mixed $id): string|int
    {
        if (is_array($id) || is_object($id)) {
            throw new AdminException('参数 id 非法');
        }

        $id = trim((string)$id);
        if ($id === '') {
            throw new AdminException('参数 id 不能为空');
        }

        // 允许雪花ID/自增ID（数字）与 UUID（字母、数字、中划线、下划线）
        if (!preg_match('/^[\w-]+$/', $id)) {
            throw new AdminException('参数 id 非法');
        }

        $model = $this->service->getModel();
        if ($model->getKeyType() === 'int' && is_numeric($id)) {
            return (int)$id;
        }

        return $id;
    }

    /**
     * 批量主键归一化（自动忽略空值，去重后返回）
     *
     * @param array|string $ids
     *
     * @return array
     */
    protected function normalizeIds(array|string $ids): array
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        $result = [];
        foreach ($ids as $id) {
            if (is_array($id) || is_object($id)) {
                continue;
            }
            $id = trim((string)$id);
            if ($id === '') {
                continue;
            }
            $normalized = $this->normalizeId($id);
            if (!in_array($normalized, $result, true)) {
                $result[] = $normalized;
            }
        }

        return $result;
    }

    /**
     * 数据恢复
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function recovery(Request $request): \support\Response
    {
        try {
            // 主键来源与删除保持一致，并统一做合法性校验与类型转换
            $id   = $request->route->param('id');
            $raw  = $request->input('data', []);
            $data = !empty($id) && $id !== '0'
                ? [$this->normalizeId($id)]
                : (is_array($raw) || is_string($raw) ? $this->normalizeIds($raw) : []);
            if (empty($data)) {
                throw new AdminException('参数错误');
            }
            $this->service->transaction(function () use ($data) {
                $data = is_array($data) ? $data : explode(',', $data);
                foreach ($data as $id) {
                    $this->service->update($id, ['delete' => null]);
                }
            });
            return Json::success('操作成功', []);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 将各种格式的查询条件转换为统一格式
     * 支持大写前缀、三元素数组、普通字段等格式
     * 自动过滤空值参数（null、空字符串、空数组）
     *
     * @param array $params
     * @param array $allowColumns
     * @return array
     */
    private function convertToStandardFormat(array $params, array $allowColumns): array
    {
        $result = [];
        $hasTripleArray = false;

        // 检查是否包含三元素数组格式
        foreach ($params as $key => $value) {
            if (is_numeric($key) && is_array($value) && count($value) === 3) {
                $hasTripleArray = true;
                break;
            }
        }

        // 如果有三元素数组格式,直接保留原格式
        if ($hasTripleArray) {
            foreach ($params as $key => $value) {
                // 跳过非查询参数
                if (in_array($key, ['field', 'order', 'orderBy', 'orderDirection', 'sort', 'format', 'limit', 'page'])) {
                    continue;
                }

                // 三元素数组直接添加（跳过空值条件）
                if (is_numeric($key) && is_array($value) && count($value) === 3) {
                    if ($this->isValidCondition($value)) {
                        $result[] = $value;
                    }
                    continue;
                }

                // 处理大写前缀格式: OPERATOR_field
                if (preg_match('/^([A-Z_]+)_(.+)$/', $key, $matches)) {
                    $operatorUpper = $matches[1];
                    $field = $matches[2];

                    // 字段白名单验证
                    if (in_array($field, $allowColumns, true)) {
                        // 检查值是否为空
                        if (!$this->isValidValue($value, $operatorUpper)) {
                            continue;
                        }

                        // 转换操作符
                        $operatorMap = [
                            'IN' => 'IN',
                            'LIKE' => 'LIKE',
                            'GT' => '>',
                            'LT' => '<',
                            'GTE' => '>=',
                            'LTE' => '<=',
                            'NE' => '!=',
                            'BETWEEN' => 'BETWEEN',
                            'EQ' => '=',
                        ];
                        $operator = $operatorMap[$operatorUpper] ?? '=';

                        // 特殊处理 LIKE 操作符自动加 %
                        if ($operatorUpper === 'LIKE') {
                            $result[] = [$field, $operator, "%{$value}%"];
                        } elseif ($operatorUpper === 'IN' && is_array($value)) {
                            $result[] = [$field, 'IN', $value];
                        } elseif ($operatorUpper === 'BETWEEN' && is_array($value) && count($value) === 2) {
                            $start = DateTime::dateTimeStringToTimestamp($value[0]);
                            $end = DateTime::dateTimeStringToTimestamp($value[1]);
                            $result[] = [$field, '>', $start];
                            $result[] = [$field, '<', $end];
                        } else {
                            $result[] = [$field, $operator, $value];
                        }
                    }
                    continue;
                }

                // 处理普通字段 (默认使用 =)
                if (in_array($key, $allowColumns, true)) {
                    // 检查值是否为空
                    if (!$this->isValidValue($value)) {
                        continue;
                    }
                    $result[] = [$key, '=', $value];
                }
            }
        } else {
            // 没有三元素数组,转换为标准格式
            $result['filters'] = [];
            foreach ($params as $key => $value) {
                // 跳过非查询参数
                if (in_array($key, ['field', 'order', 'orderBy', 'orderDirection', 'sort', 'format', 'limit', 'page'])) {
                    continue;
                }

                // 处理大写前缀格式: OPERATOR_field
                if (preg_match('/^([A-Z_]+)_(.+)$/', $key, $matches)) {
                    $operatorUpper = $matches[1];
                    $field = $matches[2];

                    // 字段白名单验证
                    if (in_array($field, $allowColumns, true)) {
                        // 检查值是否为空
                        if (!$this->isValidValue($value, $operatorUpper)) {
                            continue;
                        }

                        // 转换为小写操作符
                        $operatorMap = [
                            'IN' => 'in',
                            'LIKE' => 'like',
                            'GT' => 'gt',
                            'LT' => 'lt',
                            'GTE' => 'gte',
                            'LTE' => 'lte',
                            'NE' => 'ne',
                            'BETWEEN' => 'between',
                            'EQ' => 'eq',
                        ];
                        $operator = $operatorMap[$operatorUpper] ?? 'eq';

                        // 特殊处理 LIKE 操作符自动加 %
                        if ($operatorUpper === 'LIKE') {
                            $result['filters'][$field] = "like:%{$value}%";
                        } elseif ($operatorUpper === 'IN' && is_array($value)) {
                            $result['filters'][$field] = "in:" . json_encode($value);
                        } else {
                            $result['filters'][$field] = "{$operator}:{$value}";
                        }
                    }
                    continue;
                }

                // 处理普通字段 (默认使用 eq)
                if (in_array($key, $allowColumns, true)) {
                    // 检查值是否为空
                    if (!$this->isValidValue($value)) {
                        continue;
                    }
                    $result['filters'][$key] = "eq:{$value}";
                }
            }
        }

        return $result;
    }

    /**
     * 检查值是否有效（非空）
     *
     * @param mixed $value
     * @param string $operator 操作符（可选）
     * @return bool
     */
    private function isValidValue(mixed $value, string $operator = ''): bool
    {
        // null 值无效
        if ($value === null) {
            return false;
        }

        // 空字符串无效
        if ($value === '') {
            return false;
        }

        // 空数组无效
        if (is_array($value) && empty($value)) {
            return false;
        }

        // 对于 BETWEEN 操作，需要检查数组元素
        if ($operator === 'BETWEEN' && is_array($value) && count($value) === 2) {
            return $value[0] !== '' && $value[0] !== null && $value[1] !== '' && $value[1] !== null;
        }

        return true;
    }

    /**
     * 检查三元素数组条件是否有效
     *
     * @param array $condition [字段, 操作符, 值]
     * @return bool
     */
    private function isValidCondition(array $condition): bool
    {
        if (count($condition) !== 3) {
            return false;
        }

        // 检查值是否有效
        return $this->isValidValue($condition[2]);
    }

    /**
     * 解析排序参数为 SQL order 字符串 - 支持多种场景
     *
     * 场景1: order=id 或 order=id desc (单字段排序)
     * 场景2: sort=id:asc,create_time:desc 或 sort[]=id:asc&sort[]=create_time:desc (多字段排序)
     * 场景3: orderBy=id&orderDirection=desc (兼容旧版大写前缀)
     *
     * @param array $params
     * @param array $allowColumns
     * @return string SQL order 字符串,如 "id desc, create_time asc"
     */
    private function parseSortToOrder(array $params, array $allowColumns): string
    {
        $orders = [];

        // 场景1: order=id 或 order=id desc
        if (isset($params['order']) && !isset($params['sort']) && !isset($params['orderBy'])) {
            $orderValue = $params['order'];
            $parts = explode(' ', $orderValue, 2);
            $field = $parts[0];
            $direction = strtolower($parts[1] ?? 'desc');

            if (in_array($field, $allowColumns)) {
                $orders[] = "$field $direction";
            }
        }

        // 场景2: sort=id:asc,create_time:desc 或 sort[]=id:asc&sort[]=create_time:desc
        if (isset($params['sort'])) {
            $sortValues = is_array($params['sort']) ? $params['sort'] : explode(',', $params['sort']);
            foreach ($sortValues as $sortValue) {
                $parts = explode(':', trim($sortValue), 2);
                $field = $parts[0];
                $direction = strtolower($parts[1] ?? 'desc');

                if (in_array($field, $allowColumns)) {
                    $orders[] = "$field $direction";
                }
            }
        }

        // 场景3: orderBy=id&orderDirection=desc (兼容旧版)
        if (isset($params['orderBy']) && !isset($params['sort']) && empty($orders)) {
            $field = $params['orderBy'];
            $direction = strtolower($params['orderDirection'] ?? 'desc');

            if (in_array($field, $allowColumns)) {
                $orders[] = "$field $direction";
            }
        }

        return implode(', ', $orders);
    }

    /**
     * 处理查询输入参数
     *
     * @param Request $request
     * @param array|null $config 临时配置（可选），会覆盖类属性配置
     * @return array [where, format, limit, field, order, page]
     */
    protected function selectInput(Request $request, ?array $config = null): array
    {
        $model = $this->service->getModel();
        $baseConfig = $this->getSelectInputConfig($request);
        
        // 扩展 allowed_fields 并处理配置
        $helperConfig = $this->prepareHelperConfig($config, $baseConfig, $model);
        
        // 处理查询参数
        $result = $this->processQueryParams($request, $model, $helperConfig);
        
        // 处理 custom_fields
        $where = $this->processCustomFields(
            $result['where'],
            $result,
            $request,
            $config,
            $baseConfig
        );

        // 兼容旧格式返回值
        return [
            $where,
            $result['format'] ?? $request->input('format', 'normal'),
            $result['limit'],
            $result['field'] ?? $this->validateField($request->input('field', '*'), $model->getFields()),
            $result['order'],
            $result['page'],
        ];
    }

    /**
     * 准备 QuerySelectHelper 配置
     */
    private function prepareHelperConfig(?array $config, array $baseConfig, Model $model): QuerySelectConfig
    {
        $allowColumn = $model->getFields();
        
        // 扩展 allowed_fields：自动包含所有数据库字段
        if (isset($baseConfig['allowed_fields']) && is_array($baseConfig['allowed_fields'])) {
            $baseConfig['allowed_fields'] = array_unique(array_merge($allowColumn, $baseConfig['allowed_fields']));
        }
        
        if ($config !== null) {
            if (isset($config['allowed_fields']) && is_array($config['allowed_fields'])) {
                $config['allowed_fields'] = array_unique(array_merge($allowColumn, $config['allowed_fields']));
            }
            return QuerySelectConfig::make(array_merge($baseConfig, $config));
        }
        
        return QuerySelectConfig::make($baseConfig);
    }

    /**
     * 处理查询参数
     */
    private function processQueryParams(Request $request, Model $model, QuerySelectConfig $helperConfig): array
    {
        $helper = new QuerySelectHelper($helperConfig, $model->getFields());
        $result = $helper->process($request->all(), $model);
        
        // 提取 custom_fields（它们会被合并到 where 中）
        $customFields = array_filter($result, function ($key) {
            return !in_array($key, ['where', 'format', 'limit', 'field', 'order', 'page']);
        }, ARRAY_FILTER_USE_KEY);
        
        return array_merge($result, ['custom_fields' => $customFields]);
    }

    /**
     * 处理 custom_fields
     */
    private function processCustomFields(array $where, array $result, Request $request, ?array $config, array $baseConfig): array
    {
        $customFields = $result['custom_fields'] ?? [];
        
        $filterFormat = $this->getFilterFormat($config, $baseConfig);
        $defaultOperator = $this->getDefaultOperator($config, $baseConfig);
        $excludeFilters = $this->getExcludeFilters($config, $baseConfig);
        
        $hasTripleArray = $this->hasTripleArray($where);
        $hasFiltersKey = isset($where['filters']) && is_array($where['filters']);
        
        if ($excludeFilters) {
            return $this->processCustomFieldsWithoutFilters($where, $customFields, $request, $filterFormat, $defaultOperator, $hasFiltersKey);
        }
        
        return $this->processCustomFieldsWithFilters($where, $customFields, $request, $filterFormat, $defaultOperator, $hasFiltersKey, $hasTripleArray);
    }

    /**
     * 获取过滤器格式
     */
    private function getFilterFormat(?array $config, array $baseConfig): string
    {
        if ($config !== null && isset($config['filter_format'])) {
            return $config['filter_format'];
        }
        return $baseConfig['filter_format'] ?? 'array';
    }

    /**
     * 获取默认操作符
     */
    private function getDefaultOperator(?array $config, array $baseConfig): string
    {
        if ($config !== null && isset($config['default_operator'])) {
            return $config['default_operator'];
        }
        return $baseConfig['default_operator'] ?? 'eq';
    }

    /**
     * 获取 exclude_filters 配置
     */
    private function getExcludeFilters(?array $config, array $baseConfig): bool
    {
        if ($config !== null && isset($config['exclude_filters'])) {
            return $config['exclude_filters'];
        }
        return $baseConfig['exclude_filters'] ?? false;
    }

    /**
     * 检查是否有三元素数组格式
     */
    private function hasTripleArray(array $where): bool
    {
        foreach ($where as $key => $value) {
            if (is_numeric($key) && is_array($value) && count($value) === 3) {
                return true;
            }
        }
        return false;
    }

    /**
     * 处理 custom_fields（exclude_filters = true）
     */
    private function processCustomFieldsWithoutFilters(array $where, array $customFields, Request $request, string $filterFormat, string $defaultOperator, bool $hasFiltersKey): array
    {
        if ($hasFiltersKey) {
            unset($where['filters']);
        }
        
        foreach ($customFields as $key => $value) {
            if (is_numeric($key)) {
                $fieldName = $value;
                $fieldValue = $request->input($fieldName);
                if ($fieldValue !== null && $fieldValue !== '') {
                    $this->addCustomFieldToWhere($where, $fieldName, $fieldValue, $filterFormat, $defaultOperator);
                }
            } else {
                if ($value !== null && $value !== '') {
                    $this->addCustomFieldToWhere($where, $key, $value, $filterFormat, $defaultOperator);
                }
            }
        }
        
        return $where;
    }

    /**
     * 处理 custom_fields（exclude_filters = false）
     */
    private function processCustomFieldsWithFilters(array $where, array $customFields, Request $request, string $filterFormat, string $defaultOperator, bool $hasFiltersKey, bool $hasTripleArray): array
    {
        if (empty($customFields) && !$hasFiltersKey) {
            if (!isset($where['filters']) && !$hasTripleArray) {
                $where['filters'] = [];
            }
            return $where;
        }
        
        if (!isset($where['filters']) || !is_array($where['filters'])) {
            $where['filters'] = [];
        }
        
        $normalizedFilters = $this->normalizeFilters($where['filters']);
        
        foreach ($customFields as $key => $value) {
            if (is_numeric($key)) {
                $fieldName = $value;
                $fieldValue = $request->input($fieldName);
                if ($fieldValue !== null && $fieldValue !== '') {
                    $parsed = $this->parseFieldPrefix($fieldName, $defaultOperator);
                    $normalizedFilters[] = [$parsed['field'], $parsed['operator'], $fieldValue];
                }
            } else {
                if ($value !== null && $value !== '') {
                    $parsed = $this->parseFieldPrefix($key, $defaultOperator);
                    $normalizedFilters[] = [$parsed['field'], $parsed['operator'], $value];
                }
            }
        }
        
        if ($filterFormat === 'keyvalue') {
            $finalFilters = [];
            foreach ($normalizedFilters as $filter) {
                $finalFilters[$filter[0]] = $filter[1] . ':' . $filter[2];
            }
            $where['filters'] = $finalFilters;
        } else {
            $where['filters'] = $normalizedFilters;
        }
        
        return $where;
    }

    /**
     * 添加 custom_field 到 where
     */
    private function addCustomFieldToWhere(array &$where, string $field, $value, string $filterFormat, string $defaultOperator): void
    {
        // 解析字段前缀（如 LIKE_user_name）
        $parsed = $this->parseFieldPrefix($field, $defaultOperator);
        $actualField = $parsed['field'];
        $operator = $parsed['operator'];
        
        switch ($filterFormat) {
            case 'simple':
                $where[$actualField] = $value;
                break;
            case 'keyvalue':
                $where[$actualField] = $operator . ':' . $value;
                break;
            default:
                $where[] = [$actualField, $operator, $value];
                break;
        }
    }
    
    /**
     * 解析字段前缀，支持 LIKE_, EQ_, GT_, LT_, GTE_, LTE_, NEQ_ 等前缀
     */
    private function parseFieldPrefix(string $field, string $defaultOperator): array
    {
        $prefixes = [
            'LIKE_' => 'like',
            'EQ_' => 'eq',
            'GT_' => 'gt',
            'LT_' => 'lt',
            'GTE_' => 'gte',
            'LTE_' => 'lte',
            'NEQ_' => 'neq',
            'IN_' => 'in',
            'NOTIN_' => 'notin',
            'BETWEEN_' => 'between',
            'NOTBETWEEN_' => 'notbetween',
            'NULL_' => 'null',
            'NOTNULL_' => 'notnull',
        ];
        
        foreach ($prefixes as $prefix => $operator) {
            if (strpos($field, $prefix) === 0) {
                return [
                    'field' => substr($field, strlen($prefix)),
                    'operator' => $operator,
                ];
            }
        }
        
        return [
            'field' => $field,
            'operator' => $defaultOperator,
        ];
    }

    /**
     * 标准化 filters
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];
        foreach ($filters as $key => $filter) {
            if (is_array($filter) && count($filter) === 3) {
                $normalized[] = $filter;
            } elseif (is_string($key) && is_string($filter)) {
                if (strpos($filter, ':') !== false) {
                    [$op, $val] = explode(':', $filter, 2);
                    $normalized[] = [$key, $op, $val];
                } else {
                    $normalized[] = [$key, 'eq', $filter];
                }
            }
        }
        return $normalized;
    }

    /**
     * 将 where 条件应用到查询构建器
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array                                 $where
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyWhereToQuery(\Illuminate\Database\Eloquent\Builder $query, array $where): \Illuminate\Database\Eloquent\Builder
    {
        // 处理 filters 格式
        if (isset($where['filters']) && is_array($where['filters'])) {
            QueryBuilderHelper::applyFiltersToQuery($query, $where['filters']);
        }
        
        // 处理三元素数组格式（直接应用到查询）
        foreach ($where as $key => $value) {
            if (is_numeric($key) && is_array($value) && count($value) === 3) {
                $field = $value[0];
                $operator = $value[1];
                $val = $value[2];
                $query->where($field, $operator, $val);
            }
        }
        
        // 处理普通字段键值对格式（exclude_filters = true 时的 custom_fields）
        foreach ($where as $key => $value) {
            // 跳过系统保留键
            if (in_array($key, ['filters', 'format', 'limit', 'field', 'order', 'page'])) {
                continue;
            }
            
            // 处理普通字段（字符串键且非数组）
            if (is_string($key) && !is_array($value)) {
                // 检查是否是 'op:value' 格式
                if (strpos($value, ':') !== false) {
                    [$op, $val] = explode(':', $value, 2);
                    $query->where($key, $op, $val);
                } else {
                    $query->where($key, '=', $value);
                }
            }
        }

        return $query;
    }





    /**
     * 获取 SelectInput 配置
     * 保持原有行为，默认不启用新特性
     *
     * @param Request $request
     * @return array
     */
    protected function getSelectInputConfig(Request $request): array
    {
        $format = $request->input('format', 'normal');
        $defaultConfig = $this->selectInputConfig ?? [];

        // 如果子类没有配置自定义选项，使用完全兼容的默认配置
        if (empty($defaultConfig)) {
            return [
                'return_format' => 'array',
                'param_format' => 'auto',  // 自动检测参数格式，兼容现有所有格式
                'skip_fields' => [],  // 默认不跳过任何字段
                'allowed_fields' => null,  // 默认不限制字段
                'sort_fields' => null,  // 默认不限制排序字段
                'default_sort' => [],  // 默认不设置默认排序
                'default_page_size' => $format === 'tree' ? 10000 : 10,
                'max_page_size' => 10000,
                'default_page' => 1,
                'keyword_fields' => [],  // 默认不启用关键词搜索
                'enable_keyword_search' => false,
                'enable_range_query' => true,  // 启用范围查询
                'enable_like_query' => true,  // 启用模糊匹配
                'auto_convert_datetime' => true,
                'skip_empty_values' => true,
                'custom_fields' => [],  // 默认无自定义字段
                'filter_format' => 'keyvalue',  // 过滤器格式：array、keyvalue、simple
                'default_operator' => 'eq',  // 默认操作符
                'exclude_filters' => false,  // 是否排除 filters 键，默认为 false（包含 filters）
            ];
        }

        // 子类配置了自定义选项，合并默认值
        return array_merge([
            'return_format' => 'array',
            'param_format' => 'auto',
            'skip_fields' => ['password', 'token', 'deleted_at'],
            'allowed_fields' => null,
            'sort_fields' => null,
            'default_sort' => [],
            'default_page_size' => $format === 'tree' ? 10000 : 10,
            'max_page_size' => 10000,
            'default_page' => 1,
            'keyword_fields' => [],
            'enable_keyword_search' => false,
            'enable_range_query' => true,
            'enable_like_query' => true,
            'auto_convert_datetime' => true,
            'skip_empty_values' => true,
            'custom_fields' => [],  // 默认无自定义字段
            'filter_format' => 'keyvalue',  // 过滤器格式：array（[[字段, 操作符, 值]]）、keyvalue（[字段 => 'op:value']）、simple（[字段 => 值]）
            'default_operator' => 'eq',  // 默认操作符
            'exclude_filters' => false,  // 是否排除 filters 键，默认为 false（包含 filters）
        ], $defaultConfig);
    }

    /**
     * 验证字段是否有效
     *
     * @param string $field
     * @param array $allow_column
     * @return string
     */
    protected function validateField(string $field, array $allow_column): string
    {
        if ($field === '*') {
            return '*';
        }

        // 处理多个字段（逗号分隔）
        $fields = array_map('trim', explode(',', $field));
        $validFields = [];

        foreach ($fields as $f) {
            if (in_array($f, $allow_column)) {
                $validFields[] = $f;
            }
        }


        return empty($validFields) ? '*' : implode(', ', $validFields);
    }

    /**
     * 插入前置方法
     *
     * @param Request $request
     *
     * @return array
     */
    protected function insertInput(Request $request): array
    {
        $data           = $this->inputFilter($request->all());
        $password_filed = 'password';
        if (isset($data[$password_filed])) {
            $data[$password_filed] = password_hash($data[$password_filed], PASSWORD_DEFAULT);
        }
        return $data;
    }

    /**
     * 对用户输入表单过滤
     *
     * @param array $data
     * @param array $skipKeys
     *
     * @return array
     */
    protected function inputFilter(array $data, array $skipKeys = []): array
    {
        $model   = $this->service->getModel();
        $columns = $model->getFields();
        foreach ($data as $col => $item) {
            // 检查是否在跳过的键中，或者不在列中
            if (!in_array($col, $columns) && !in_array($col, $skipKeys)) {
                unset($data[$col]);
                continue;
            }
        }

        // 处理时间字段
        if (empty($data['created_at']) && !in_array('created_at', $skipKeys)) {
            unset($data['created_at']);
        }
        if (empty($data['updated_at']) && !in_array('updated_at', $skipKeys)) {
            unset($data['updated_at']);
        }
        return $data;
    }

    /**
     * 格式化树
     *
     * @param $items
     *
     * @return \support\Response
     */
    protected function formatTree($items): \support\Response
    {
        $format_items = [];
        foreach ($items as $item) {
            $format_items[] = [
                'name'  => $item->title ?? $item->name ?? $item->id,
                'value' => (string)$item->id,
                'id'    => $item->id,
                'pid'   => $item->pid,
            ];
        }
        $tree = new Tree($format_items);
        return Json::success('ok', $tree->getTree());
    }

    /**
     * 格式化表格树
     *
     * @param $data
     * @param $total
     *
     * @return \support\Response
     */
    protected function formatTableTree($data, $total): \support\Response
    {
        $tree  = new Tree($data->toArray());
        $items = $tree->getTree();
        return Json::success('ok', $items);
    }

    /**
     * 格式化下拉列表
     *
     * @param $items
     *
     * @return \support\Response
     */
    protected function formatSelect($items): \support\Response
    {
        $formatted_items = [];
        foreach ($items as $item) {
            $formatted_items[] = [
                'label' => $item->title ?? $item->name ?? $item->real_name ?? $item->id,
                'value' => $item->id,
            ];
        }
        return Json::success('ok', $formatted_items);
    }

    /**
     * 通用格式化
     *
     * @param $items
     * @param $total
     *
     * @return \support\Response
     */
    protected function formatNormal($items, $total): \support\Response
    {
        return Json::success('ok', compact('items', 'total'));
    }

    public function dev(Request $request): \support\Response
    {
        return Json::fail('接口开发中');
    }

}