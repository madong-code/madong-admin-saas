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

namespace core\business\message;

use app\model\content\message\Category;
use app\model\content\message\Definition;
use app\model\content\message\DefinitionRel;
use app\model\content\message\Template;
use app\scope\global\TenantScope;
use core\io\uuid\Snowflake;
use support\Log;

/**
 * 消息分类/定义/模板数据同步
 *
 * 数据文件：resource/data/message/category.php（框架）、plugin/{name}/resource/data/message/category.php（插件）
 * 数据来源：system=框架，plugin:{name}=插件，user=后台自建
 *
 * 多租户适配（SaaS）：
 *   消息数据按租户隔离，写入哪个库、哪个 tenant_id 完全由构造参数决定，不再依赖请求上下文：
 *   - tenant_id = 0 : 平台/单租户默认数据
 *   - tenant_id = T : 租户私有数据（字段隔离写主库 tenant_id=T；库隔离写 tenant_T 库）
 *
 * 为彻底避免上下文污染，本类所有读写：
 *   1. 显式设置模型连接（$connection ?? 默认连接），不读取 TenantContext::getConnectionName()；
 *   2. 通过 withoutGlobalScope(TenantScope::class) 移除租户全局作用域；
 *   3. 使用查询构造器写入，绕过 BaseModel 的 creating 自动填充，tenant_id 保持调用方指定值。
 *
 * 幂等键：
 *   - 分类   tenant_id + source + key
 *   - 定义   tenant_id + category_id + key
 *   - 模板   tenant_id + type + key（模板全局共享，创建后归属不变）
 *
 * 三种模式：
 *   - increment：仅补插文件中存在、数据库中缺失的（不更新、不删除）
 *   - sync：补插缺失 + 更新已有 + 删除本来源中文件中已不存在的
 *   - full：先清空本来源数据再全量导入
 */
class MessageDataSync
{
    public const MODE_INCREMENT = 'increment';
    public const MODE_SYNC = 'sync';
    public const MODE_FULL = 'full';

    /** @var string 目标租户标识，0 表示平台/单租户 */
    private readonly string $tenantId;

    /** @var string|null 目标连接名，null 表示默认连接（主库） */
    private readonly ?string $connection;

