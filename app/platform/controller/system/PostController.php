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
use app\service\admin\system\org\PostService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class PostController extends Base
{
    public function __construct(PostService $service)
    {
        $this->service = $service;
    }
    #[OA\Get(
        path: '/system/post',
        summary: '岗位列表（表单组件专用）',
        tags: ['岗位'],
    )]
    #[OA\Parameter(name: 'page', description: '页码', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', description: '每页数量', in: 'query', schema: new OA\Schema(type: 'integer', default: 1000))]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $methods = ['select' => 'formatSelect', 'tree' => 'formatTree', 'table_tree' => 'formatTableTree', 'normal' => 'formatNormal'];
            $formatFunction = $methods[$format] ?? 'formatNormal';
            $total = $this->service->getCount($where);
            $list = $this->service->selectList($where, $field, $page, $limit, $order, ['dept']);
            return call_user_func([$this, $formatFunction], $list, $total);
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
    #[OA\Get(
        path: '/system/post/{id}',
        summary: '岗位详情',
        tags: ['岗位'],
    )]
    #[OA\Parameter(name: 'id', description: '岗位ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $data = $this->service->get($id, ['*'], ['dept']);
            if (empty($data)) return Json::fail('数据未找到');
            return Json::success('ok', $data->toArray());
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
}
