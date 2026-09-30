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

use app\dao\site\AdvDao;
use core\foundation\base\BaseService;
use support\Redis;

/**
 * 广告服务
 */
class AdvertisementService extends BaseService
{
    public function __construct(AdvDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取广告列表
     *
     * @return array
     * @throws \Exception
     */
    public function getAds(): array
    {
        $map = [
            ['enabled', 'eq', 1],
        ];

        $advs = $this->dao->selectList($map, ['*'], 0, 0, 'sort asc, id asc');

        // 过滤时间范围内有效的广告
        $now = time();
        return $advs->filter(function($adv) use ($now) {
            $startTime = strtotime($adv->getOriginal('start_time') ?? 0);
            $endTime = strtotime($adv->getOriginal('end_time') ?? 0);
            // 如果开始时间为0或小于等于当前时间，且结束时间为0或大于等于当前时间
            if ($startTime === false) $startTime = 0;
            if ($endTime === false) $endTime = 0;
            return ($startTime == 0 || $startTime <= $now) && ($endTime == 0 || $endTime >= $now);
        })->toArray();
    }

    /**
     * 获取广告位信息（兼容旧版本，已废弃）
     *
     * @param array $_params 参数
     * @return array
     */
    public function getAdvertisementInfo(array $_params): array
    {
        return $this->getAds();
    }

    /**
     * 清除广告缓存
     *
     * @return void
     */
    public function clearAdCache(): void
    {
        // 清除所有广告缓存
        $keys = Redis::keys('web_ads_*');
        if (!empty($keys)) {
            Redis::del($keys);
        }
    }
}
