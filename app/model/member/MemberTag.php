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
namespace app\model\member;

use app\model\web\Menu;
use core\foundation\base\BaseModel;
use app\enum\common\EnabledStatus;

/**
 * 会员标签模型
 */
class MemberTag extends BaseModel
{
    /**
     * 数据表名称
     */
    protected $table = 'member_tag';

    /**
     * 数据表主键
     */
    protected $primaryKey = 'id';

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'id',
        'name',
        'color',
        'description',
        'sort',
        'enabled',
        'created_at',
        'updated_at',
        'tenant_id',
    ];


    protected $casts = [
        'id'        => 'string',
        'tenant_id' => 'string',
    ];

    /**
     * 追加字段
     */
    protected $appends = [
        'enabled_text',
    ];

    /**
     * 获取状态文本
     */
    public function getEnabledTextAttribute(): string
    {
        return EnabledStatus::tryFrom($this->enabled)?->label() ?? '未知';
    }

    /**
     * 关联会员
     */
    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_tag_relation', 'tag_id', 'member_id');
    }

    /**
     * 关联菜单权限
     */
    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Menu::class, MemberTagPermission::class, 'tag_id', 'menu_id')->orderBy('sort');
    }
}