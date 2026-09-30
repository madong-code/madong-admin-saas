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

namespace app\api\controller\system;

use app\api\controller\Base;
use app\api\middleware\ApiAccessTokenMiddleware;
use app\api\schema\request\system\ConfigQueryRequest;
use app\api\schema\request\system\ConfigValueRequest;
use app\service\api\system\ConfigService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;

//#[Middleware(ApiAccessTokenMiddleware::class)]
final class ConfigController extends Base
{
    public function __construct(ConfigService $service)
    {
        $this->service = $service;
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
    #[SimpleResponse(schema: [], example: ' {"site_open": "1","site_url": "http://127.0.0.1:8001"}')]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getByCode(Request $request, string $code): \support\Response
    {
        try {
            if (empty($code)) {
                return Json::fail('配置编码不能为空');
            }

            // 先查指定分组（如果有），再自动回退到 default 分组
            $groupCode = $request->input('group_code', '');
            $options   = [];
            if (!empty($groupCode)) {
                $options['group_code'] = $groupCode;
                $options['fallback_groups'] = ['default'];
            }
            $result = $this->service->config($code, [], $options);
            return Json::success('操作成功', $result);
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
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getValue(Request $request, string $code): \support\Response
    {
        try {
            if (empty($code)) {
                return Json::fail('配置项编码不能为空');
            }

            $key = $request->input('key');
            if (empty($key)) {
                return Json::fail('配置键不能为空');
            }

            // 自动在指定分组（如有）和 default 分组中查找
            $groupCode = $request->input('group_code', '');
            $options   = ['search_groups' => ['default']];
            if (!empty($groupCode)) {
                array_unshift($options['search_groups'], $groupCode);
            }
            $result = $this->service->getValue($code, $key, null, $options);
            return Json::success('操作成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 获取所有配置并按分组整理
     *
     * @param Request $request
     *
     * @return \support\Response
     * @throws \Exception
     */
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
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
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
}