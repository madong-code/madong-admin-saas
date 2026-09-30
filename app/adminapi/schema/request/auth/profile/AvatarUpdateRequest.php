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
namespace app\adminapi\schema\request\auth\profile;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '头像上传请求',
    description: '上传头像文件（multipart/form-data）'
)]
final class AvatarUpdateRequest
{
    #[OA\Property(
        property: 'file',
        description: '头像文件，支持 jpg/png/gif/webp 格式',
        type: 'string',
        format: 'binary',
    )]
    public string $file;
}
