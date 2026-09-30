<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息管理服务（仅消息定义）
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\service\admin\content\message;

use app\dao\content\message\MessageDefinitionDao;
use app\model\content\message\Definition;
use app\model\content\message\DefinitionRel;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;

/**
 * 消息管理服务
 * 仅处理消息定义 CRUD，消息模板由独立 TemplateService 管理
 */
class ManageService extends BaseService
{
    public function __construct(MessageDefinitionDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 删除定义（解除中间表关联，不删除模板）
     */
    public function destroy(array $ids): void
    {
        foreach ($ids as $id) {
            $definition = Definition::find($id);
            if (!$definition) {
                continue;
            }
            if ($definition->is_system) {
                throw new AdminException('系统内置消息不可删除');
            }
        }

        // 解除模板关联
        DefinitionRel::whereIn('definition_id', $ids)->delete();
        // 删除定义
        $this->dao->delete($ids);
    }
}
