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
namespace app\adminapi\schema\response\system;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '文件上传响应模型',
    description: '文件上传/下载接口的返回数据结构'
)]
class UploadResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '文件ID',
        type: 'string',
        example: '234982712740941824'
    )]
    public string $id;

    #[OA\Property(
        property: 'url',
        description: '访问URL',
        type: 'string',
        example: 'http://43.138.153.216:8899/upload/42f86b9b36794fb9e380917c251f6d81.png'
    )]
    public string $url;

    #[OA\Property(
        property: 'size',
        description: '文件大小(字节)',
        type: 'integer',
        example: 6308
    )]
    public int $size;

    #[OA\Property(
        property: 'size_info',
        description: '文件大小格式化显示',
        type: 'string',
        example: '6.16 KB'
    )]
    public string $size_info;

    #[OA\Property(
        property: 'hash',
        description: '文件哈希值',
        type: 'string',
        example: '42f86b9b36794fb9e380917c251f6d81'
    )]
    public string $hash;

    #[OA\Property(
        property: 'filename',
        description: '存储文件名',
        type: 'string',
        example: '42f86b9b36794fb9e380917c251f6d81.png'
    )]
    public string $filename;

    #[OA\Property(
        property: 'original_filename',
        description: '原始文件名',
        type: 'string',
        example: 'logo.png'
    )]
    public string $original_filename;

    #[OA\Property(
        property: 'base_path',
        description: '基础存储路径',
        type: 'string',
        example: '/upload/42f86b9b36794fb9e380917c251f6d81.png'
    )]
    public string $base_path;

    #[OA\Property(
        property: 'path',
        description: '服务器存储路径',
        type: 'string',
        example: '/www/wwwroot/playground/madong-admin-saas/server/public/upload/42f86b9b36794fb9e380917c251f6d81.png'
    )]
    public string $path;

    #[OA\Property(
        property: 'ext',
        description: '文件扩展名',
        type: 'string',
        example: 'png'
    )]
    public string $ext;

    #[OA\Property(
        property: 'content_type',
        description: '文件MIME类型',
        type: 'string',
        example: 'image/png'
    )]
    public string $content_type;

    #[OA\Property(
        property: 'platform',
        description: '存储平台',
        type: 'string',
        example: 'local'
    )]
    public string $platform;

    #[OA\Property(
        property: 'th_url',
        description: '缩略图URL',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $th_url = null;

    #[OA\Property(
        property: 'th_filename',
        description: '缩略图文件名',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $th_filename = null;

    #[OA\Property(
        property: 'th_size',
        description: '缩略图大小(字节)',
        type: 'integer',
        example: null,
        nullable: true
    )]
    public ?int $th_size = null;

    #[OA\Property(
        property: 'th_size_info',
        description: '缩略图大小格式化显示',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $th_size_info = null;

    #[OA\Property(
        property: 'th_content_type',
        description: '缩略图MIME类型',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $th_content_type = null;

    #[OA\Property(
        property: 'object_id',
        description: '关联对象ID',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $object_id = null;

    #[OA\Property(
        property: 'object_type',
        description: '关联对象类型',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $object_type = null;

    #[OA\Property(
        property: 'attr',
        description: '额外属性',
        type: 'object',
        example: null,
        nullable: true
    )]
    public ?object $attr = null;

    #[OA\Property(
        property: 'created_at',
        description: '上传时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-10T02:17:24.000000Z'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-10T02:17:24.000000Z'
    )]
    public string $updated_at;

    #[OA\Property(
        property: 'created_by',
        description: '创建人ID',
        type: 'integer',
        example: 2
    )]
    public int $created_by;

    #[OA\Property(
        property: 'updated_by',
        description: '更新人ID',
        type: 'integer',
        example: null,
        nullable: true
    )]
    public ?int $updated_by = null;

    #[OA\Property(
        property: 'created_date',
        description: '创建日期',
        type: 'string',
        example: '2025-10-10 10:17:24'
    )]
    public string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新日期',
        type: 'string',
        example: '2025-10-10 10:17:24'
    )]
    public string $updated_date;

    #[OA\Property(
        property: 'createds',
        description: '创建人详情',
        properties: [
            new OA\Property(property: 'id', type: 'string', example: '2'),
            new OA\Property(property: 'created_name', type: 'string', example: '超级管理员'),
            new OA\Property(property: 'created_date', type: 'string', nullable: true, example: null),
            new OA\Property(property: 'updated_date', type: 'string', nullable: true, example: null)
        ],
        type: 'object'
    )]
    public object $createds;

    #[OA\Property(
        property: 'updateds',
        description: '更新人详情',
        type: 'object',
        example: null,
        nullable: true
    )]
    public ?object $updateds = null;
}
