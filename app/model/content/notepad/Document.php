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

class Document extends BaseModel
{
    protected $table = 'sys_notepad_document';

    protected $fillable = [
        'id',
        'folder_id',
        'user_id',
        'tenant_id',
        'title',
        'content',
        'content_html',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'        => 'string',
        'folder_id' => 'string',
        'user_id'   => 'string',
        'tenant_id' => 'string',
    ];

    public function folder()
    {
        return $this->belongsTo(Folder::class, 'folder_id', 'id');
    }
}
