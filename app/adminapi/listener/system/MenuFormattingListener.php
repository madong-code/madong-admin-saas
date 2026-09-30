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
namespace app\adminapi\listener\system;

use app\adminapi\event\system\MenuFormattingEvent;
use core\foundation\base\BaseListener;
use core\foundation\tool\MenuVariableParser;
use madong\helper\Tree;

/**
 * 菜单格式化监听器
 */
class MenuFormattingListener extends BaseListener
{
    protected function process($event): void
    {
        switch ($event->formatType) {
            case 'vben':
                $event->result = $this->formatForVben($event->data);
                break;
            case 'art':
                $event->result = $this->formatForArt($event->data);
                break;
            case 'tree':
            case 'default':
                $event->result = $this->formatForTree($event->data);
                break;
            default:
                $event->result = [];
        }
    }

    /**
     * 基础树形结构格式化（用于Vben Ui）
     */
    private function formatForVben(null|\Illuminate\Support\Collection $data): array
    {
        if (empty($data)) {
            return [];
        }

        $self = $this;

        $filteredData = $data->filter(function ($item) {
            return !in_array($item->type, [3, 4]);
        })->map(function ($item) use ($self) {
            // 构建基础菜单项（前端 MenuRecordRaw 接口期望的字段）
            $result = [
                'id'        => $item->id,
                'pid'       => $item->pid,
                'path'      => $item->path,
                'name'      => $item->code ?: $self->menuNameFromPath($item->path),
                'component' => $item->component,
                'redirect'  => $item->redirect ?? '',

                // 前端直接从根级别读取的字段
                'icon'          => $item->icon ?? null,
                'order'         => $item->sort ?? 0,
                'activeIcon'    => null,           // 预留字段：激活状态图标
                'disabled'      => false,          // 预留字段：是否禁用
                'query'         => null,           // 预留字段：路由参数
            ];

            // 徽章配置来自 menu.variable 根级的 badge 域（未配置则不下发任何徽标字段）
            // 输出 snake_case，位置在根级，前端组件直接读取
            $badge = MenuVariableParser::badge($item->variable ?? '');
            if ($badge !== null) {
                $result['badge']          = $badge['badge'];
                $result['badge_type']     = $badge['badge_type'];
                $result['badge_variants'] = $badge['badge_variants'];
            }

            // meta 字段（用于路由配置）
            $result['meta'] = [
                // 基础信息
                'title'           => $item->title,
                'icon'            => $item->icon ?? null,
                'order'           => $item->sort ?? 0,

                // 显示控制
                'hideInMenu'      => !($item->is_show ?? true),
                'hideInTab'       => !($item->is_tab ?? true),         // is_tab=0 → 隐藏标签页
                'affixTab'        => $item->is_affix ?? false, // 固定标签页

                // 权限控制
                'authority'       => MenuVariableParser::authority($item->variable ?? ''),

                // 功能控制
                'keepAlive'       => $item->is_cache ?? false, // 缓存

                // 链接相关
                'link'            => $item->type == 6 ? $item->link_url : null, // 外链(6)使用link_url
                'iframeSrc'       => $item->is_frame ? $item->link_url : null, // iframe链接
                'openInNewWindow' => $item->open_type === '_blank',            // 修复：移除错误的三元运算符
            ];

            return $result;
        })->toArray();
        $tree         = new Tree($filteredData);
        return $tree->getTree();
    }

    /**
     * 基础树形结构格式化（用于Art Ui）
     */
    private function formatForArt(null|\Illuminate\Support\Collection $data): array
    {
        if (empty($data)) {
            //可以输出默认主页菜单
            return [];
        }

        $items     = $data->all();
        $grouped   = collect($items)->groupBy('pid')->all();
        $buildMenu = function ($parentId = 0) use (&$buildMenu, $grouped) {
            $menuItems    = [];
            $currentItems = $grouped[$parentId] ?? collect();


            foreach ($currentItems as $item) {
                // 跳过按钮(3)和接口(4)类型，它们不直接作为菜单项
                if (in_array($item->type, [3, 4])) {
                    continue;
                }

                // 提取当前菜单项下的接口(4)和按钮(3)作为authList
                $authItems = $grouped[$item->id] ?? collect();
                $authList  = $authItems->filter(function ($child) {
                    return in_array($child->type, [3, 4]);
                })->map(function ($authItem) {
                    return [
                        'title'    => $authItem->title,//可以优化多语言
                        'authMark' => $authItem->code ?? $authItem->path,
                    ];
                })->all();
                $meta      = [
                    'title'        => $item->title,
                    'icon'         => $item->icon ?? null,
                    'isHide'       => !$item->is_show ?? false, // （0=隐藏，1=显示）
                    'isHideTab'    => $item->is_affix ?? false, // 否隐藏标签页
                    'link'         => in_array($item->type, [5, 6]) ? $item->link_url : null, //外链使用link_url字段
                    'isIframe'     => $item->is_frame ?? false, // 修正：is_frame对应是否为iframe
                    'keepAlive'    => $item->is_cache ?? false, // 修正：is_cache对应是否缓存
                    'authList'     => $authList,
                    'isFirstLevel' => $parentId === 0, // 顶级菜单标记为一级菜单
                    'roles'        => $item->variable ? explode(',', $item->variable) : [], // 假设variable存储角色列表（逗号分隔）
                ];

                // 插件菜单添加 module 字段，值为 app 字段
                if (str_starts_with($item->source ?? '', 'plugin:') && !empty($item->app)) {
                    $meta['module'] = $item->app;
                }

                $component = $item->component;
                $path      = $item->path;

                if ($item->type === 1 && $parentId === 0) {
                    $component = '/layout';
                    $path      = $path ?: '#'; // 目录默认路径为#
                }

                $children    = $buildMenu($item->id);
                $menuItems[] = [
                    'id'        => $item->id,
                    'path'      => $path,
                    'name'      => $item->code,
                    'component' => $component,
                    'meta'      => $meta,
                    'children'  => $children,
                ];
            }
            return $menuItems;
        };
        return $buildMenu();
    }

    /**
     * 基础树形结构格式化（用于权限设置等场景）
     */
    private function formatForTree(null|\Illuminate\Support\Collection $data): array
    {
        if (empty($data)) {
            return [];
        }

        $tree = new Tree($data->toArray());
        return $tree->getTree();
    }

    /**
     * 从路由路径生成唯一的英文 route name（当 menu code 为空时的 fallback）
     * 例如: "/system/user" → "SystemUser", "/" → "Root"
     */
    private function menuNameFromPath(string $path): string
    {
        $path = trim($path, '/');
        if (empty($path)) {
            return 'Root';
        }
        $parts = array_map(fn($part) => ucfirst($part), explode('/', $path));
        return implode('', $parts);
    }
}
