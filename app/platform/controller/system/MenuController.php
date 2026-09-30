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

namespace app\platform\controller\system;

use app\adminapi\validate\system\menu\MenuValidate;
use app\model\system\menu\Menu;
use app\platform\controller\Base;
use app\schema\request\IdRequest;
use app\scope\global\AccessPermissionScope;
use app\service\admin\system\menu\MenuService;
use app\service\core\plugin\PluginService;
use core\foundation\tool\Json;
use madong\helper\Arr;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use support\Container;
use support\annotation\Middleware;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class MenuController extends Base
{
    public function __construct(MenuService $service, MenuValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/system/menu',
        summary: '系统菜单列表（支持 tree/select/table_tree 格式）',
        tags: ['系统菜单'],
        parameters: [
            new OA\Parameter(name: 'format', description: '返回格式：tree|select|table_tree|normal', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'app', description: '按应用过滤', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'name', description: '菜单名称', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', description: '菜单类型', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', description: '页码', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', description: '每页数量', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $methods = [
                'select'     => 'formatSelect',
                'tree'       => 'formatTree',
                'table_tree' => 'formatTableTree',
                'normal'     => 'formatNormal',
            ];
            if (empty($order)) {
                $order = 'sort asc';
            }
            $format_function = $methods[$format] ?? 'formatNormal';
            $total           = $this->service->getCount($where);
            $list            = $this->service->selectList($where, $field, $page, $limit, $order, [], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/menu/{id}',
        summary: '系统菜单详情',
        tags: ['系统菜单'],
        parameters: [
            new OA\Parameter(name: 'id', description: '菜单ID', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/system/menu',
        summary: '创建系统菜单',
        tags: ['系统菜单'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/system/menu/{id}',
        summary: '更新系统菜单',
        tags: ['系统菜单'],
        parameters: [
            new OA\Parameter(name: 'id', description: '菜单ID', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $request->all();
            $model = $this->service->update($id, $data);
            return Json::success('更新成功', $model->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/system/menu',
        summary: '批量删除系统菜单',
        tags: ['系统菜单'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'ids',
            SchemaConstants::X_SCHEMA_REQUEST => \app\schema\request\BatchDeleteRequest::class,
        ],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        try {
            $data = $this->getDeleteIds($request);
            if (empty($data)) {
                return Json::fail('参数错误');
            }
            $result = $this->service->batchDelete($data);
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/system/menu/{id}',
        summary: '删除系统菜单',
        tags: ['系统菜单'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function delete(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $this->service->batchDelete(Arr::normalize($id));
            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/system/menu/batch-store',
        summary: '批量添加系统菜单（从接口选择器导入）',
        tags: ['系统菜单'],
    )]
    #[RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'menus', type: 'array', items: new OA\Items(type: 'object')),
        ],
        type: 'object',
    ))]
    #[SimpleResponse(schema: [], example: [])]
    public function batchStore(Request $request): \support\Response
    {
        try {
            $params = $request->input('menus', []);
            $data   = [];
            if (isset($this->validate) && $this->validate) {
                foreach ($params as $param) {
                    $data[] = $this->inputFilter($param);
                    if (!$this->validate->scene('batch-store')->check($param)) {
                        throw new \Exception($this->validate->getError());
                    }
                }
            }
            // 平台端操作主库 sys_menu，显式指定 mysql 连接防止 TenantContext 重定向到租户库
            foreach ($data as $item) {
                Menu::on('mysql')->create($item);
            }
            return Json::success('ok');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/menu/app/list',
        summary: '获取应用列表（供下拉选择器使用）',
        tags: ['系统菜单'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function appList(Request $request): \support\Response
    {
        try {
            /** @var PluginService $pluginService */
            $pluginService = Container::make(PluginService::class);
            $plugins = $pluginService->selectList([], '*', 0, 0, '', [], false, [AccessPermissionScope::class]);
            return $this->formatAppSelect($plugins);
        } catch (\Exception $e) {
            return Json::fail('获取应用列表失败', []);
        }
    }

    /**
     * 格式化应用列表为下拉选择器数据
     *
     * @param mixed $plugins
     * @return \support\Response
     */
    private function formatAppSelect($plugins): \support\Response
    {
        $formatted   = [];
        $formatted[] = ['label' => '系统应用', 'value' => 'admin'];
        foreach ($plugins as $item) {
            $formatted[] = [
                'label' => $item->title ?? $item->name ?? $item->real_name ?? $item->id,
                'value' => $item->id,
            ];
        }
        return Json::success('ok', $formatted);
    }
}
