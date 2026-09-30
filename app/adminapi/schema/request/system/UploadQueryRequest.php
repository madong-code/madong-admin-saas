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


use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '文件上传列表查询请求',
    description: '文件上传列表接口的查询过滤参数'
)]
class UploadQueryRequest extends BaseQueryRequest
{



    #[OA\Property(
        property: 'filename',
        description: '存储文件名',
        type: 'string',
        example: '42f86b9b36794fb9e380917c251f6d81.png'
    )]
    public ?string $filename = null;

    #[OA\Property(
        property: 'original_filename',
        description: '原始文件名',
        type: 'string',
        example: 'logo.png'
    )]
    public ?string $original_filename = null;

    #[OA\Property(
        property: 'ext',
        description: '文件扩展名',
        type: 'string',
        example: 'png'
    )]
    public ?string $ext = null;

    #[OA\Property(
        property: 'platform',
        description: '存储平台',
        type: 'string',
        enum: ['local', 'oss', 'cos', 's3'],
        example: 'local'
    )]
    public ?string $platform = null;

    #[OA\Property(
        property: 'start_time',
        description: '上传开始时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-01T00:00:00Z'
    )]
    public ?string $start_time = null;

    #[OA\Property(
        property: 'end_time',
        description: '上传结束时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-31T23:59:59Z'
    )]
    public ?string $end_time = null;

}
