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


namespace app\adminapi\schema\request\system;


use madong\swagger\schema\BaseRequestDTO;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(title: '网络图片下载请求', description: '通过URL下载网络图片')]
class UploadDownloadNetworkImageRequest extends BaseRequestDTO
{
    #[OA\Property(
        description: '图片URL',
        type: 'string',
        format: 'uri',
        example: 'https://example.com/image.jpg'
    )]
    #[ValidationRules(rules: 'required|url|max:512')]
    public string $url;
}
