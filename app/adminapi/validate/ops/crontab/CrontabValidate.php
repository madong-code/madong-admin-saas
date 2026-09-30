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

namespace app\adminapi\validate\ops\crontab;

use core\foundation\base\BaseValidate;

class CrontabValidate extends BaseValidate
{
    /**
     * 定义验证规则
     */
    protected array $rules = [
        'data'    => 'required',
        'title'   => 'required',
        'type'    => 'required',
        'rule'    => 'required',
        'target'  => 'required',
        'enabled' => 'required',
    ];

    /**
     * 定义错误信息
     */
    protected array $messages = [
        'data.required'       => '唯一标识ID不能为空',
        'title.required'      => '任务名称必须填写',
//        'title.task_enabled' => '定时任务未开启,.env文件APP_TASK_ENABLED=true',
        'type.required'       => '任务类型必须填写',
        'rule.required'       => '任务规则必须填写',
        'target.required'     => '调用目标必须填写',
        'enabled.required'    => '任务状态必须填写',
    ];

    /**
     * 验证是否启动定时任务
     *
     * @param       $value
     * @param       $rule
     * @param array $data
     *
     * @return bool
     */
    protected function task_enabled($value, $rule, array $data = []): bool
    {
        return config('core.infrastructure.scheduler.enable', false);
    }

    /**
     * 定义场景
     */
    protected array $scenes = [
        'start'   => [
            'data',
        ],
        'resume'  => [
            'data',
        ],
        'pause'   => [
            'data',
        ],
        'execute' => [
            'data',
        ],
        'destroy' => [
            'data',
        ],
        'store'   => [
            'title',
            'type',
            'target',
            'enabled',
        ],
        'update'  => [
            'title',
            'type',
            'rule',
            'target',
            'enabled',
        ],
    ];
}
