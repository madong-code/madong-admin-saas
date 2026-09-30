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
 * Official Website: https://madong.tech
 */

namespace app\service\core;

use core\foundation\base\BaseValidate;

/**
 * 验证服务类
 *
 * @author Mr.April
 * @since  1.0
 */
class ValidationService
{
    /**
     * 验证请求数据
     *
     * @param string|BaseValidate $validator  验证器类名或验证器实例
     * @param array               $data       待验证数据
     * @param string|null         $scene      验证场景
     * @param array               $extend     扩展参数，支持：
     *                                        - returnAllData: bool 是否返回全部数据（包括未验证字段）
     *                                        - message: array 自定义错误消息
     *
     * @return array 验证通过的数据
     * @throws \core\exception\handler\ValidationException
     */
    public function validate(string|BaseValidate $validator, array $data = [], ?string $scene = null, array $extend = []): array
    {
        // 如果传入的是验证器类名，则实例化它
        if (is_string($validator)) {
            $validatorInstance = new $validator($scene);
        } else {
            // 如果传入的是验证器实例，检查是否需要设置场景
            $validatorInstance = $validator;
            if ($scene) {
                $validatorInstance->scene($scene);
            }
        }

        // 从扩展参数中获取配置
        $isFull  = $extend['full'] ?? false;
        $message = $extend['message'] ?? [];

        // 判断验证器是否有新的validate方法，如果有则使用新方法
        if (method_exists($validatorInstance, 'validate')) {
            return $validatorInstance->validate($data, [], $message, $isFull);
        }

        // 兼容旧版：使用check方法
        $validatorInstance->check($data);

        // 如果需要返回全部数据且有传入数据
        if ($isFull && !empty($data)) {
            return array_merge($data, $validatorInstance->getData());
        }

        // 返回验证通过的数据
        return $validatorInstance->getData();
    }
}
