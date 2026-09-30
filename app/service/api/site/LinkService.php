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

namespace app\service\api\site;

use app\dao\site\LinkDao;
use core\foundation\base\BaseService;

/**
 * 友情链接服务
 */
class LinkService extends BaseService
{
    public function __construct(LinkDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据类型获取链接
     *
     * @param string $type 链接类型
     *
     * @return array|null
     * @throws \Exception
     */
    public function getLinksByType(string $type): ?array
    {
        $map = [
            ['category', 'eq', $type],
            ['enabled', 'eq', 1],
        ];
        return $this->dao->selectList($map, ['*'], 0, 0, 'sort asc')->toArray();
    }

    /**
     * 获取所有链接
     *
     * @return array
     * @throws \Exception
     */
    public function getAllLinks(): array
    {
        $map = [
            ['enabled', '=', 1],
        ];

        return $this->dao->selectList($map, ['*'], 0, 0, 'category asc, sort asc')->toArray();
    }
}
