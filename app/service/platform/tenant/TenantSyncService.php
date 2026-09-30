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
namespace app\service\platform\tenant;

use app\dao\tenant\SubscriptionPermissionDao;
use app\dao\tenant\TenantSubscriptionDao;
use app\model\tenant\ConfigTemplate;
use app\model\tenant\MenuTemplate;
use app\model\sync\SyncConfig;
use app\model\sync\SyncMenu;
use app\model\sync\SyncWebMenu;
use app\model\tenant\WebMenuTemplate;
use core\foundation\base\BaseService;
use core\business\tenant\SyncConnection;
use Illuminate\Database\Schema\Blueprint;
use support\Container;
use support\DB;

/**
 * 租户数据同步服务
 *
 * 负责将平台配置（菜单、字典、配置等）同步到各个租户。
 * 支持 field（字段隔离）和 database（库隔离）两种模式。
 */
class TenantSyncService extends BaseService
{
    /**
     * 同步租户全量数据
     */
    public function syncData(int $tenantId, string $mode): array
    {
        $permissionIds = $this->getAuthorizedPermissionIds($tenantId);

        $menuResult = $this->syncMenuData($tenantId, $permissionIds, $mode);
        $configResult = $this->syncConfigData($tenantId, $mode);
        $webMenuResult = $this->syncWebMenuData($tenantId, $mode);

        return [
            'tenant_id'      => $tenantId,
            'database_mode'  => $mode,
            'menu'           => $menuResult,
            'config'         => $configResult,
            'web_menu'       => $webMenuResult,
        ];
    }

    /**
     * 获取租户已授权的所有权限ID（多套餐去重）
     */
    public function getAuthorizedPermissionIds(int $tenantId): array
    {
        /** @var TenantSubscriptionDao $tsDao */
        $tsDao = Container::make(TenantSubscriptionDao::class);
        $subscriptionIds = $tsDao->getSubscriptionIdsByTenantId($tenantId);

        if (empty($subscriptionIds)) {
            return [];
        }

        /** @var SubscriptionPermissionDao $spDao */
        $spDao = Container::make(SubscriptionPermissionDao::class);
        $allIds = [];
        foreach ($subscriptionIds as $subId) {
            $ids = $spDao->getPermissionIdsBySubscriptionId((int)$subId);
            $allIds = array_merge($allIds, $ids);
        }

        return array_unique($allIds);
    }

