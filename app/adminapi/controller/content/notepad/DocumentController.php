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
namespace app\adminapi\controller\content\notepad;

use app\adminapi\CurrentUser;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\service\admin\content\notepad\NotepadDocumentService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Container;
use support\Request;
use support\Response;
use support\annotation\Middleware;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class DocumentController
{
    public function __construct(
        private NotepadDocumentService $documentService,
    ) {}

    private function getUserId(Request $request): string
    {
        return (string) Container::make(CurrentUser::class)->id();
    }

    #[OA\Get(
        path: '/content/notepad/document',
        summary: '获取文档列表',
        tags: ['记事本']
    )]
    #[OA\Parameter(
        name: 'folder_id',
        description: '文件夹ID (传 recent 则取最近使用)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: '搜索关键词',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
    )]
    #[Permission(code: 'notepad:document:list')]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): Response
    {
        try {
            $userId = $this->getUserId($request);
            $folderId = $request->input('folder_id', '');
            $keyword = trim((string) $request->input('keyword', ''));
            $list = $this->documentService->getListByUser($userId, $folderId, $keyword);
            return Json::success('获取成功', $list);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/content/notepad/document/{id}',
        summary: '获取文档详情',
        tags: ['记事本']
    )]
    #[Permission(code: 'notepad:document:read')]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request, string $id): Response
    {
        try {
            $userId = $this->getUserId($request);
            $doc = $this->documentService->getByUser($userId, $id);
            if (!$doc) {
                return Json::fail('文档不存在');
            }
            return Json::success('获取成功', $doc);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/content/notepad/document',
        summary: '创建文档',
        tags: ['记事本']
    )]
    #[Permission(code: 'notepad:document:create')]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): Response
    {
        try {
            $data = $request->post();
            $userId = $this->getUserId($request);

            if (empty($data['folder_id'])) {
                return Json::fail('请选择文件夹');
            }

            $doc = $this->documentService->createByUser($userId, $data);
            return Json::success('创建成功', $doc);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/content/notepad/document/{id}',
        summary: '更新文档',
        tags: ['记事本']
    )]
    #[Permission(code: 'notepad:document:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request, string $id): Response
    {
        try {
            $userId = $this->getUserId($request);
            $data = $request->post();

            $doc = $this->documentService->updateByUser($userId, $id, $data);
            if (!$doc) {
                return Json::fail('文档不存在');
            }

            return Json::success('更新成功', $doc);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/content/notepad/document/{id}/move',
        summary: '移动文档到其他文件夹',
        tags: ['记事本']
    )]
    #[Permission(code: 'notepad:document:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function move(Request $request, string $id): Response
    {
        try {
            $userId = $this->getUserId($request);
            $folderId = $request->post('folder_id', '');

            if (empty($folderId)) {
                return Json::fail('请选择目标文件夹');
            }

            $result = $this->documentService->moveByUser($userId, $id, $folderId);
            if (!$result) {
                return Json::fail('文档不存在');
            }

            return Json::success('移动成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/content/notepad/document/{id}',
        summary: '删除文档',
        tags: ['记事本']
    )]
    #[Permission(code: 'notepad:document:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function delete(Request $request, string $id): Response
    {
        try {
            $userId = $this->getUserId($request);
            $result = $this->documentService->deleteByUser($userId, $id);
            if (!$result) {
                return Json::fail('文档不存在');
            }
            return Json::success('删除成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
