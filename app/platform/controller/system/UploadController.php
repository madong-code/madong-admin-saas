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
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use core\io\upload\support\StorageUrl;
use core\io\upload\UploadFile;
use core\io\upload\UploadScene;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use support\annotation\Middleware;
use support\Request;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class UploadController extends Base
{
    /**
     * 通用文件上传
     *
     * 使用平台级上传配置（group_code=platform），bypass 租户隔离。
     * 上传后的文件 URL 可直接用于站点设置 logo、favicon 等场景。
     */
    #[OA\Post(
        path: '/file/upload',
        summary: '平台文件上传',
        tags: ['上传管理'],
    )]
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
                        format: 'binary',
                    ),
                    new OA\Property(
                        property: 'sub_dir',
                        description: '子目录路径，例如：image/202603',
                        type: 'string',
                        example: 'image/202603',
                    ),
                ],
            ),
        ),
    )]
    #[SimpleResponse(schema: [], example: ['url' => 'http://example.com/upload/xxx.jpg', 'base_path' => '/upload/xxx.jpg'])]
    public function upload(Request $request): \support\Response
    {
        try {
            $subDir = $request->input('sub_dir', 'image');
            $subDir .= '/' . date('Ym');

            // 使用平台场景：group_code=platform → 用 platform\ConfigService（bypass TenantScope）
            $disk = UploadFile::disk(null, true, UploadScene::platform());
            $result = $disk->uploadFile(['sub_dir' => $subDir]);
            $data = $result[0];

            $url = str_replace('\\', '/', $data['url'] ?? '');
            $path = str_replace('\\', '/', $data['save_path'] ?? '');
            $basePath = str_replace('\\', '/', $data['base_path'] ?? '');

            return Json::success('上传成功', [
                'url'       => $url,
                'base_path' => $basePath,
                'path'      => $path,
            ]);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/file/access-urls',
        description: '按资源 key 批量换取可访问地址：公开空间返回访问域名拼接结果，私有空间（非公开读）返回带签名的临时直链；仅签发可内联渲染的图片/音频/视频，附件与下载包须走各自带归属校验的下载接口',
        summary: '资源访问地址批量换取',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['上传管理'],
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
                    example: ['/storage/avatar/202609/abc.png'],
                ),
            ],
        ),
    )]
    #[SimpleResponse(schema: [], example: ['data' => [['key' => '/storage/avatar/202609/abc.png', 'url' => 'https://cdn.example.com/storage/avatar/202609/abc.png?e=1790157600&token=xxx']]])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口（仅签发可内联渲染的媒体，登录时携带身份）')]
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
            return Json::success('ok', StorageUrl::resolveMany(array_values($keys), UploadScene::platform()));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