    /**
     * @param string          $source     数据来源标识: system / plugin:{name}
     * @param string|int|null $tenantId   目标租户ID，null=平台数据(0)
     * @param string|null     $connection 目标连接名，null=默认连接
     */
    public function __construct(
        private readonly string $source = 'system',
        string|int|null $tenantId = null,
        ?string $connection = null
    ) {
        if ($this->source === '') {
            throw new \InvalidArgumentException('消息数据来源标识不能为空');
        }
        $this->tenantId   = $tenantId === null || $tenantId === '' ? '0' : (string)$tenantId;
        $this->connection = $connection === null || $connection === '' ? null : $connection;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * 同步消息分类数据
     *
     * @param array  $categories require 后的分类数组
     * @param string $mode       self::MODE_*
     *
     * @return array{created:int,updated:int,skipped:int,deleted:int}
     */
    public function sync(array $categories, string $mode = self::MODE_INCREMENT): array
    {
        $stat = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'deleted' => 0];

        if ($mode === self::MODE_FULL) {
            $stat['deleted'] = $this->purge();
        }

        $now        = time();
        $updateAble = $mode !== self::MODE_INCREMENT;
        $keepCatIds = [];
        $keepDefIds = [];
        $keepTplIds = [];

        foreach ($categories as $cat) {
            if (empty($cat['key'])) {
                continue;
            }

            $category = $this->newQuery(Category::class)
                ->where('tenant_id', $this->tenantId)
                ->where('source', $this->source)
                ->where('key', $cat['key'])
                ->first();

            $categoryFields = $this->pick($cat, ['name', 'icon', 'description', 'sort']);

            if ($category) {
                if ($updateAble && $categoryFields) {
                    $this->newQuery(Category::class)
                        ->where('id', $category->id)
                        ->where('tenant_id', $this->tenantId)
                        ->update($categoryFields + ['updated_at' => $now]);
                    $stat['updated']++;
                } else {
                    $stat['skipped']++;
                }
            } else {
                if ($this->categoryKeyTaken($cat['key'])) {
                    $this->warnConflict('category', (string)$cat['key']);
                    $stat['skipped']++;
                    continue;
                }

                $category = (object)[
                    'id' => (string)Snowflake::generate(),
                ];

                $this->newQuery(Category::class)->insert(array_merge([
                    'id'         => $category->id,
                    'pid'        => 0,
                    'key'        => $cat['key'],
                    'name'       => $cat['name'] ?? $cat['key'],
                    'level'      => 0,
                    'path'       => '0',
                    'is_show'    => 1,
                    'is_system'  => 1,
                    'source'     => $this->source,
                    'enabled'    => 1,
                    'tenant_id'  => $this->tenantId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $categoryFields));
                $stat['created']++;
            }

            $keepCatIds[] = $category->id;

            foreach ($cat['definitions'] ?? $cat['children'] ?? [] as $def) {
                if (empty($def['key'])) {
                    continue;
                }

                $definition = $this->newQuery(Definition::class)
                    ->where('tenant_id', $this->tenantId)
                    ->where('category_id', $category->id)
                    ->where('key', $def['key'])
                    ->first();

                $defFields = $this->pick($def, ['name', 'description', 'default_on', 'nav_type', 'nav_value', 'sort']);

                if ($definition) {
                    if ($updateAble && $defFields) {
                        $this->newQuery(Definition::class)
                            ->where('id', $definition->id)
                            ->where('tenant_id', $this->tenantId)
                            ->update($defFields + ['updated_at' => $now]);
                        $stat['updated']++;
                    } else {
                        $stat['skipped']++;
                    }
                } else {
                    if ($this->definitionKeyTaken((string)$category->id, (string)$def['key'])) {
                        $this->warnConflict('definition', (string)$def['key']);
                        $stat['skipped']++;
                        continue;
                    }

                    $definition = (object)[
                        'id' => (string)Snowflake::generate(),
                    ];

                    $this->newQuery(Definition::class)->insert(array_merge([
                        'id'          => $definition->id,
                        'category_id' => $category->id,
                        'key'         => $def['key'],
                        'name'        => $def['name'] ?? $def['key'],
                        'is_system'   => 1,
                        'source'      => $this->source,
                        'enabled'     => 1,
                        'tenant_id'   => $this->tenantId,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ], $defFields));
                    $stat['created']++;
                }

                $keepDefIds[] = $definition->id;

                foreach ($def['templates'] ?? [] as $tpl) {
                    // 模板稳定键：优先 key，兼容仅声明 template_id 的写法
                    $tplKey = $tpl['key'] ?? $tpl['template_id'] ?? null;
                    if (empty($tplKey)) {
                        continue;
                    }

                    $type     = $tpl['type'] ?? 'system';
                    $template = $this->newQuery(Template::class)
                        ->where('tenant_id', $this->tenantId)
                        ->where('type', $type)
                        ->where('key', $tplKey)
                        ->first();

                    $tplFields = $this->pick($tpl, [
                        'template_id', 'title', 'content_template', 'button_template',
                        'url', 'uni_url', 'webhook_url', 'image', 'enabled', 'push_rule', 'minute',
                    ]);

                    if ($template) {
                        if ($updateAble && $tplFields) {
                            $this->newQuery(Template::class)
                                ->where('id', $template->id)
                                ->where('tenant_id', $this->tenantId)
                                ->update($tplFields + ['updated_at' => $now]);
                            $stat['updated']++;
                        } else {
                            $stat['skipped']++;
                        }
                    } else {
                        $template = (object)[
                            'id' => (string)Snowflake::generate(),
                        ];

                        $this->newQuery(Template::class)->insert(array_merge([
                            'id'         => $template->id,
                            'type'       => $type,
                            'key'        => $tplKey,
                            'is_system'  => 1,
                            'source'     => $this->source,
                            'enabled'    => 1,
                            'tenant_id'  => $this->tenantId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ], $tplFields));
                        $stat['created']++;
                    }

                    $keepTplIds[] = $template->id;

                    $this->newQuery(DefinitionRel::class)->insertOrIgnore([
                        'definition_id' => $definition->id,
                        'template_id'   => $template->id,
                        'tenant_id'     => $this->tenantId,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                }
            }
        }

        if ($mode === self::MODE_SYNC) {
            $stat['deleted'] = $this->deleteOrphans($keepCatIds, $keepDefIds, $keepTplIds);
        }

        return $stat;
    }

    /**
     * 按来源清理数据（分类/定义/模板及其关联）
     * 仅清理本来源 + 本租户的数据，其余来源、其他租户与后台自建数据不受影响
     */
    public function purge(): int
    {
        $definitionIds = $this->newQuery(Definition::class)
            ->where('source', $this->source)
            ->where('tenant_id', $this->tenantId)
            ->pluck('id')->all();
        $templateIds = $this->newQuery(Template::class)
            ->where('source', $this->source)
            ->where('tenant_id', $this->tenantId)
            ->pluck('id')->all();
        $categoryIds = $this->newQuery(Category::class)
            ->where('source', $this->source)
            ->where('tenant_id', $this->tenantId)
            ->pluck('id')->all();

        $deleted = $this->deleteRelations($definitionIds, $templateIds);

        if ($definitionIds) {
            $deleted += $this->newQuery(Definition::class)
                ->where('tenant_id', $this->tenantId)
                ->whereIn('id', $definitionIds)
                ->delete();
        }
        if ($categoryIds) {
            $deleted += $this->newQuery(Category::class)
                ->where('tenant_id', $this->tenantId)
                ->whereIn('id', $categoryIds)
                ->delete();
        }
        // 模板全局共享，仅当不再被任何定义引用时才随来源删除
        if ($templateIds) {
            $usedIds = $this->newQuery(DefinitionRel::class)
                ->where('tenant_id', $this->tenantId)
                ->whereIn('template_id', $templateIds)
                ->pluck('template_id')->all();
            $unused  = array_diff($templateIds, $usedIds);
            if ($unused) {
                $deleted += $this->newQuery(Template::class)
                    ->where('tenant_id', $this->tenantId)
                    ->whereIn('id', $unused)
                    ->delete();
            }
        }

        return $deleted;
    }

    /**
     * 删除本来源中文件中已不存在的节点
     */
    private function deleteOrphans(array $keepCatIds, array $keepDefIds, array $keepTplIds): int
    {
        $definitionIds = $this->newQuery(Definition::class)
            ->where('source', $this->source)
            ->where('tenant_id', $this->tenantId)
            ->whereNotIn('id', $keepDefIds ?: [0])
            ->pluck('id')->all();
        $templateIds = $this->newQuery(Template::class)
            ->where('source', $this->source)
            ->where('tenant_id', $this->tenantId)
            ->whereNotIn('id', $keepTplIds ?: [0])
            ->pluck('id')->all();
        $categoryIds = $this->newQuery(Category::class)
            ->where('source', $this->source)
            ->where('tenant_id', $this->tenantId)
            ->whereNotIn('id', $keepCatIds ?: [0])
            ->pluck('id')->all();

        $deleted = $this->deleteRelations($definitionIds, $templateIds);

        if ($definitionIds) {
            $deleted += $this->newQuery(Definition::class)
                ->where('tenant_id', $this->tenantId)
                ->whereIn('id', $definitionIds)
                ->delete();
        }
        if ($categoryIds) {
            $deleted += $this->newQuery(Category::class)
                ->where('tenant_id', $this->tenantId)
                ->whereIn('id', $categoryIds)
                ->delete();
        }
        if ($templateIds) {
            $deleted += $this->newQuery(Template::class)
                ->where('tenant_id', $this->tenantId)
                ->whereIn('id', $templateIds)
                ->delete();
        }

        return $deleted;
    }

    /**
     * 删除定义/模板相关关联记录
     */
    private function deleteRelations(array $definitionIds, array $templateIds): int
    {
        $deleted = 0;

        if ($definitionIds) {
            $deleted += $this->newQuery(DefinitionRel::class)
                ->where('tenant_id', $this->tenantId)
                ->whereIn('definition_id', $definitionIds)
                ->delete();
        }
        if ($templateIds) {
            $deleted += $this->newQuery(DefinitionRel::class)
                ->where('tenant_id', $this->tenantId)
                ->whereIn('template_id', $templateIds)
                ->delete();
        }

        return $deleted;
    }

    /**
     * 创建绑定到目标连接并移除租户作用域的查询构造器
     *
     * 注意：全局作用域注册的是 app\scope\global\TenantScope 实例，
     * 必须用该类名移除，不能用父类或字符串 'tenant'。
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function newQuery(string $modelClass): \Illuminate\Database\Eloquent\Builder
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass();
        $model->setConnection($this->resolveConnection());

        return $model->newQuery()->withoutGlobalScope(TenantScope::class);
    }

    /**
     * 解析目标连接：显式连接优先，否则回退默认连接，绝不读取租户上下文
     */
    private function resolveConnection(): string
    {
        return $this->connection ?? (string)config('database.default', 'mysql');
    }

    /**
     * 检查同租户内是否已存在同名分类 key（跨来源冲突）
     */
    private function categoryKeyTaken(string $key): bool
    {
        return $this->newQuery(Category::class)
            ->where('tenant_id', $this->tenantId)
            ->where('key', $key)
            ->exists();
    }

    /**
     * 检查同租户、同分类内是否已存在同名定义 key（跨来源冲突）
     */
    private function definitionKeyTaken(string $categoryId, string $key): bool
    {
        return $this->newQuery(Definition::class)
            ->where('tenant_id', $this->tenantId)
            ->where('category_id', $categoryId)
            ->where('key', $key)
            ->exists();
    }

    /**
     * 记录 key 冲突日志（同租户内不同来源复用了同一 key，跳过而不中断安装）
     */
    private function warnConflict(string $type, string $key): void
    {
        try {
            Log::channel('plugin')->warning('[MessageDataSync] 消息键冲突，已跳过', [
                'type'      => $type,
                'key'       => $key,
                'source'    => $this->source,
                'tenant_id' => $this->tenantId,
            ]);
        } catch (\Throwable $e) {
            // 日志失败不影响同步流程
        }
    }

    /**
     * 仅保留数据文件里声明过的字段，避免用默认值覆盖库中已有值
     */
    private function pick(array $data, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $result[$key] = $data[$key];
            }
        }
        return $result;
    }
}
