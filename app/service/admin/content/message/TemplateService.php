<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息模板服务
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\service\admin\content\message;

use app\dao\content\message\MessageTemplateDao;
use app\model\content\message\DefinitionRel;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;

/**
 * 消息模板服务
 *
 * 模板是独立实体，通过中间表与消息定义多对多关联。
 * 一套模板可被多个消息定义复用。
 */
class TemplateService extends BaseService
{
    public function __construct(MessageTemplateDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 检查模板是否可删除（被关联则抛异常）
     *
     * @param array $ids
     * @throws AdminException
     */
    public function checkCanDelete(array $ids): void
    {
        $count = DefinitionRel::whereIn('template_id', $ids)->count();
        if ($count > 0) {
            throw new AdminException('该模板已被消息定义关联，无法删除。请先解除关联后再操作。');
        }
    }

    /**
     * 保存模板（可选同步关联消息定义）
     *
     * @param array $data 模板数据
     * @param string|null $definitionId 可选，关联的消息定义ID
     *
     * @return \app\model\content\message\Template
     */
    public function saveTemplate(array $data, ?string $definitionId = null): \app\model\content\message\Template
    {
        // 移除非模板字段
        unset($data['definition_id']);

        $model = $this->dao->save($data);

        // 如果指定了消息定义，创建中间表关联
        if ($definitionId) {
            DefinitionRel::firstOrCreate([
                'definition_id' => $definitionId,
                'template_id'   => $model->id,
            ]);
        }

        return $model;
    }

}
