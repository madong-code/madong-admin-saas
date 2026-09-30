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

namespace app\adminapi\controller\system;

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\schema\request\system\ConfigGroupQueryRequest;
use app\adminapi\schema\request\system\ConfigQueryRequest;
use app\adminapi\schema\request\system\ConfigValueRequest;
use app\service\admin\system\config\ConfigService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class ConfigController extends Crud
{
    public function __construct(ConfigService $service)
    {
        $this->service = $service;
    }


    #[OA\Get(
        path: "/system/config/group/{group_code}",
        summary: "按分组获取配置",
        tags: ["系统配置"],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => ConfigGroupQueryRequest::class,
        ]
    )]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: [], example: '{"site_open": "1","site_url": "http://127.0.0.1:8001","site_name": "madong-admin","site_logo": "https://madong.tech/assets/images/logo.svg","site_network_security": "2024042441号-2","site_description": "快速开发框架","site_record_no": "2024042442","site_icp_url": "https://beian.miit.gov.cn/","site_network_security_url": ""}')]
    public function getByGroup(Request $request, string $group_code): \support\Response
    {
        try {
            // 参数验证
            if (empty($group_code)) {
                return Json::fail('分组编码不能为空');
            }

            $options = [
                'enabled_only'  => $request->input('enabled_only', true),
                'with_metadata' => $request->input('with_metadata', false),
            ];
            $result  = $this->service->getByGroup($group_code, [], $options);
            return Json::success('操作成功', $result);
        } catch (\Exception $e) {

            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: "/system/config/code/{code}",
        summary: "按code获取配置",
        tags: ["系统配置"],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => ConfigQueryRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: "code",
        description: "配置编码",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "string"),
    )]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(schema: [], example: ' {"site_open": "1","site_url": "http://127.0.0.1:8001"}')]
    public function getByCode(Request $request, string $code): \support\Response
    {
        try {
            // 参数验证
            if (empty($code)) {
                return Json::fail('配置编码不能为空');
            }

            $groupCode = $request->input('group_code', 'default');
            $options   = [];
            if (!empty($groupCode)) {
                $options['group_code'] = $groupCode;
            }
            // 站点配置：追加存储运行时信息（upload_mode / cdn_url / is_private / storage_prefix），
            // 前端据此决定资源地址是直接拼接还是换取签名直链
            if ($code === 'site_setting') {
                $options['with_upload_info'] = true;
            }
            $result = $this->service->config($code, [], $options);
            return Json::success('操作成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 保存单个配置
     *
     * @param Request $request
     *
     * @return \support\Response
     * @throws \Exception
     */
    #[OA\Post(
        path: "/system/config",
        summary: "保存单个配置",
        tags: ["系统配置"],
    )]
    #[Permission(code: 'system:config:create')]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $request->all();

            // 参数验证
            if (empty($data['group_code']) || empty($data['code']) || empty($data['name'])) {
                return Json::fail('参数不能为空');
            }

            $options = [
                'group_code' => $data['group_code'],
                'name'       => $data['name'],
            ];

            if (isset($data['enabled'])) {
                $options['enabled'] = $data['enabled'];
            }

            $this->service->update(
                $data['code'],
                $data['content'],
                $options
            );

            return Json::success('保存成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 保存单个配置
     *
     * @param Request $request
     *
     * @return \support\Response
     * @throws \Exception
     */
    #[OA\Put(
        path: "/system/config/{code}",
        summary: "更新配置",
        tags: ["系统配置"],
    )]
    #[Permission(code: 'system:config:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $data = $request->all();
            $code = $request->route->param('code');

            $options = [
                'name'       => $data['name'],
                'group_code' => $data['group_code'] ?? '',
                'enabled'    => $data['enabled'] ?? 0,
            ];
            $this->service->update(
                $code,
                $data['content'],
                $options
            );

            return Json::success('更新成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 获取特定配置项的特定键值
     *
     * @param Request $request
     * @param string  $code
     *
     * @return \support\Response
     * @throws \Exception
     */
    #[OA\Get(
        path: "/system/config/{code}/value",
        summary: "获取特定配置项的特定键值",
        tags: ["系统配置"],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => ConfigValueRequest::class,
        ]
    )]
    #[SimpleResponse(schema: [], example: '{"data": "value"}')]
    public function getValue(Request $request, string $code): \support\Response
    {
        try {
            // 参数验证
            if (empty($code)) {
                return Json::fail('配置项编码不能为空');
            }

            $groupCode = $request->input('group_code');
            $key       = $request->input('key');

            if (empty($groupCode) || empty($key)) {
                return Json::fail('分组编码和配置键不能为空');
            }

            $result = $this->service->getValue($code, $key, null, ['group_code' => $groupCode]);
            return Json::success('操作成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }


    #[OA\Put(
        path: "/system/config/{code}/value",
        summary: "更新特定配置项的特定键值",
        tags: ["系统配置"],
    )]
    #[Permission(code: 'system:config:update_value')]
    #[SimpleResponse(schema: [], example: [])]
    public function setValue(Request $request, string $code): \support\Response
    {
        try {
            // 参数验证
            if (empty($code)) {
                return Json::fail('配置项编码不能为空');
            }

            $groupCode = $request->post('group_code');
            $key       = $request->post('key');
            $value     = $request->post('value');

            if (empty($groupCode) || empty($key)) {
                return Json::fail('分组编码和配置键不能为空');
            }

            $result = $this->service->setValue($code, $key, $value, ['group_code' => $groupCode]);
            if ($result) {
                return Json::success('更新成功');
            } else {
                return Json::fail('更新失败');
            }
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }


    #[OA\Get(
        path: "/system/config/all-grouped",
        summary: "获取所有配置并按分组整理",
        tags: ["系统配置"],
    )]
    #[OA\Parameter(
        name: "enabled_only",
        description: "是否只获取启用的配置项",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "boolean", default: true),
    )]
    #[OA\Parameter(
        name: "group_filter",
        description: "分组过滤，多个分组用逗号分隔",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "string"),
    )]
    #[OA\Parameter(
        name: "with_metadata",
        description: "是否包含配置项的完整元数据",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "boolean", default: false),
    )]
    #[OA\Parameter(
        name: "key_by",
        description: "按指定字段作为键名（'code' 或 'id'）",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "string", enum: ["code", "id"]),
    )]
    #[OA\Parameter(
        name: "sort_groups",
        description: "是否对分组进行排序",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "boolean", default: false),
    )]
    #[OA\Parameter(
        name: "sort_configs",
        description: "是否对配置项进行排序",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "boolean", default: false),
    )]
    #[SimpleResponse(schema: [], example: '{"site": {"site_name": "网站名称"}, "oss": {"accessKeyId": "xxx"}}')]
    public function getAllGrouped(Request $request): \support\Response
    {
        try {
            $options = [
                'enabled_only'  => $request->input('enabled_only', true),
                'with_metadata' => $request->input('with_metadata', false),
                'key_by'        => $request->input('key_by', null),
                'sort_groups'   => $request->input('sort_groups', false),
                'sort_configs'  => $request->input('sort_configs', false),
            ];

            // 处理分组过滤参数
            $groupFilter = $request->input('group_filter', '');
            if (!empty($groupFilter)) {
                $options['group_filter'] = explode(',', $groupFilter);
            }

            $result = $this->service->getAllGrouped($options);
            return Json::success('操作成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }


    #[OA\Get(
        path: "/system/config/items",
        summary: "获取配置项列表",
        tags: ["系统配置"],
    )]
    #[OA\Parameter(
        name: "page",
        description: "页码",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "integer", default: 1),
    )]
    #[OA\Parameter(
        name: "pageSize",
        description: "每页数量",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "integer", default: 20),
    )]
    #[OA\Parameter(
        name: "groupCode",
        description: "分组代码",
        in: "query",
        required: true,
        schema: new OA\Schema(type: "string"),
    )]
    #[OA\Parameter(
        name: "keyword",
        description: "搜索关键字",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "string"),
    )]
    #[SimpleResponse(schema: [], example: ['items'=>[],'total'=>0])]
    public function getItems(Request $request): \support\Response
    {
        try {
            $page      = $request->input('page', 1);
            $pageSize  = $request->input('pageSize', 20);
            $groupCode = $request->input('groupCode', '');
            $keyword   = $request->input('keyword', '');

            if (empty($groupCode)) {
                return Json::fail('分组代码不能为空');
            }

            $result = $this->service->getItems((int)$page, (int)$pageSize, $groupCode, $keyword);
            return Json::success('操作成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 创建单个配置项
     *
     * @param Request $request
     *
     * @return \support\Response
     * @throws \Exception
     */
    #[OA\Post(
        path: "/system/config/item",
        summary: "创建配置项",
        tags: ["系统配置"],
    )]
    #[Permission(code: 'system:config:create')]
    #[SimpleResponse(schema: [], example: [])]
    public function createItem(Request $request): \support\Response
    {
        try {
            $data = $request->post();

            // 参数验证
            if (empty($data['group_code']) || empty($data['code']) || empty($data['name']) || !isset($data['value'])) {
                return Json::fail('参数不能为空');
            }

            $type = $data['type'] ?? 'string';

            // 值类型转换
            $value = $this->convertValue($data['value'], $type);

            $configData = [
                'group_code'  => $data['group_code'],
                'code'        => $data['code'],
                'name'        => $data['name'],
                'content'     => $type === 'json' ? $value : (string)$value,
                'type'        => $type,
                'description' => $data['description'] ?? '',
                'enabled'     => $data['enabled'] ?? 1,
                'is_sys'      => 0,
                'remark'      => '',
            ];

            $this->service->createItem($configData);

            return Json::success('创建成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 更新单个配置项
     *
     * @param Request $request
     * @param int     $id
     *
     * @return \support\Response
     * @throws \Exception
     */
    #[OA\Put(
        path: "/system/config/item/{id}",
        summary: "更新配置项",
        tags: ["系统配置"],
    )]
    #[Permission(code: 'system:config:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function updateItem(Request $request, int $id): \support\Response
    {
        try {
            $data = $request->post();

            $type = $data['type'] ?? 'string';

            // 值类型转换
            $value = $this->convertValue($data['value'], $type);

            $configData = [
                'name'        => $data['name'],
                'content'     => $type === 'json' ? $value : (string)$value,
                'type'        => $type,
                'description' => $data['description'] ?? '',
                'enabled'     => $data['enabled'] ?? 1,
            ];

            $this->service->updateItem($id, $configData);

            return Json::success('更新成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }



    #[OA\Put(
        path: "/system/config/item/{id}/toggle",
        summary: "切换配置项状态",
        tags: ["系统配置"],
    )]
    #[Permission(code: 'system:config:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function toggleItem(Request $request, int $id): \support\Response
    {
        try {
            $enabled = $request->post('enabled', 0);
            $this->service->toggleItem($id, (bool)$enabled);
            return Json::success('操作成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 值类型转换
     *
     * @param mixed  $value 原始值
     * @param string $type  目标类型
     *
     * @return mixed
     */
    private function convertValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'string' => (string)$value,
            'number' => (string)$value,
            'boolean' => $value ? '1' : '0',
            'json' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string)$value
        };
    }

    /**
     * 删除配置项（按code）
     *
     * @param Request $request
     * @param string  $code   配置编码
     *
     * @return \support\Response
     * @throws \Exception
     */
    #[OA\Delete(
        path: "/system/config/{code}",
        summary: "删除配置项",
        tags: ["系统配置"],
    )]
    #[OA\Parameter(
        name: "code",
        description: "配置编码",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "string"),
    )]
    #[OA\Parameter(
        name: "group_code",
        description: "分组代码",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "string"),
    )]
    #[Permission(code: 'system:config:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function delete(Request $request, string $code): \support\Response
    {
        try {
            if (empty($code)) {
                return Json::fail('配置编码不能为空');
            }

            $options = [];
            $groupCode = $request->input('group_code');
            if (!empty($groupCode)) {
                $options['group_code'] = $groupCode;
            }

            $result = $this->service->delete($code, $options);
            if ($result) {
                return Json::success('删除成功');
            } else {
                return Json::fail('配置不存在');
            }
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}