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
namespace app\adminapi\validate\member;

use app\model\member\MemberLevel;
use core\foundation\base\BaseValidate;
use support\Request;

/**
 * 会员等级验证器
 */
class MemberLevelValidate extends BaseValidate
{
    /**
     * 验证规则
     *
     * 注意：不使用 Rule::unique 是因为它可能不经过 BaseModel::getConnectionName()
     * 动态连接切换。改用自定义验证方法通过 Eloquent 模型查询，确保库隔离模式下
     * 正确切换到租户专属数据库连接。
     */
    // public function rules(): array
    // {
    //     return [
    //         'name'        => ['required', 'max:50', 'unique_level_name'],
    //         'level'       => ['required', 'integer', 'min:1', 'unique_level'],
    //         'min_points'  => 'integer|min:0',
    //         'max_points'  => 'integer|min:0',
    //         'discount'    => 'numeric|between:0,1',
    //         'color'       => 'max:20',
    //         'description' => 'max:255',
    //         'enabled'      => 'in:0,1',
    //     ];
    // }


    protected array $rules=[
            'name'        => ['required', 'max:50', 'unique_level_name'],
            'level'       => ['required', 'integer', 'min:1', 'unique_level'],
            'min_points'  => 'integer|min:0',
            'max_points'  => 'integer|min:0',
            'discount'    => ['numeric', 'discount_between:0,1'],
            'color'       => 'max:20',
            'description' => 'max:255',
            'enabled'      => 'in:0,1',
    ];

    /**
     * 验证消息
     */
    protected array $messages = [
        'name.required'            => '等级名称不能为空',
        'name.max'                 => '等级名称不能超过50个字符',
        'name.unique_level_name'   => '等级名称已存在',
        'level.required'           => '等级值不能为空',
        'level.integer'            => '等级值必须为整数',
        'level.min'                => '等级值不能小于1',
        'level.unique_level'       => '等级值已存在',
        'min_points.integer'     => '最低积分必须为整数',
        'min_points.min'         => '最低积分不能小于0',
        'max_points.integer'     => '最高积分必须为整数',
        'max_points.min'         => '最高积分不能小于0',
        'discount.number'                 => '折扣率必须为数字',
        'discount.discount_between'       => '折扣率必须在0到1之间',
        'color.max'              => '等级颜色不能超过20个字符',
        'description.max'        => '等级描述不能超过255个字符',
        'enabled.in'             => '状态值不正确',
    ];

    /**
     * 自定义验证：等级名称唯一
     *
     * 通过 Eloquent 模型查询，确保走 BaseModel::getConnectionName() 动态连接切换。
     */
    public function unique_level_name(mixed $value, array $params, array $data): bool
    {
        /** @var Request $request */
        $request = request();
        $id = $request->route->param('id');

        $query = MemberLevel::query()->where('name', $value);
        if ($id) {
            $query->where('id', '<>', $id);
        }
        return !$query->exists();
    }

    /**
     * 自定义验证：等级值唯一
     *
     * 通过 Eloquent 模型查询，确保走 BaseModel::getConnectionName() 动态连接切换。
     */
    public function unique_level(mixed $value, array $params, array $data): bool
    {
        /** @var Request $request */
        $request = request();
        $id = $request->route->param('id');

        $query = MemberLevel::query()->where('level', $value);
        if ($id) {
            $query->where('id', '<>', $id);
        }
        return !$query->exists();
    }

    /**
     * 自定义验证：折扣率范围
     *
     * 手动比较数值范围，避免走 between 规则内部 BigNumber::of(float) 触发
     * E_USER_DEPRECATED 被全局 error handler 转为异常的问题。
     */
    public function discount_between(mixed $value, array $params, array $data): bool
    {
        $value = (float) $value;
        $min = (float) ($params[0] ?? 0);
        $max = (float) ($params[1] ?? 1);
        return $value >= $min && $value <= $max;
    }

    /**
     * 验证场景
     */
    protected array $scenes = [
        'store'  => [
            'name',
            'level',
            'min_points',
            'max_points',
            'discount',
            'color',
            'description',
            'enabled',
        ],
        'update' => [
            'name',
            'level',
            'min_points',
            'max_points',
            'discount',
            'color',
            'description',
            'enabled'
        ],
    ];

}