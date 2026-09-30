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

namespace app\dao\ops\crontab;

use app\model\ops\crontab\Crontab;
use core\foundation\base\BaseDao;

class CrontabDao extends BaseDao
{

    protected function setModel(): string
    {
        return Crontab::class;
    }

    /**
     * 获取列表
     *
     * @param array      $where
     * @param string     $field
     * @param int        $page
     * @param int        $limit
     * @param string     $order
     * @param array      $with
     * @param bool       $search
     * @param array|null $withoutScopes
     *
     * @return \Illuminate\Database\Eloquent\Collection|null
     * @throws \Exception
     */
    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?\Illuminate\Database\Eloquent\Collection
    {
        // 透传 $with，确保调用方（如平台 service）注入的关联（tenant 等）生效
        $result = parent::selectList($where, $field, $page, $limit, $order, $with, $search,$withoutScopes);

        $systemCrontabLogDao = new CrontabLogDao();
        if (!empty($result)) {
            foreach ($result as $item) {
                $item->rule_name .= '';
                $item->logs      = $systemCrontabLogDao->getModel()
                    ->where(['crontab_id' => $item->id])
                    ->orderBy('created_at', 'desc')
                    ->first();
            }
        }
        return $result;
    }

}
