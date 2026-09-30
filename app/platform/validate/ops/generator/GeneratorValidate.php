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
namespace app\platform\validate\ops\generator;

use core\foundation\base\BaseValidate;

/**
 * 代码生成器
 * Class GeneratorValidate
 *
 * @package app\platform\validate\generator
 */
class GeneratorValidate extends BaseValidate
{
    protected array $rules = [
        'table_name'              => 'required|max:64',
        'table_content'           => 'required|max:64',
        'name'                    => 'required|max:64',
        'basic.table_name'        => 'required|max:64',
        'basic.table_content'     => 'max:64',
    ];

    protected array $messages = [
        'table_name.required'              => 'validate_generator.table_name_require',
        'table_name.max'                  => 'validate_generator.table_name_max',
        'table_content.required'           => 'validate_generator.table_content_require',
        'table_content.max'               => 'validate_generator.table_content_max',
        'name.required'                    => 'validate_generator.table_name_require',
        'name.max'                        => 'validate_generator.table_name_max',
        'basic.table_name.required'        => 'validate_generator.table_name_require',
        'basic.table_name.max'            => 'validate_generator.table_name_max',
        'basic.table_content.required'     => 'validate_generator.table_content_require',
        'basic.table_content.max'         => 'validate_generator.table_content_max',
    ];

    protected array $scenes = [
        'store'  => ['name'],
        'update' => ['basic.table_name', 'basic.table_content', 'basic.class_name', 'basic.module_name', 'columns'],
    ];
}
