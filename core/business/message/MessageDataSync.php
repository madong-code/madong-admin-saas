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
use core\io\uuid\Snowflake;

/**
 * 消息分类/定义/模板数据同步
 *
 * 数据文件：resource/data/message/category.php（框架）、plugin/{name}/resource/data/message/category.php（插件）
 * 数据来源：system=框架，plugin:{name}=插件，user=后台自建
 *
 * 多租户适配（SaaS）：
 *   消息分类/定义/模板属于「平台级共享数据」，所有查询显式锁定 tenant_id IS NULL，
 *   写入时显式置 tenant_id = null，避免被租户全局作用域与自动填充逻辑改写为当前租户。
 *
 * 幂等键：
 *   - 分类   source + key
 *   - 定义   category_id + key
 *   - 模板   type + key（模板全局共享，创建后归属不变）
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

    /**
     * @param string $source 数据来源标识: system / plugin:{name}
     */
    public function __construct(private readonly string $source = 'system')
    {
        if ($this->source === '') {
            throw new \InvalidArgumentException('消息数据来源标识不能为空');
        }
    }

    public function getSource(): string
    {
        return $this->source;
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

            $category = Category::where('key', $cat['key'])
                ->where('source', $this->source)
                ->whereNull('tenant_id')
                ->first();

            $categoryFields = $this->pick($cat, ['name', 'icon', 'description', 'sort']);

            if ($category) {
                if ($updateAble && $categoryFields) {
                    $category->fill($categoryFields);
                    $category->updated_at = $now;
                    $category->save();
                    $stat['updated']++;
                } else {
                    $stat['skipped']++;
                }
            } else {
                $category = Category::create(array_merge([
                    'id'         => Snowflake::generate(),
                    'pid'        => 0,
                    'key'        => $cat['key'],
                    'name'       => $cat['name'] ?? $cat['key'],
                    'level'      => 0,
                    'path'       => '0',
                    'is_show'    => 1,
                    'is_system'  => 1,
                    'source'     => $this->source,
                    'enabled'    => 1,
                    'tenant_id'  => null,
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

                $definition = Definition::where('category_id', $category->id)
                    ->where('key', $def['key'])
                    ->whereNull('tenant_id')
                    ->first();

                $defFields = $this->pick($def, ['name', 'description', 'default_on', 'nav_type', 'nav_value', 'sort']);

                if ($definition) {
                    if ($updateAble && $defFields) {
                        $definition->fill($defFields);
                        $definition->updated_at = $now;
                        $definition->save();
                        $stat['updated']++;
                    } else {
                        $stat['skipped']++;
                    }
                } else {
                    $definition = Definition::create(array_merge([
                        'id'          => Snowflake::generate(),
                        'category_id' => $category->id,
                        'key'         => $def['key'],
                        'name'        => $def['name'] ?? $def['key'],
                        'is_system'   => 1,
                        'source'      => $this->source,
                        'enabled'     => 1,
                        'tenant_id'   => null,
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

                    $type      = $tpl['type'] ?? 'system';
                    $template  = Template::where('type', $type)
                        ->where('key', $tplKey)
                        ->whereNull('tenant_id')
                        ->first();
                    $tplFields = $this->pick($tpl, [
                        'template_id', 'title', 'content_template', 'button_template',
                        'url', 'uni_url', 'webhook_url', 'image', 'enabled', 'push_rule', 'minute',
                    ]);

                    if ($template) {
                        if ($updateAble && $tplFields) {
                            $template->fill($tplFields);
                            $template->updated_at = $now;
                            $template->save();
                            $stat['updated']++;
                        } else {
                            $stat['skipped']++;
                        }
                    } else {
                        $template = Template::create(array_merge([
                            'id'         => Snowflake::generate(),
                            'type'       => $type,
                            'key'        => $tplKey,
                            'is_system'  => 1,
                            'source'     => $this->source,
                            'enabled'    => 1,
                            'tenant_id'  => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ], $tplFields));
                        $stat['created']++;
                    }

                    $keepTplIds[] = $template->id;

                    DefinitionRel::firstOrCreate([
                        'definition_id' => $definition->id,
                        'template_id'   => $template->id,
                        'tenant_id'     => null,
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
     * 仅清理本来源（source）的数据，其余来源与后台自建数据不受影响
     */
    public function purge(): int
    {
        $definitionIds = Definition::where('source', $this->source)->whereNull('tenant_id')->pluck('id')->all();
        $templateIds   = Template::where('source', $this->source)->whereNull('tenant_id')->pluck('id')->all();
        $categoryIds   = Category::where('source', $this->source)->whereNull('tenant_id')->pluck('id')->all();

        $deleted = $this->deleteRelations($definitionIds, $templateIds);

        if ($definitionIds) {
            $deleted += Definition::whereIn('id', $definitionIds)->delete();
        }
        if ($categoryIds) {
            $deleted += Category::whereIn('id', $categoryIds)->delete();
        }
        // 模板全局共享，仅当不再被任何定义引用时才随来源删除
        if ($templateIds) {
            $usedIds = DefinitionRel::whereIn('template_id', $templateIds)->pluck('template_id')->all();
            $unused  = array_diff($templateIds, $usedIds);
            if ($unused) {
                $deleted += Template::whereIn('id', $unused)->delete();
            }
        }

        return $deleted;
    }

    /**
     * 删除本来源中文件中已不存在的节点
     */
    private function deleteOrphans(array $keepCatIds, array $keepDefIds, array $keepTplIds): int
    {
        $definitionIds = Definition::where('source', $this->source)
            ->whereNull('tenant_id')
            ->whereNotIn('id', $keepDefIds ?: [0])
            ->pluck('id')->all();
        $templateIds = Template::where('source', $this->source)
            ->whereNull('tenant_id')
            ->whereNotIn('id', $keepTplIds ?: [0])
            ->pluck('id')->all();
        $categoryIds = Category::where('source', $this->source)
            ->whereNull('tenant_id')
            ->whereNotIn('id', $keepCatIds ?: [0])
            ->pluck('id')->all();

        $deleted = $this->deleteRelations($definitionIds, $templateIds);

        if ($definitionIds) {
            $deleted += Definition::whereIn('id', $definitionIds)->delete();
        }
        if ($categoryIds) {
            $deleted += Category::whereIn('id', $categoryIds)->delete();
        }
        if ($templateIds) {
            $deleted += Template::whereIn('id', $templateIds)->delete();
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
            $deleted += DefinitionRel::whereIn('definition_id', $definitionIds)->delete();
        }
        if ($templateIds) {
            $deleted += DefinitionRel::whereIn('template_id', $templateIds)->delete();
        }

        return $deleted;
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
