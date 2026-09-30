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
namespace app\adminapi\schema\request\content\notice;


use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '公告表单',
    description: '公告创建和编辑接口共用的表单请求参数'
)]
class NoticeFormRequest extends BaseFormRequest
{
    #[OA\Property(
        property: 'type',
        description: '公告类型',
        type: 'string',
        enum: ['notice', 'announcement', 'alert'],
        example: 'notice',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|in:notice,announcement,alert')]
    public string $type;

    #[OA\Property(
        property: 'title',
        description: '公告标题',
        type: 'string',
        example: '系统升级通知',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:100')]
    public string $title;

    #[OA\Property(
        property: 'content',
        description: '公告内容',
        type: 'string',
        example: '系统将于2025-10-20进行升级维护',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string')]
    public string $content;

    #[OA\Property(
        property: 'sort',
        description: '排序号',
        type: 'integer',
        example: 10,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|integer|min:0|max:1000')]
    public int $sort;

    #[OA\Property(
        property: 'enabled',
        description: '是否启用（1启用 0禁用）',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|integer|in:0,1')]
    public int $enabled;

    #[OA\Property(
        property: 'remark',
        description: '备注',
        type: 'string',
        example: '重要公告',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $remark = null;
}
