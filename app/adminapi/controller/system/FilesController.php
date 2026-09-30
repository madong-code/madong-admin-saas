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
use app\adminapi\CurrentUser;
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
use core\io\upload\support\StorageUrl;
use core\io\upload\UploadScene;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use support\annotation\Middleware;
use support\Container;
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
        try {
            // 删除附件会同时清理云 / 本地物理资源，不可恢复，需二次校验当前登录管理员密码。
            // 校验使用当前身份（含租户库中的管理员），避免越权删除他人空间资源。
            $password = (string)$request->input('password', '');
            if ($password === '') {
                throw new AdminException('请输入管理员密码');
            }
            $admin = Container::make(CurrentUser::class)->admin();
            if (empty($admin) || !password_verify($password, (string)$admin->password)) {
                throw new AdminException('管理员密码错误');
            }

            $ids = $this->getDeleteIds($request);
            if (empty($ids)) {
                throw new AdminException('删除参数不能为空');
            }

            // 记录删除在 TenantScope 作用域内完成，物理资源按当前租户存储配置清理
            return Json::success('ok', $this->service->removeWithStorage($ids));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
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
        // 批量删除与单条删除语义一致：均需管理员密码校验并清理物理资源
        return $this->destroy($request);
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
        $subDir = (string)$request->input('sub_dir', '');
        $result = $this->service->saveNetworkImage($url, $subDir);
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
        path: '/system/files/access-urls',
        description: '按资源 key 批量换取可访问地址：公开空间返回访问域名拼接结果，私有空间（非公开读）返回带签名的临时直链；仅签发可内联渲染的图片/音频/视频，附件与下载包须走各自带归属校验的下载接口',
        summary: '资源访问地址批量换取',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['附件管理'],
    )]
    #[RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'keys',
                    description: '资源地址集合，支持相对路径与本空间域名下的绝对地址',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    example: ['/storage/avatar/202609/abc.png']
                ),
            ]
        )
    )]
    #[SimpleResponse(schema: [], example: ['data' => [['key' => '/storage/avatar/202609/abc.png', 'url' => 'https://cdn.example.com/storage/avatar/202609/abc.png?e=1790157600&token=xxx']]])]
    #[Permission(code: 'upload:files:access_urls')]
    #[AllowAnonymous(requireToken: true, requirePermission: false, description: '需携带登录身份：据此建立租户上下文，才能按当前租户的存储配置签发地址')]
    public function accessUrls(Request $request): \support\Response
    {
        try {
            $keys = $request->input('keys', []);
            if (is_string($keys)) {
                $keys = $keys === '' ? [] : explode(',', $keys);
            }
            if (!is_array($keys)) {
                throw new AdminException('参数 keys 必须是数组');
            }
            return Json::success('ok', StorageUrl::resolveMany(array_values($keys), UploadScene::admin()));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

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
