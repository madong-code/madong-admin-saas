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

namespace app\service\platform\plugin;

use app\service\admin\plugin\PluginDevelopService as AdminPluginDevelopService;
use core\foundation\base\BaseService;
use support\Container;

/**
 * 平台端插件开发服务 - 委托 admin PluginDevelopService
 */
class PluginDevelopService extends BaseService
{
    private AdminPluginDevelopService $adminService;

    public function __construct()
    {
        $this->adminService = Container::make(AdminPluginDevelopService::class);
    }

    public function getList(int $page, int $limit, string $keyword = ''): array
    {
        return $this->adminService->getList(['page' => $page, 'limit' => $limit, 'keyword' => $keyword]);
    }

    public function show(int|string $id): mixed
    {
        return $this->adminService->show($id);
    }

    public function store(array $data): mixed
    {
        return $this->adminService->store($data);
    }

    public function update(int|string $id, array $data): mixed
    {
        return $this->adminService->update($id, $data);
    }

    public function destroyPlugin(int|string $id): void
    {
        $this->adminService->destroyPlugin($id);
    }

    public function buildPlugin(int|string $id): array
    {
        return $this->adminService->buildPlugin($id);
    }
}
