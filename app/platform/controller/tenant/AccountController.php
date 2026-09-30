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
namespace app\platform\controller\tenant;

use app\platform\controller\Base;
use app\model\system\admin\Admin;
use app\service\platform\tenant\TenantService;
use core\foundation\exception\handler\TenantException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\Request;

//#[Middleware(\app\platform\middleware\TenantMiddleware::class)]
final class AccountController extends Base
{
    public function __construct(TenantService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/tenant/account',
        summary: '租户列表',
        tags: ['租户管理'])
    ]
    #[PageResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $format_function = ['select' => 'formatSelect', 'tree' => 'formatTree', 'table_tree' => 'formatTableTree', 'normal' => 'formatNormal'][$format] ?? 'formatNormal';
            $total           = $this->service->getCount($where);
            $list            = $this->service->selectList($where, $field, $page, $limit, $order, [], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/tenant/account',
        summary: '创建租户',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);

            // 手动创建验证器检查 tenant code 唯一性
            $validate = new \app\platform\validate\tenant\TenantValidate();
            if (!$validate->scene('store')->check($data)) {
                throw new \Exception($validate->getError());
            }

            // 提取管理员参数（会被 inputFilter 过滤掉，因为不是 saas_tenant 表字段）
            $allInput = $request->all();
            $account  = $allInput['account'] ?? '';
            $password = $allInput['password'] ?? '';
            $dbMode   = $data['database_mode'] ?? 'field';

            $model = $this->service->save($data);
            if (empty($model)) throw new TenantException('创建租户失败');

            // 创建管理员（字段模式/库隔离模式均需要）
            $tenantId = (int)$model->getAttribute($model->getPk());
            if (!empty($account)) {
                $this->service->createAdminAndLink($tenantId, $account, $password, $dbMode);
            }

            return Json::success('创建成功', [$model->getPk() => $tenantId]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/tenant/account/{id}',
        summary: '租户详情',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        try {
            $id     = $request->route->param('id');
            $result = $this->service->get($id, null, ['subscriptions']);
            if (empty($result)) throw new TenantException('租户不存在');
            $data               = $result->toArray();
            $data['plan_ids']   = $result->getSubscriptionIds();
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: 400);
        }
    }

    #[OA\Put(
        path: '/tenant/account/{id}',
        summary: '更新租户',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->insertInput($request);
            if (isset($this->validate) && $this->validate && !$this->validate->scene('update')->check($data)) {
                throw new \Exception($this->validate->getError());
            }
            $this->service->update($id, $data);
            return Json::success('更新成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/tenant/account/{id}',
        summary: '删除租户',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        try {
            $data = $this->getDeleteIds($request);
            if (empty($data)) throw new TenantException('删除参数不能为空');

            // 检查每个租户下是否还有用户
            foreach ($data as $id) {
                $tenant = $this->service->get($id);
                if (!$tenant) continue;

                $mode       = $tenant->database_mode ?? 'field';
                $connection = \core\business\tenant\SyncConnection::getConnectionName($id, $mode);

                $userCount = Admin::on($connection)
                    ->when($mode === 'field', fn($q) => $q->where('tenant_id', $id))
                    ->count();

                if ($userCount > 0) {
                    throw new TenantException("租户「{$tenant->name}」下存在 {$userCount} 个用户，请先删除用户后再删除租户");
                }
            }

            // 删除租户
            $this->service->transaction(function () use ($data) {
                foreach ($data as $id) {
                    $item = $this->service->get($id);
                    if ($item) $item->delete();
                }
            });

            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/tenant/account/{id}/status',
        summary: '更新租户状态',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function changeStatus(Request $request): \support\Response
    {
        try {
            $data       = $this->insertInput($request);
            $model      = $this->service->getModel();
            $primaryKey = $model->getKeyName();
            if (!array_key_exists($primaryKey, $data)) throw new \Exception('参数异常缺少主键');
            $targetModel = $model->findOrFail($data[$primaryKey]);
            if (empty($targetModel)) throw new TenantException('租户不存在');
            $targetModel->fill($data);
            if (!$targetModel->save()) throw new \RuntimeException('数据保存失败');
            return Json::success('更新成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/tenant/{id}/bind-plan',
        summary: '授权套餐',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', description: '套餐ID数组', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        tags: ['租户管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '租户ID', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function bindPlan(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $request->all();
            $ids  = $data['ids'] ?? [];
            if (empty($ids)) {
                return Json::fail('套餐ID不能为空');
            }
            $result = $this->service->bindPlan($id, $ids);
            return Json::success('授权成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/tenant/{id}/sync-data',
        summary: '同步数据',
        tags: ['租户管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '租户ID', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function syncData(Request $request): \support\Response
    {
        try {
            $id     = $request->route->param('id');
            $result = $this->service->syncData($id);
            return Json::success('同步成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
