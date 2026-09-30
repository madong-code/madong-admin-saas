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

namespace app\service\admin\system\dict;

use app\dao\system\dict\DictDao;
use app\model\system\dict\Dict;
use app\model\system\dict\DictItem;
use app\model\tenant\DictItemTemplate;
use app\model\tenant\DictTemplate;
use core\business\tenant\SyncConnection;
use core\foundation\base\BaseService;

/**
 * 数据字段服务
 *
 * @author Mr.April
 * @since  1.0
 * @method update($where, $data)
 * @method findItemsByCode($dictType)
 */
class DictService extends BaseService
{
    public function __construct(DictDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 同步模板字典到租户字典表
     *
     * 根据当前系统模式，将 saas_template_dict 中的字典
     * 深度复制到对应的租户字典表：
     * - FIELD 模式：复制到 sys_dict（设置 tenant_id）
     * - DB 模式：复制到租户独立库的 sys_dict
     *
     * @param int    $tenantId 租户ID
     * @param string $mode     隔离模式：field|database
     *
     * @return array 同步结果
     */
    public function syncTemplateDicts(int $tenantId, string $mode = 'field'): array
    {
        // 1. 获取所有启用状态的字典模板
        $templates = DictTemplate::where('enabled', 1)
            ->orderBy('sort')
            ->get()
            ->toArray();

        if (empty($templates)) {
            return ['tenant_id' => $tenantId, 'mode' => $mode, 'count' => 0];
        }

        // database 模式：先注册租户连接配置
        if ($mode === 'database') {
            SyncConnection::register($tenantId);
        }

        $idMap = [];
        $newDicts = [];

        // 2. 复制字典
        foreach ($templates as $item) {
            $oldId = $item['id'];
            unset(
                $item['id'], $item['app'], $item['created_at'], $item['created_by'],
                $item['updated_at'], $item['updated_by'], $item['deleted_at']
            );
            $item['tenant_id'] = $tenantId;

            if ($mode === 'field') {
                $dict = Dict::create($item);
            } else {
                $dict = Dict::on('tenant_' . $tenantId)->create($item);
            }

            $idMap[$oldId] = $dict->id;
            $newDicts[$dict->id] = $dict;
        }

        // 3. 复制字典项
        $itemTemplates = DictItemTemplate::where('enabled', 1)
            ->orderBy('sort')
            ->get()
            ->toArray();

        foreach ($itemTemplates as $item) {
            $oldDictId = $item['dict_template_id'] ?? 0;
            $newDictId = $idMap[$oldDictId] ?? null;
            if (!$newDictId) {
                continue;
            }

            unset(
                $item['id'], $item['created_at'], $item['created_by'],
                $item['updated_at'], $item['updated_by'], $item['deleted_at']
            );
            $item['tenant_id'] = $tenantId;
            $item['dict_id'] = $newDictId;

            if ($mode === 'field') {
                DictItem::create($item);
            } else {
                DictItem::on('tenant_' . $tenantId)->create($item);
            }
        }

        return [
            'tenant_id' => $tenantId,
            'mode'      => $mode,
            'count'     => count($newDicts),
        ];
    }
}
