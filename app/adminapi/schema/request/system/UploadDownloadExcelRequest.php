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

#[OA\Schema(
    title: 'Excel下载请求', description: '下载导出的Excel文件')]
class UploadDownloadExcelRequest extends BaseRequestDTO
{
    // 第一层校验：仅接受 exports 目录下的 .xlsx 相对路径；
    // 路径白名单与 realpath 边界由 core\io\excel\ExportPathGuard 兜底，不可依赖本规则防穿越。
    #[OA\Property(
        description: '文件路径',
        type: 'string',
        example: '/exports/report.xlsx'
    )]
    #[ValidationRules(rules: 'required|string|max:255|regex:/^[\/]?exports\/[^\/\\\\]+\.xlsx$/i')]
    public string $file_path;

    #[OA\Property(
        description: '下载文件名',
        type: 'string',
        example: '数据报表.xlsx'
    )]
    #[ValidationRules(rules: 'nullable|string|max:100')]
    public ?string $file = null;
}
