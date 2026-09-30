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

namespace app\dao\content\message;

use app\model\content\message\Message;
use core\foundation\base\BaseDao;

class MessageDao extends BaseDao
{

    protected function setModel(): string
    {
        return Message::class;
    }

    public function get($id, ?array $field = null, ?array $with = [], string $order = '', ?array $withoutScopes = null): ?Message
    {
        return parent::get($id, $field, ['sender'], $order, $withoutScopes);
    }

    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?\Illuminate\Database\Eloquent\Collection
    {
        return parent::selectList($where, $field, $page, $limit, 'created_at desc', ['sender'], $search, $withoutScopes);
    }
}
