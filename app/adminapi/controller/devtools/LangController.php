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

namespace app\adminapi\controller\devtools;

use app\adminapi\controller\Base;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\service\core\lang\TranslationService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use support\Response;

#[Middleware(AccessTokenMiddleware::class, OperationMiddleware::class)]
final class LangController extends Base
{
    public function __construct(TranslationService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/devtools/lang/supported',
        summary: '获取支持的语言列表',
        tags: ['语言'],
        responses: [
            new OA\Response(
                response: 200,
                description: '成功',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'string')
                )
            )
        ]
    )]
     #[SimpleResponse(example: ['code' => 0,'message' => 'success','data' => []])]
    public function supported(Request $request): Response
    {
        try {
            $languages = $this->service->getSupportedLanguages();
            return json(['code' => 200, 'msg' => 'success', 'data' => $languages]);
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/devtools/lang',
        summary: '获取语言包列表',
        tags: ['语言'],
        parameters: [
            new OA\Parameter(
                name: 'language',
                description: '语言类型（如en|zh_CN等）',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'zh_CN')
            ),
            new OA\Parameter(
                name: 'file',
                description: '翻译文件名称',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'messages')
            ),
            new OA\Parameter(
                name: 'keyword',
                description: '搜索关键词（支持key和翻译内容）',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'page',
                description: '页码',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                description: '每页数量',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 10)
            )
        ]
    )]
    #[PageResponse(schema: [], example: [])]
    public function index(Request $request): Response
    {
        try {
            $language = $request->get('language');
            $file = $request->get('file');
            $keyword = $request->get('keyword');
            $page = (int)$request->get('page', 1);
            $limit = (int)$request->get('limit', 10);

            $result = $this->service->getList($language, $file, $keyword, $page, $limit);
            return json(['code' => 200, 'msg' => 'success', 'data' => $result]);
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/devtools/lang/files',
        summary: '获取翻译文件列表',
        tags: ['语言'],
        responses: [
            new OA\Response(
                response: 200,
                description: '成功',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'string')
                )
            )
        ]
    )]
    #[SimpleResponse(schema:[],example: [])]
    public function files(Request $request): Response
    {
        try {
            $files = $this->service->getFileList();
            return json(['code' => 200, 'msg' => 'success', 'data' => $files]);
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/devtools/lang/statistics',
        summary: '获取语言包统计信息',
        tags: ['语言'],
    )]
    #[SimpleResponse(schema:[],example: [])]
    public function statistics(Request $request): Response
    {
        try {
            $stats = $this->service->getStatistics();
            return json(['code' => 200, 'msg' => 'success', 'data' => $stats]);
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * @throws \core\exception\handler\AdminException
     */
    #[OA\Get(
        path: '/devtools/lang/translate',
        summary: '根据key获取翻译内容',
        tags: ['语言'],
        parameters: [
            new OA\Parameter(
                name: 'key',
                description: '翻译key（格式：文件.键，如：common.operation.success）',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'common.operation.success')
            ),
            new OA\Parameter(
                name: 'language',
                description: '语言类型（如en|zh_CN等）',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'zh_CN')
            )
        ]
    )]
     #[SimpleResponse(schema:[],example: [])]
    public function translate(Request $request): Response
    {
        try {
            $key = $request->get('key');
            $language = $request->get('language');
            $parameters = $request->get('parameters', []);
            if(empty($language)){
                $language = locale();
            }

            // 如果parameters是字符串，尝试解析为JSON
            if (is_string($parameters)) {
                $parameters = json_decode($parameters, true) ?: [];
            }

            $translation = $this->service->getTranslationByKey($key, $language, $parameters);

            if ($translation === null) {
                return json(['code' => 404, 'msg' => 'Translation key not found', 'data' => $key]);
            }

            return Json::success('common.operation.success',$translation);
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }
}