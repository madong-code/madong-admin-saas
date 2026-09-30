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

namespace app\api\controller\upload;

use app\api\controller\Base;
use app\service\api\upload\UploadService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use support\Request;

final class UploadController extends Base
{

    public function __construct(UploadService $service)
    {
        $this->service = $service;
    }

    #[OA\Post(
        path: '/file/image',
        description: '上传图片文件，支持 JPG/PNG/GIF/WEBP 格式，最大 5MB',
        summary: '图片上传',
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
                        description: '图片文件',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'sub_dir',
                        description: '子目录路径，例如：video/202603',
                        type: 'string',
                        example: 'images'
                    ),
                ]
            )
        )
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function image(Request $request): \support\Response
    {
        try {
            $subDir = $request->input('sub_dir', 'image');
            $subDir .= '/' . date('Ym');
            $result = $this->service->uploadImage($subDir);
            return Json::success('上传成功', $result->toArray());
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/file/video',
        description: '上传视频文件，支持 MP4/AVI/MOV/WMV/FLV/MKV 格式，最大 100MB',
        summary: '视频上传',
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
                        description: '视频文件',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'sub_dir',
                        description: '子目录路径，例如：video/202603',
                        type: 'string',
                        example: 'video/202603'
                    ),
                ]
            )
        )
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function video(Request $request): \support\Response
    {
        try {
            $subDir = $request->input('sub_dir', 'video');
            $subDir .= '/' . date('Ym');
            $result = $this->service->uploadVideo($subDir);
            return Json::success('上传成功', $result->toArray());
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/file',
        description: '上传普通文件，支持文档和压缩包，最大 50MB',
        summary: '文件上传',
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
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function file(Request $request): \support\Response
    {
        try {
            $subDir = $request->input('sub_dir', 'file');
            $subDir .= '/' . date('Ym');
            $result = $this->service->uploadFile($subDir);
            return Json::success('上传成功', $result->toArray());
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/file/fetch-image',
        description: '从远程URL下载图片并保存到服务器',
        summary: '远程图片拉取',
        tags: ['上传管理'],
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
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function fetchImage(Request $request): \support\Response
    {
        try {
            $url = $request->input('url', '');
            if (empty($url)) {
                throw new AdminException('图片URL不能为空');
            }
            $result = $this->service->fetchImage($url);
            return Json::success('拉取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/file/base64-image',
        description: '上传Base64编码的图片',
        summary: 'Base64图片上传',
        tags: ['上传管理'],
    )]
    #[RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'base64',
                    description: 'Base64编码的图片数据',
                    type: 'string',
                    example: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...'
                ),
            ]
        )
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function base64Image(Request $request): \support\Response
    {
        try {
            $base64 = $request->input('base64', '');
            if (empty($base64)) {
                throw new AdminException('Base64数据不能为空');
            }
            $result = $this->service->uploadBase64Image($base64);
            return Json::success('上传成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}