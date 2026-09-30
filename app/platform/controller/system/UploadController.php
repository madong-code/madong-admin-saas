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
use core\foundation\tool\Json;
use core\io\upload\UploadFile;
use core\io\upload\UploadScene;
use madong\swagger\annotation\response\SimpleResponse;
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
}
