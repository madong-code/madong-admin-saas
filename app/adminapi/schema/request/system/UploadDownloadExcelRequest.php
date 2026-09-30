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
    #[OA\Property(
        description: '文件路径',
        type: 'string',
        example: 'export/20240512/report.xlsx'
    )]
    #[ValidationRules(rules: 'required|string|max:255')]
    public string $file_path;

    #[OA\Property(
        description: '下载文件名',
        type: 'string',
        example: '数据报表.xlsx'
    )]
    #[ValidationRules(rules: 'string|max:100|nullable')]
    public ?string $file = null;
}
