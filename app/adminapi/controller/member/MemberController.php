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

namespace app\adminapi\controller\member;

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\validate\member\MemberValidate;
use app\api\CurrentMember;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\member\MemberService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class MemberController extends Crud
{

    public function __construct(MemberService $service, MemberValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/member/user',
        summary: '会员用户列表',
        tags: ['会员用户'],
        parameters: [
            new OA\Parameter(name: "username", description: "用户名", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "nickname", description: "昵称", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "phone", description: "手机号", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "email", description: "邮箱", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "level_id", description: "等级ID", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "status", description: "状态", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "page", description: "页码", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "limit", description: "每页数量", in: "query", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "获取成功"),
                    new OA\Property(property: "data", properties: [
                        new OA\Property(property: "total", type: "integer", example: 10),
                        new OA\Property(property: "list", type: "array", items: new OA\Items(properties: [
                            new OA\Property(property: "id", type: "integer", example: 1),
                            new OA\Property(property: "username", type: "string", example: "testuser"),
                            new OA\Property(property: "nickname", type: "string", example: "测试用户"),
                            new OA\Property(property: "phone", type: "string", example: "13800138000"),
                            new OA\Property(property: "email", type: "string", example: "test@example.com"),
                            new OA\Property(property: "avatar", type: "string", example: ""),
                            new OA\Property(property: "level_id", type: "integer", example: 1),
                            new OA\Property(property: "status", type: "integer", example: 1),
                            new OA\Property(property: "create_time", type: "integer", example: 1640995200),
                        ])),
                    ], type: "object"),
                ])
            ),
        ]
    )]
    #[Permission("member:user:list")]
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
            $list            = $this->service->selectList($where, $field, $page, $limit, $order, ['level', 'tags'], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/member/user/{id}',
        summary: '会员用户详情',
        tags: ['会员用户'],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "获取成功"),
                    new OA\Property(property: "data", properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "username", type: "string", example: "testuser"),
                        new OA\Property(property: "nickname", type: "string", example: "测试用户"),
                        new OA\Property(property: "phone", type: "string", example: "13800138000"),
                        new OA\Property(property: "email", type: "string", example: "test@example.com"),
                        new OA\Property(property: "avatar", type: "string", example: ""),
                        new OA\Property(property: "level_id", type: "integer", example: 1),
                        new OA\Property(property: "status", type: "integer", example: 1),
                        new OA\Property(property: "create_time", type: "integer", example: 1640995200),
                    ], type: "object"),
                ])
            ),
        ],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission("member:user:read")]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/member/user',
        summary: '创建',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "username", type: "string", example: "testuser"),
                new OA\Property(property: "nickname", type: "string", example: "测试用户"),
                new OA\Property(property: "phone", type: "string", example: "13800138000"),
                new OA\Property(property: "email", type: "string", example: "test@example.com"),
                new OA\Property(property: "password", type: "string", example: "123456"),
                new OA\Property(property: "avatar", type: "string", example: ""),
                new OA\Property(property: "level_id", type: "integer", example: 1),
                new OA\Property(property: "status", type: "integer", example: 1),
            ])
        ),
        tags: ['会员用户'],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "创建成功"),
                    new OA\Property(property: "data", properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                    ], type: "object"),
                ])
            ),
        ],
    )]
    #[Permission("member:user:create")]
    #[SimpleResponse( example: '{"code":0,"msg":"created","id":1}')]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/member/user/{id}',
        summary: '更新',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "nickname", type: "string", example: "新昵称"),
                new OA\Property(property: "phone", type: "string", example: "13800138000"),
                new OA\Property(property: "email", type: "string", example: "test@example.com"),
                new OA\Property(property: "avatar", type: "string", example: ""),
                new OA\Property(property: "level_id", type: "integer", example: 2),
                new OA\Property(property: "status", type: "integer", example: 1),
            ])
        ),
        tags: ['会员用户'],
        parameters: [
            new OA\Parameter(name: "id", description: "用户ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "更新成功"),
                    new OA\Property(property: "data", properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                    ], type: "object"),
                ])
            ),
        ]
    )]
    #[Permission("member:user:update")]
    public function update(Request $request): \support\Response
    {

        return parent::update($request);
    }

    #[OA\Delete(
        path: '/member/user/{id}',
        summary: '删除',
        tags: ['会员用户'],
        parameters: [
            new OA\Parameter(name: "id", description: "用户ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "删除成功"),
                ])
            ),
        ],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission("member:user:delete")]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/member/user',
        summary: '批量删除',
        tags: ['会员用户'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class],
    )]
    #[Permission("member:user:delete")]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Put(
        path: '/member/user/{id}/reset-password',
        summary: '重置密码',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "password", type: "string", example: "123456"),
            ])
        ),
        tags: ['会员用户'],
        parameters: [
            new OA\Parameter(name: "id", description: "用户ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "密码重置成功"),
                ])
            ),
        ],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission("member:user:reset_password")]
    public function resetPassword(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->insertInput($request);
            if (empty($data['password'])) {
                return Json::fail('密码不能为空');
            }
            $this->service->update($id, ['password' => $data['password']]);
            return Json::success('密码重置成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/member/user/{id}/assign-tags',
        summary: '分配标签',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "tags", type: "array", example: [1, 2, 3]),
            ])
        ),
        tags: ['会员用户'],
    )]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    #[Permission("member:user:assign_tags")]
    public function assignTags(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $request->input('tags', []);
            if (empty($data)) {
                return Json::fail('标签不能为空');
            }
            $this->service->assignTags($id, $data);
            return Json::success('标签分配成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/member/user/{id}/adjust-points',
        summary: '调整会员积分',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "points", type: "integer", example: 100),
            ])
        ),
        tags: ['会员用户'],
        parameters: [
            new OA\Parameter(name: "id", description: "用户ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    #[Permission("member:user:adjust_points")]
    public function adjustPoints(Request $request): \support\Response
    {
        try {
            $id     = $request->route->param('id');
            $points = $request->input('points', 0);
            $this->service->adjustPoints($id, (int)$points);
            return Json::success('积分调整成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 格式化下拉列表
     *
     * @param $items
     *
     * @return \support\Response
     */
    public function formatSelect($items): \support\Response
    {
        $formatted_items = [];
        foreach ($items as $item) {
            $formatted_items[] = [
                'label' => $item->nickname ?? $item->id,
                'value' => $item->id,
            ];
        }
        return Json::success('ok', $formatted_items);
    }

//    #[OA\Get(
//        path: '/member/user/statistics',
//        summary: '会员统计',
//        tags: ['会员用户'],
//        responses: [
//            new OA\Response(
//                response: 200,
//                description: "成功",
//                content: new OA\JsonContent(properties: [
//                    new OA\Property(property: "code", type: "integer", example: 0),
//                    new OA\Property(property: "msg", type: "string", example: "获取成功"),
//                    new OA\Property(property: "data", properties: [
//                        new OA\Property(property: "total", type: "integer", example: 100),
//                        new OA\Property(property: "active", type: "integer", example: 80),
//                        new OA\Property(property: "inactive", type: "integer", example: 20),
//                    ], type: "object"),
//                ])
//            ),
//        ]
//    )]
//    #[Permission("member:user:statistics")]
    public function statistics(Request $request): \support\Response
    {
        try {
            $result = $this->service->getStatistics();
            return Json::success('获取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
