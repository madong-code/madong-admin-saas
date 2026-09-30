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

namespace app\schema\request\plugin;

use madong\swagger\schema\BaseRequestDTO;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '租户插件授权请求',
    description: '批量设置租户插件的授权状态'
)]
class PluginTenantAuthRequest extends BaseRequestDTO
{
    #[OA\Property(
        property: 'tenant_id',
        description: '租户ID',
        type: 'string',
        example: '246996721795072000'
    )]
    #[ValidationRules(rules: 'required|string')]
    public string $tenant_id;

    #[OA\Property(
        property: 'plugin_keys',
        description: '插件标识列表',
        type: 'array',
        items: new OA\Items(type: 'string')
    )]
    #[ValidationRules(rules: 'required|array')]
    public array $plugin_keys;

    #[OA\Property(
        property: 'auth_status',
        description: '授权状态: none/authorized/trial',
        type: 'string',
        enum: ['none', 'authorized', 'trial'],
        example: 'authorized'
    )]
    #[ValidationRules(rules: 'in:none,authorized,trial')]
    public string $auth_status = 'authorized';
}
