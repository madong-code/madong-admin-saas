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
use app\adminapi\schema\request\system\UploadQueryRequest;
use app\adminapi\schema\response\system\UploadResponse;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\system\config\UploadService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use support\annotation\Middleware;
use support\Request;
use Webman\RedisQueue\Client;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class FilesController extends Crud
{

    public function __construct(UploadService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/system/files',
        summary: '列表',
        tags: ['附件管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => UploadQueryRequest::class,
        ]
    )]
    #[Permission(code: 'upload:files:list')]
    #[PageResponse(schema: UploadResponse::class, example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/system/files/{id}',
        summary: '详情',
        tags: ['附件管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'upload:files:read')]
    #[SimpleResponse(schema: UploadResponse::class, example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Delete(
        path: '/system/files/{id}',
        summary: '删除',
        tags: ['附件管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'upload:files:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/system/files',
        summary: '批量删除',
        tags: ['附件管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'upload:files:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Post(
        path: '/system/files/fetch-and-save-image',
        summary: '上传网络图片到服务器',
        tags: ['附件管理'],
    )]
    #[RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'url',
                    description: '图片URL',
                    type: 'string',
                    example: 'https://example.com/image.jpg'
                ),
            ]
        )
    )]
    #[Permission(code: 'upload:files:fetch_and_save_image')]
    #[SimpleResponse(schema: [], example: [])]
    public function downloadNetworkImage(Request $request): \support\Response
    {
        $url    = $request->input('url', '');
        $result = $this->service->saveNetworkImage($url);
        return Json::success('操作成功', $result);
    }

    #[OA\Get(
        path: '/system/files/download-by-id/{id}',
        summary: '根据ID下载资源',
        tags: ['附件管理'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: '文件ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: '123456789012345678')
            ),
        ]
    )]
    #[Permission(code: 'upload:files:download_by_id')]
    #[SimpleResponse(schema: [], example: [])]
    public function downloadResourceById(Request $request): \support\Response|\Webman\Http\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->service->get($id);
            if (empty($data)) {
                throw new AdminException('数据未找到', -1);
            }
            return response()->download($data->path, $data->filename);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    #[OA\Get(
        path: '/system/files/download-by-hash/{hash}',
        summary: '根据hash下载资源',
        tags: ['附件管理'],
        parameters: [
            new OA\Parameter(
                name: 'hash',
                description: '文件Hash',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: '123456789012345678')
            ),
        ]
    )]
    #[Permission(code: 'upload:files:download_by_hash')]
    #[SimpleResponse(schema: [], example: [])]
    public function downloadResourceByHash(Request $request): \support\Response|\Webman\Http\Response
    {
        try {
            $hash = $request->route->param('hash');
            $data = $this->service->get(['hash' => $hash]);
            if (empty($data)) {
                throw new AdminException('数据未找到', -1);
            }
            return response()->download($data->path, $data->filename);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    /**
     * @throws \Throwable
     */
    #[OA\Post(
        path: '/system/files/upload-image',
        summary: '上传图片',
        tags: ['附件管理'],
    )]
    #[Permission(code: 'upload:files:upload_image')]
    #[RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'file',
                        description: '文件',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'sub_dir',
                        description: '子目录路径，例如：image/202603',
                        type: 'string',
                        example: 'image/202603'
                    ),
                ]
            )
        )
    )]
    #[SimpleResponse(schema: UploadResponse::class, example: [])]
    public function uploadImage(Request $request): \support\Response
    {
        try {
            $subDir = $request->input('sub_dir', 'image');
            $subDir .= '/' . date('Ym');
            $result = $this->service->upload($subDir);
            return Json::success('ok', $result->toArray());
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/system/files/upload-file',
        summary: '上传文件',
        tags: ['附件管理'],
    )]
    #[Permission(code: 'upload:files:upload_file')]
    #[RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'file',
                        description: '文件',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'sub_dir',
                        description: '子目录路径，例如：file/202603',
                        type: 'string',
                        example: 'file/202603'
                    ),
                ]
            )
        )
    )]
    #[SimpleResponse(schema: UploadResponse::class, example: [])]
    public function uploadFile(Request $request): \support\Response
    {
        try {
            $subDir = $request->input('sub_dir', 'file');
            $subDir .= '/' . date('Ym');
            $result = $this->service->upload($subDir);
            return Json::success('ok', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 上传图片返回base64
     * 用于插件开发等需要base64预览的场景
     */
    #[OA\Post(
        path: '/system/files/upload-image-base64',
        summary: '上传图片返回base64',
        tags: ['附件管理'],
    )]
    #[Permission(code: 'upload:files:upload_image_base64')]
    #[RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'file',
                    description: '图片文件',
                    type: 'string',
                    format: 'binary'
                ),
            ]
        )
    )]
    public function uploadImageBase64(Request $request): \support\Response
    {
        try {
            $file = $request->file('file');
            if (!$file) {
                return Json::fail('请选择图片文件');
            }

            // 验证文件类型
            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
            if (!in_array($file->getUploadMimeType(), $allowedTypes)) {
                return Json::fail('只支持PNG、JPEG、JPG、GIF格式的图片');
            }

            // 验证文件大小(最大2MB)
            $maxSize = 2 * 1024 * 1024;
            if ($file->getSize() > $maxSize) {
                return Json::fail('图片大小不能超过2MB');
            }

            // 读取文件内容并转换为base64
            $imageData = file_get_contents($file->getPathname());
            if ($imageData === false) {
                return Json::fail('图片读取失败');
            }

            $base64 = 'data:' . $file->getUploadMimeType() . ';base64,' . base64_encode($imageData);

            return Json::success('上传成功', [
                'base64' => $base64,
                'size'   => $file->getSize(),
                'mime'   => $file->getUploadMimeType(),
            ]);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/export/download-excel',
        summary: '下载导出的excel',//下载导出的excel-后期需要优化实现下载成功后删除原文件
        tags: ['附件管理'],
    )]
    #[Permission(code: 'upload:files:download_excel')]
    public function downloadExcel(Request $request): ?\support\Response
    {

        $param        = $request->all();
        $file         = $param['file_path'];//文件路径
        $downloadName = $param['file'] ?? date('Y-m-d His', time());//文件下载名称
        $filePath     = runtime_path() . $file;
        //生成的文件5分钟后超时自动删除
        $queue = 'remove-excel-file';
        Client::send($queue, $param, 300);
        return response()->download($filePath, $downloadName);
    }

    #[OA\Post(
        path: '/system/files/common/wangeditor',
        summary: '适配wangeditor编辑器上传接口',
        tags: ['附件管理'],
    )]
    #[Permission(code: 'upload:files:wangeditor')]
    public function wangeditor(Request $request): \support\Response
    {
        try {
            $subDir      = $request->input('sub_dir', 'image');
            $subDir      .= '/' . date('Ym');
            $result      = $this->service->upload($subDir);
            $data        = $result->toArray();
            $data['url'] = $data['base_path'] ?? '';
            return Json(['errno' => 0, 'data' => $data]);
        } catch (\Exception $e) {
            return Json([
                'errno'   => 1,
                'message' => $e->getMessage(),
            ]);
        }
    }

}