    /**
     * 同步菜单数据：从模板菜单复制到租户菜单表
     */
    public function syncMenuData(int $tenantId, array $permissionIds, string $mode): array
    {
        if (empty($permissionIds)) {
            return ['count' => 0, 'message' => '无权限配置，跳过菜单同步'];
        }

        // ====== permissionIds 来自套餐授权，存储的是 saas_template_menu.id（模板ID）======
        // 直接用作 template_id，无需查 sys_menu 的映射（sys_menu 的 template_id 可能为空）
        $templateIds = array_map('intval', array_unique($permissionIds));

        if (empty($templateIds)) {
            return ['count' => 0, 'message' => '无有效权限模板，跳过菜单同步'];
        }

        // 1. 获取模板菜单记录
        $allTemplates = MenuTemplate::where('enabled', 1)
            ->orderBy('sort', 'asc')
            ->get()
            ->toArray();

        if (empty($allTemplates)) {
            return ['count' => 0, 'message' => '模板菜单为空'];
        }

        // 2. 收集权限ID的父链，确保树完整
        $neededIds = $this->collectParentIds($allTemplates, $templateIds);
        $templates = array_values(array_filter($allTemplates, fn($m) => in_array($m['id'], $neededIds)));

        if (empty($templates)) {
            return ['count' => 0];
        }

        // 3. 确定目标连接
        $conn = SyncConnection::getConnectionName($tenantId, $mode);

        // 3.1 确保目标库表结构包含所需的字段（兼容旧版迁移）
        $this->ensureSchemaColumns($conn, $mode, $tenantId);

        // 4. 加载租户已有菜单（按 template_id 索引），用于对比
        $existingQuery = SyncMenu::on($conn);
        if ($mode === 'field') {
            $existingQuery->where('tenant_id', $tenantId);
        }
        $existingMenus = $existingQuery->whereNotNull('template_id')->get()->keyBy('template_id');
        $existingTemplateIds = $existingMenus->keys()->toArray();

        $newTemplateIds = array_column($templates, 'id');
        $templateIdsToAdd = array_diff($newTemplateIds, $existingTemplateIds);
        $templateIdsToRemove = array_diff($existingTemplateIds, $newTemplateIds);
        $templateIdsToUpdate = array_intersect($newTemplateIds, $existingTemplateIds);

        // 5. 删除不再授权的菜单
        if (!empty($templateIdsToRemove)) {
            $deleteQuery = SyncMenu::on($conn)->whereIn('template_id', $templateIdsToRemove);
            if ($mode === 'field') {
                $deleteQuery->where('tenant_id', $tenantId);
            }
            $deleteQuery->delete();
        }

        // 6. 建立 template_id → tenant_menu_id 映射表（更新/新增共用）
        $templateIdMap = [];
        $affectedIds = [];
        $writableFields = array_diff(
            (new SyncMenu())->getFillable(),
            ['id', 'pid', 'level', 'template_id', 'tenant_id', 'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at']
        );

        // 6a. 更新已有菜单（ID 不变，RBAC 绑定不受影响）
        foreach ($templates as $item) {
            if (!in_array($item['id'], $templateIdsToUpdate)) continue;

            $existingMenu = $existingMenus->get($item['id']);
            if (!$existingMenu) continue;

            $updateData = array_intersect_key($item, array_flip($writableFields));
            $existingMenu->update($updateData);

            $templateIdMap[$item['id']] = $existingMenu->id;
            $affectedIds[] = $existingMenu->id;
        }

        // 6b. 新增菜单（之前没有的）
        foreach ($templates as $item) {
            if (!in_array($item['id'], $templateIdsToAdd)) continue;

            $oldId = $item['id'];
            $newItem = $item;
            $newItem['template_id'] = $oldId;
            $newItem['tenant_id'] = $tenantId;
            unset($newItem['id'], $newItem['created_at'], $newItem['created_by'],
                  $newItem['updated_at'], $newItem['updated_by'], $newItem['deleted_at']);

            $menu = SyncMenu::on($conn)->create($newItem);
            $templateIdMap[$oldId] = $menu->id;
            $affectedIds[] = $menu->id;
        }

        // 6c. 补充已有但无需更新的菜单到映射表（用于 parent_id、level 计算）
        foreach ($templates as $item) {
            if (isset($templateIdMap[$item['id']])) continue;
            $existingMenu = $existingMenus->get($item['id']);
            if ($existingMenu) {
                $templateIdMap[$item['id']] = $existingMenu->id;
                $affectedIds[] = $existingMenu->id;
            }
        }

        // 7. 更新 parent_id（用 template_id 映射转成 tenant_menu_id）
        foreach ($templates as $item) {
            $tenantMenuId = $templateIdMap[$item['id']] ?? null;
            if (!$tenantMenuId) continue;

            if (empty($item['pid'])) {
                continue;
            }
            $newPid = $templateIdMap[$item['pid']] ?? null;
            if ($newPid === null) {
                continue;
            }
            SyncMenu::on($conn)->where('id', $tenantMenuId)->update(['pid' => $newPid]);
        }

        // 8. 重建 level 字段
        $this->rebuildMenuLevels($templateIdMap, $templates, $conn);

        return [
            'count'            => count($affectedIds),
            'menu_ids'         => $affectedIds,
            'added'            => count($templateIdsToAdd),
            'updated'          => count($templateIdsToUpdate),
            'removed'          => count($templateIdsToRemove),
        ];
    }

