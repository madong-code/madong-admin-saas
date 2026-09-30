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
namespace app\model\content\notepad;

use core\foundation\base\BaseModel;

class Folder extends BaseModel
{
    protected $table = 'sys_notepad_folder';

    protected $fillable = [
        'id',
        'pid',
        'user_id',
        'tenant_id',
        'name',
        'icon',
        'sort',
        'doc_count',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'        => 'string',
        'pid'       => 'string',
        'user_id'   => 'string',
        'tenant_id' => 'string',
        'sort'      => 'integer',
        'doc_count' => 'integer',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class, 'folder_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'pid', 'id');
    }
}
