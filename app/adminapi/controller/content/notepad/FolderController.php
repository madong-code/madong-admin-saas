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
use app\service\admin\content\notepad\NotepadFolderService;
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
final class FolderController
{
    public function __construct(
        private NotepadFolderService $folderService,
        private NotepadDocumentService $documentService,
    ) {}

    private function getUserId(Request $request): string
    {
        return (string) Container::make(CurrentUser::class)->id();
    }

    #[OA\Get(
        path: '/content/notepad/folder/tree',
        summary: '获取文件夹树',
        tags: ['记事本']
    )]
    #[Permission(code: 'notepad:folder:list')]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function tree(Request $request): Response
    {
        try {
            $userId = $this->getUserId($request);
            $tree = $this->folderService->getTree($userId);
            return Json::success('获取成功', $tree);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/content/notepad/folder',
        summary: '获取所有文件夹',
        tags: ['记事本']
    )]
    #[Permission(code: 'notepad:folder:list')]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): Response
    {
        try {
            $userId = $this->getUserId($request);
            $list = $this->folderService->getList($userId);
            return Json::success('获取成功', $list);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/content/notepad/folder',
        summary: '创建文件夹',
        tags: ['记事本']
    )]
    #[Permission(code: 'notepad:folder:create')]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): Response
    {
        try {
            $data = $request->post();
            $userId = $this->getUserId($request);

            if (empty($data['name'])) {
                return Json::fail('文件夹名称不能为空');
            }

            $folder = $this->folderService->createByUser($userId, $data);
            return Json::success('创建成功', $folder);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/content/notepad/folder/{id}',
        summary: '更新文件夹',
        tags: ['记事本']
    )]
    #[Permission(code: 'notepad:folder:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request, string $id): Response
    {
        try {
            $userId = $this->getUserId($request);
            $data = $request->post();

            $result = $this->folderService->updateByUser($userId, $id, $data);
            if (!$result) {
                return Json::fail('文件夹不存在');
            }

            return Json::success('更新成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/content/notepad/folder/{id}',
        summary: '删除文件夹(含子文件夹和文档)',
        tags: ['记事本']
    )]
    #[Permission(code: 'notepad:folder:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function delete(Request $request, string $id): Response
    {
        try {
            $userId = $this->getUserId($request);
            $result = $this->folderService->deleteByUser($userId, $id, $this->documentService);
            if (!$result) {
                return Json::fail('文件夹不存在');
            }
            return Json::success('删除成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