    /**
     * 重建菜单 level 字段
     */
    private function rebuildMenuLevels(array $idMap, array $templates, string $conn): void
    {
        foreach ($templates as $item) {
            $newId = $idMap[$item['id']] ?? null;
            if (!$newId) continue;

            $levels = [];
            $parentId = $item['pid'];
            while ($parentId && isset($idMap[$parentId])) {
                array_unshift($levels, $idMap[$parentId]);
                $parentTemplate = current(array_filter($templates, fn($t) => $t['id'] == $parentId));
                $parentId = $parentTemplate ? ($parentTemplate['pid'] ?? 0) : 0;
            }
            $level = !empty($levels) ? implode(',', $levels) : null;
            if ($level !== null) {
                SyncMenu::on($conn)->where('id', $newId)->update(['level' => $level]);
            }
        }
    }

    /**
     * 收集所有需要的父级 ID
     */
    private function collectParentIds(array $allMenus, array $targetIds): array
    {
        $result = [];
        $idMap = [];
        foreach ($allMenus as $m) {
            $idMap[$m['id']] = $m;
        }

        $walk = function (int $id) use (&$walk, $idMap, &$result) {
            if (in_array($id, $result)) return;
            $result[] = $id;
            if (isset($idMap[$id]) && !empty($idMap[$id]['pid'])) {
                $walk((int)$idMap[$id]['pid']);
            }
        };

        foreach ($targetIds as $id) {
            $walk((int)$id);
        }

        return $result;
    }

