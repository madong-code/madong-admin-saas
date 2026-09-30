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


namespace app\adminapi\schema\request\ops\crontab;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '定时任务执行请求',
    description: '定时任务手动执行接口参数'
)]
class CrontabExecuteRequest extends BaseResponseDTO
{
    #[OA\Property(
        description: '任务ID',
        type: 'string',
        example: 1
    )]
    #[ValidationRules(rules: 'required|integer|min:1')]
    public int|string $data;
}
