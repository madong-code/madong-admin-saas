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
namespace app\dao\member;

use core\foundation\base\BaseDao;
use app\enum\common\EnabledStatus;
use app\model\member\MemberTag;

/**
 * 会员标签数据访问对象
 */
class MemberTagDao extends BaseDao
{
    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return MemberTag::class;
    }

    /**
     * 获取标签使用统计
     */
    public function getUsageCount(int $tagId): int
    {
        $relationDao = new MemberTagRelationDao();
        return $relationDao->getTagMemberCount($tagId);
    }

    /**
     * 获取启用的标签列表
     */
    public function getEnabledTags(): array
    {
        return $this->query()
            ->where('enabled', EnabledStatus::ENABLED->value)
            ->orderBy('sort', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 获取标签详情
     */
    public function getTagById(int $tagId): ?MemberTag
    {
        return $this->find($tagId);
    }

    /**
     * 获取标签列表
     */
    public function getTagList(array $params = []): array
    {
        $query = $this->query();

        // 状态筛选
        if (isset($params['enabled'])) {
            $query->where('enabled', $params['enabled']);
        }

        // 关键词搜索
        if (isset($params['keyword'])) {
            $query->where('name', 'like', '%' . $params['keyword'] . '%')
                ->orWhere('description', 'like', '%' . $params['keyword'] . '%');
        }

        return $query->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 创建或更新标签
     */
    public function saveTag(array $data): MemberTag
    {
        $tagId = $data['id'] ?? 0;

        if ($tagId) {
            // 更新标签
            $tag = $this->find($tagId);
            if (!$tag) {
                throw new \Exception('标签不存在');
            }
        } else {
            // 创建新标签
            $tag = $this->getModel();
            $tag->enabled = EnabledStatus::ENABLED->value;
        }

        // 填充数据
        $fillable = [
            'name',
            'color',
            'description',
            'sort',
            'enabled',
        ];

        foreach ($fillable as $field) {
            if (isset($data[$field])) {
                $tag->{$field} = $data[$field];
            }
        }

        $tag->save();
        return $tag;
    }

    /**
     * 删除标签
     */
    public function deleteTag(int $tagId): bool
    {
        // 检查标签是否被使用
        $usageCount = $this->getUsageCount($tagId);
        if ($usageCount > 0) {
            throw new \Exception('该标签已被使用，无法删除');
        }

        $tag = $this->find($tagId);
        if (!$tag) {
            throw new \Exception('标签不存在');
        }

        return $tag->delete();
    }
}