    /**
     * 同步配置数据：从配置模板同步到租户的 sys_config 表
     *
     * 逻辑：
     * - 只处理 is_sync=1 的配置模板（平台默认配置）
     * - 按 source 字段区分：只操作 source='template' 的记录
     * - 租户手动添加的配置（source='manual'）不会被覆盖或删除
     * - 新增/更新/删除基于模板与租户现有数据的差异
     */
    public function syncConfigData(int $tenantId, string $mode): array
    {
        // 1. 获取所有启用的配置模板
        $templates = ConfigTemplate::where('enabled', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        if (empty($templates)) {
            return ['count' => 0, 'message' => '无配置模板需要同步'];
        }

        // 2. 确定目标连接
        $conn = SyncConnection::getConnectionName($tenantId, $mode);

        // 2.1 确保目标库有 template_id + source 字段
        $this->ensureSchemaColumns($conn, $mode, $tenantId);

        // 3. 加载租户已有配置（按 template_id 索引，仅 source='template' 的记录）
        $existingQuery = SyncConfig::on($conn)->where('source', 'template');
        if ($mode === 'field') {
            $existingQuery->where('tenant_id', $tenantId);
        }
        $existingConfigs = $existingQuery->whereNotNull('template_id')->get()->keyBy('template_id');
        $existingTemplateIds = $existingConfigs->keys()->toArray();

        $newTemplateIds = array_column($templates, 'id');
        $templateIdsToAdd = array_diff($newTemplateIds, $existingTemplateIds);
        $templateIdsToRemove = array_diff($existingTemplateIds, $newTemplateIds);
        $templateIdsToUpdate = array_intersect($newTemplateIds, $existingTemplateIds);

        // 4. 删除不再同步的配置（仅限 source='template' 的记录）
        if (!empty($templateIdsToRemove)) {
            $deleteQuery = SyncConfig::on($conn)
                ->where('source', 'template')
                ->whereIn('template_id', $templateIdsToRemove);
            if ($mode === 'field') {
                $deleteQuery->where('tenant_id', $tenantId);
            }
            $deleteQuery->delete();
        }

        // 5. 可写字段（排除 ID / 关联 / 审计字段，source 由同步逻辑固定赋值）
        $writableFields = array_diff(
            (new SyncConfig())->getFillable(),
            ['id', 'template_id', 'tenant_id', 'source', 'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at']
        );

        $affectedIds = [];

        // 5a. 更新已有配置
        foreach ($templates as $item) {
            if (!in_array($item['id'], $templateIdsToUpdate)) continue;

            $existingConfig = $existingConfigs->get($item['id']);
            if (!$existingConfig) continue;

            $updateData = array_intersect_key($item, array_flip($writableFields));
            $existingConfig->update($updateData);
            $affectedIds[] = $existingConfig->id;
        }

        // 5b. 新增配置
        foreach ($templates as $item) {
            if (!in_array($item['id'], $templateIdsToAdd)) continue;

            $newItem = $item;
            $oldId = $item['id'];
            $newItem['template_id'] = $oldId;
            $newItem['tenant_id'] = $tenantId;
            $newItem['source'] = 'template';
            unset($newItem['id'], $newItem['created_at'], $newItem['created_by'],
                  $newItem['updated_at'], $newItem['updated_by'], $newItem['deleted_at']);

            $config = SyncConfig::on($conn)->create($newItem);
            $affectedIds[] = $config->id;
        }

        return [
            'count'   => count($affectedIds),
            'added'   => count($templateIdsToAdd),
            'updated' => count($templateIdsToUpdate),
            'removed' => count($templateIdsToRemove),
        ];
    }

    /**
     * 同步前端菜单数据：从前端菜单模板同步到租户的 web_menu 表
     *
     * 逻辑与 syncConfigData 一致：
     * - 所有启用的前端菜单模板都会同步到租户
     * - 按 source 字段区分：只操作 source='template' 的记录
     * - 租户手动添加的菜单（source 非 template）不会被覆盖或删除
     * - 处理 pid 映射，保证菜单树层级正确
     */
    public function syncWebMenuData(int $tenantId, string $mode): array
    {
        // 1. 获取所有启用的前端菜单模板
        $templates = WebMenuTemplate::where('enabled', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        if (empty($templates)) {
            return ['count' => 0, 'message' => '无前端菜单模板需要同步'];
        }

        // 2. 确定目标连接
        $conn = SyncConnection::getConnectionName($tenantId, $mode);

        // 2.1 确保目标库 web_menu 有 template_id 字段
        $this->ensureSchemaColumns($conn, $mode, $tenantId);

        // 3. 加载租户已有菜单（按 template_id 索引，仅 source='template' 的记录）
        $existingQuery = SyncWebMenu::on($conn)->where('source', 'template');
        if ($mode === 'field') {
            $existingQuery->where('tenant_id', $tenantId);
        }
        $existingMenus = $existingQuery->whereNotNull('template_id')->get()->keyBy('template_id');
        $existingTemplateIds = $existingMenus->keys()->toArray();

        $newTemplateIds = array_column($templates, 'id');
        $templateIdsToAdd = array_diff($newTemplateIds, $existingTemplateIds);
        $templateIdsToRemove = array_diff($existingTemplateIds, $newTemplateIds);
        $templateIdsToUpdate = array_intersect($newTemplateIds, $existingTemplateIds);

        // 4. 删除不再同步的菜单（仅限 source='template' 的记录）
        if (!empty($templateIdsToRemove)) {
            $deleteQuery = SyncWebMenu::on($conn)
                ->where('source', 'template')
                ->whereIn('template_id', $templateIdsToRemove);
            if ($mode === 'field') {
                $deleteQuery->where('tenant_id', $tenantId);
            }
            $deleteQuery->delete();
        }

        // 5. 可写字段（排除 ID / 关联 / 审计字段，source 由同步逻辑固定赋值）
        $writableFields = array_diff(
            (new SyncWebMenu())->getFillable(),
            ['id', 'pid', 'template_id', 'tenant_id', 'source', 'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at']
        );

        $idMap = [];
        $affectedIds = [];

        // 5a. 更新已有菜单
        foreach ($templates as $item) {
            if (!in_array($item['id'], $templateIdsToUpdate)) continue;

            $existingMenu = $existingMenus->get($item['id']);
            if (!$existingMenu) continue;

            $updateData = array_intersect_key($item, array_flip($writableFields));
            $existingMenu->update($updateData);
            $idMap[$item['id']] = $existingMenu->id;
            $affectedIds[] = $existingMenu->id;
        }

        // 5b. 新增菜单
        foreach ($templates as $item) {
            if (!in_array($item['id'], $templateIdsToAdd)) continue;

            $newItem = $item;
            $oldId = $item['id'];
            $newItem['template_id'] = $oldId;
            $newItem['tenant_id'] = $tenantId;
            $newItem['source'] = 'template';
            unset($newItem['id'], $newItem['created_at'], $newItem['created_by'],
                  $newItem['updated_at'], $newItem['updated_by'], $newItem['deleted_at']);

            $menu = SyncWebMenu::on($conn)->create($newItem);
            $idMap[$oldId] = $menu->id;
            $affectedIds[] = $menu->id;
        }

        // 5c. 补充已有但无需更新的菜单到映射表（用于 parent_id 计算）
        foreach ($templates as $item) {
            if (isset($idMap[$item['id']])) continue;
            $existingMenu = $existingMenus->get($item['id']);
            if ($existingMenu) {
                $idMap[$item['id']] = $existingMenu->id;
                $affectedIds[] = $existingMenu->id;
            }
        }

        // 6. 更新 parent_id（用 template_id 映射转成租户内父菜单ID）
        foreach ($templates as $item) {
            $tenantMenuId = $idMap[$item['id']] ?? null;
            if (!$tenantMenuId) continue;

            if (empty($item['pid'])) {
                continue;
            }
            $newPid = $idMap[$item['pid']] ?? null;
            if ($newPid === null) {
                continue;
            }
            SyncWebMenu::on($conn)->where('id', $tenantMenuId)->update(['pid' => $newPid]);
        }

        return [
            'count'   => count($affectedIds),
            'added'   => count($templateIdsToAdd),
            'updated' => count($templateIdsToUpdate),
            'removed' => count($templateIdsToRemove),
        ];
    }

    /**
     * 确保目标库表结构包含同步所需的字段
     *
     * 当迁移文件被原地修改（非增量迁移）后，旧租户库会缺少新字段。
     * 此方法在同步前检测并补齐缺失字段。
     */
    private function ensureSchemaColumns(string $conn, string $mode, int $tenantId): void
    {
        $schema = DB::connection($conn)->getSchemaBuilder();

        // 定义需要检查的表和缺失时自动添加的字段
        $checks = [
            'sys_menu' => [
                [
                    'column'   => 'template_id',
                    'type'     => 'bigInteger',
                    'default'  => 0,
                    'comment'  => '模板ID',
                    'nullable' => false,
                ],
                [
                    'column'   => 'is_tab',
                    'type'     => 'tinyInteger',
                    'default'  => 1,
                    'comment'  => '是否显示在tags标签: 0否 1是',
                    'nullable' => false,
                ],
            ],
            'sys_config' => [
                [
                    'column'   => 'template_id',
                    'type'     => 'bigInteger',
                    'comment'  => '配置模板ID',
                    'nullable' => true,
                ],
                [
                    'column'   => 'source',
                    'type'     => 'string',
                    'default'  => 'template',
                    'comment'  => '配置来源: template-模板同步 manual-手动添加',
                    'nullable' => false,
                ],
                [
                    'column'   => 'sort',
                    'type'     => 'integer',
                    'default'  => 0,
                    'comment'  => '排序',
                    'nullable' => false,
                ],
            ],
            'web_menu' => [
                [
                    'column'   => 'template_id',
                    'type'     => 'bigInteger',
                    'comment'  => '前端菜单模板ID',
                    'nullable' => true,
                ],
            ],
        ];

        foreach ($checks as $table => $columns) {
            // 取表名（处理前缀）
            $prefix = config("database.connections.{$conn}.prefix", '');
            $tableName = $prefix ? $prefix . $table : $table;

            if (!$schema->hasTable($tableName)) {
                continue;
            }

            foreach ($columns as $col) {
                if ($schema->hasColumn($tableName, $col['column'])) {
                    continue;
                }

                echo "补齐字段: {$tableName}.{$col['column']}\n";

                $schema->table($table, function (Blueprint $table) use ($col) {
                    $method = $col['type'];
                    $column = $table->$method($col['column']);

                    if (array_key_exists('default', $col)) {
                        $column->default($col['default']);
                    }
                    if (!empty($col['nullable'])) {
                        $column->nullable();
                    }
                    if (!empty($col['comment'])) {
                        $column->comment($col['comment']);
                    }
                });

                // database 模式的回填：将已有数据的 template_id 设为 0
                if ($col['column'] === 'template_id' && $mode === 'database') {
                    DB::connection($conn)->table($tableName)
                        ->whereNull('template_id')
                        ->update(['template_id' => 0]);
                }
            }
        }
    }
}
