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
use app\platform\controller\Base;
use app\service\admin\system\org\DeptService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class DeptController extends Base
{
    public function __construct(DeptService $service)
    {
        $this->service = $service;
    }
    #[OA\Get(
        path: '/system/dept',
        summary: '部门列表/树（表单组件专用）',
        tags: ['部门'],
    )]
    #[OA\Parameter(name: 'format', description: '数据格式：tree=树形, normal=列表', in: 'query', schema: new OA\Schema(type: 'string', default: 'tree'))]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $methods = ['select' => 'formatSelect', 'tree' => 'formatTree', 'table_tree' => 'formatTableTree', 'normal' => 'formatNormal'];
            $formatFunction = $methods[$format] ?? 'formatTree';
            $total = $this->service->getCount($where);
            $list = $this->service->selectList($where, $field, $page, $limit, $order);
            return call_user_func([$this, $formatFunction], $list, $total);
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
    #[OA\Get(
        path: '/system/dept/{id}',
        summary: '部门详情',
        tags: ['部门'],
    )]
    #[OA\Parameter(name: 'id', description: '部门ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $data = $this->service->get($id);
            if (empty($data)) return Json::fail('数据未找到');
            return Json::success('ok', $data->toArray());
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
}
