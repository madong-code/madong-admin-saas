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
use app\platform\validate\subscription\SubscriptionValidate;
use app\schema\request\BatchDeleteRequest;
use app\service\platform\template\MenuTemplateService;
use app\service\platform\subscription\SubscriptionService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;
#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class SubscriptionController extends Base
{
    public function __construct(SubscriptionService $service, SubscriptionValidate $validate)
    {
        $this->service = $service;
        $this->validate = $validate;
    }
    #[OA\Get(path: '/tenant/subscription', summary: '套餐列表', tags: ['套餐管理'])]
    #[PageResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $format_function = ['select' => 'formatSelect', 'tree' => 'formatTree', 'table_tree' => 'formatTableTree', 'normal' => 'formatNormal'][$format] ?? 'formatNormal';
            $total = $this->service->getCount($where);
            $list = $this->service->selectList($where, $field, $page, $limit, $order, [], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
    #[OA\Post(path: '/tenant/subscription', summary: '创建套餐', requestBody: new OA\RequestBody(required: true,
        content: new OA\JsonContent(
            required: ['name', 'status'],
            properties: [
                new OA\Property(property: 'name', description: '套餐名称', type: 'string'),
                new OA\Property(property: 'status', description: '状态', type: 'string', enum: ['active', 'disabled']),
                new OA\Property(property: 'price', description: '价格', type: 'number'),
            ]
        )
    ),
        tags: ['套餐管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);
            $this->validate->scene('store')->check($data);
            $model = $this->service->save($data);
            if (empty($model)) throw new \RuntimeException('创建套餐失败');
            return Json::success('创建成功', ['id' => $model->id]);
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
    #[OA\Get(path: '/tenant/subscription/{id}', summary: '套餐详情', tags: ['套餐管理'],
        parameters: [new OA\Parameter(name: 'id', description: '套餐ID', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $result = $this->service->get($id, null, ['tenants', 'permissions']);
            if (empty($result)) throw new \RuntimeException('套餐不存在');
            $data = $result->toArray();
            $data['tenant_ids'] = $result->tenants->pluck('id')->toArray();
            $data['permission_ids'] = $result->permissions->pluck('permission_id')->toArray();
            return Json::success('ok', $data);
        } catch (\Throwable $e) { return Json::fail($e->getMessage(), [], $e->getCode() ?: 400); }
    }
    #[OA\Put(path: '/tenant/subscription/{id}', summary: '更新套餐', requestBody: new OA\RequestBody(required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', description: '套餐名称', type: 'string'),
                new OA\Property(property: 'status', description: '状态', type: 'string', enum: ['active', 'disabled']),
                new OA\Property(property: 'price', description: '价格', type: 'number'),
            ]
        )
    ),
        tags: ['套餐管理'],
        parameters: [new OA\Parameter(name: 'id', description: '套餐ID', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            if (empty($id)) throw new \RuntimeException('套餐ID不能为空');
            $data = $this->insertInput($request);
            $data['id'] = $id;
            $this->validate->scene('update')->check($data);
            $this->service->update($id, $data);
            return Json::success('更新成功');
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
    #[OA\Delete(path: '/tenant/subscription/{id}', summary: '删除套餐', tags: ['套餐管理'],
        parameters: [new OA\Parameter(name: 'id', description: '套餐ID', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }
    #[OA\Delete(
        path: '/tenant/subscription',
        summary: '批量删除套餐',
        tags: ['套餐管理'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class]
    )]
    #[Permission(code: 'platform:tenant:subscription:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }
    #[OA\Put(path: '/tenant/subscription/{id}/bind-tenant', summary: '关联租户', requestBody: new OA\RequestBody(required: true,
        content: new OA\JsonContent(
            required: ['ids'],
            properties: [new OA\Property(property: 'ids', description: '租户ID数组', type: 'array', items: new OA\Items(type: 'integer'))]
        )
    ),
        tags: ['套餐管理'],
        parameters: [new OA\Parameter(name: 'id', description: '套餐ID', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function bindTenant(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $data = $request->all();
            $data['id'] = $id;
            $this->validate->scene('bindTenant')->check($data);
            $tenantIds = $this->service->bindTenant($id, $data['ids'] ?? []);
            return Json::success('关联成功', ['ids' => $tenantIds]);
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
    #[OA\Put(path: '/tenant/subscription/{id}/authorize', summary: '授权套餐权限策略', requestBody: new OA\RequestBody(required: true,
        content: new OA\JsonContent(
            required: ['ids'],
            properties: [new OA\Property(property: 'ids', description: '权限菜单ID数组', type: 'array', items: new OA\Items(type: 'string'))]
        )
    ),
        tags: ['套餐管理'],
        parameters: [new OA\Parameter(name: 'id', description: '套餐ID', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function authorize(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $data = $request->all();
            $data['id'] = $id;
            $this->validate->scene('authorize')->check($data);
            $permissionIds = $this->service->authorize($id, $data['ids'] ?? []);
            return Json::success('授权成功', ['ids' => $permissionIds]);
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }

    /**
     * 获取套餐授权权限树（模板资源数据）
     */
    #[OA\Get(
        path: '/tenant/subscription/permission/tree',
        summary: '套餐权限菜单树',
        tags: ['套餐管理'],
    )]
    public function permissionTree(Request $request): \support\Response
    {
        try {
            // 获取模板菜单（saas_template_menu, app='admin'）
            /** @var MenuTemplateService $templateService */
            $templateService = Container::make(MenuTemplateService::class);
            $templateMenus   = $templateService->getTree('admin');

            // 复用 PermissionController 格式化逻辑
            $controller = Container::make(\app\platform\controller\system\PermissionController::class);
            $tree       = $controller->formatPermissionTree($templateMenus);
            return Json::success('ok', $tree);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
