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
namespace app\api\controller;

use core\foundation\base\BaseController;
use core\foundation\tool\Json;
use madong\helper\Tree;

/**
 * 基类控制器继承的类
 *
 * @author Mr.April
 * @since  1.0
 */
class Base extends BaseController
{

    /**
     * 初始化
     */
    protected function initialize(): void
    {

    }

    /**
     * 格式化树
     *
     * @param $items
     *
     * @return \support\Response
     */
    protected function formatTree($items): \support\Response
    {
        $format_items = [];
        foreach ($items as $item) {
            $format_items[] = [
                'name'  => $item->title ?? $item->name ?? $item->id,
                'value' => (string)$item->id,
                'id'    => $item->id,
                'pid'   => $item->pid,
            ];
        }
        $tree = new Tree($format_items);
        return Json::success('ok', $tree->getTree());
    }

    /**
     * 格式化表格树
     *
     * @param $data
     * @param $total
     *
     * @return \support\Response
     */
    protected function formatTableTree($data, $total): \support\Response
    {
        $tree  = new Tree($data->toArray());
        $items = $tree->getTree();
        return Json::success('ok', $items);
    }

    /**
     * 格式化下拉列表
     *
     * @param $items
     *
     * @return \support\Response
     */
    protected function formatSelect($items): \support\Response
    {
        $formatted_items = [];
        foreach ($items as $item) {
            $formatted_items[] = [
                'label' => $item->title ?? $item->name ?? $item->real_name ?? $item->id,
                'value' => $item->id,
            ];
        }
        return Json::success('ok', $formatted_items);
    }

    /**
     * 通用格式化
     *
     * @param $items
     * @param $total
     *
     * @return \support\Response
     */
    protected function formatNormal($items, $total): \support\Response
    {
        return Json::success('ok', compact('items', 'total'));
    }
}
