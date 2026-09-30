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
use app\model\member\MemberTagRelation;

/**
 * 会员标签关系数据访问对象
 */
class MemberTagRelationDao extends BaseDao
{
    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return MemberTagRelation::class;
    }

    /**
     * 批量保存标签关系（自动过滤已存在的记录）
     *
     * @param array $data 标签关系数据 [['member_id' => xx, 'tag_id' => yy], ...]
     * @return array 返回结果 ['saved' => int, 'duplicates' => int, 'failed' => int, 'errors' => array]
     * @throws \Exception
     */
    public function batchSave(array $data): array
    {
        if (empty($data)) {
            return [
                'saved' => 0,
                'duplicates' => 0,
                'failed' => 0,
                'errors' => []
            ];
        }

        // 验证数据格式
        foreach ($data as $index => $item) {
            if (!isset($item['member_id']) || !isset($item['tag_id'])) {
                throw new \Exception("数据格式错误: 第 {$index} 条记录缺少 member_id 或 tag_id");
            }
        }

        // 检查已存在的记录
        $existing = $this->getExistingRelations($data);
        
        // 过滤掉已存在的记录
        $newData = $this->filterExistingRelations($data, $existing);
        
        $duplicates = count($data) - count($newData);
        
        if (empty($newData)) {
            return [
                'saved' => 0,
                'duplicates' => $duplicates,
                'failed' => 0,
                'errors' => []
            ];
        }

        try {
            // 使用批量插入（性能更好）
            $result = $this->getModel()->insert($newData);
            
            if (!$result) {
                throw new \Exception('批量插入失败');
            }
            
            return [
                'saved' => count($newData),
                'duplicates' => $duplicates,
                'failed' => 0,
                'errors' => []
            ];
        } catch (\Throwable $e) {
            // 检查是否是唯一键冲突
            if ($this->isDuplicateEntryError($e)) {
                return [
                    'saved' => 0,
                    'duplicates' => count($data),
                    'failed' => 0,
                    'errors' => ['部分记录可能已存在']
                ];
            }
            
            throw new \Exception("批量保存失败: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取已存在的关联关系
     *
     * @param array $data
     *
     * @return array
     * @throws \Exception
     */
    protected function getExistingRelations(array $data): array
    {
        if (empty($data)) {
            return [];
        }
        
        $query = $this->query();
        
        // 构建查询：WHERE (member_id = x AND tag_id = y) OR (member_id = a AND tag_id = b) ...
        $query->where(function($q) use ($data) {
            foreach ($data as $item) {
                $q->orWhere(function($subQ) use ($item) {
                    $subQ->where('member_id', $item['member_id'])
                         ->where('tag_id', $item['tag_id']);
                });
            }
        });
        
        return $query->get()->toArray();
    }

    /**
     * 过滤掉已存在的关联关系
     *
     * @param array $data
     * @param array $existing
     * @return array
     */
    protected function filterExistingRelations(array $data, array $existing): array
    {
        if (empty($existing)) {
            return $data;
        }
        
        // 构建已存在记录的 map
        $existingMap = [];
        foreach ($existing as $record) {
            $key = $record['member_id'] . ':' . $record['tag_id'];
            $existingMap[$key] = true;
        }
        
        // 过滤掉已存在的记录
        $newData = [];
        foreach ($data as $item) {
            $key = $item['member_id'] . ':' . $item['tag_id'];
            if (!isset($existingMap[$key])) {
                $newData[] = $item;
            }
        }
        
        return $newData;
    }

    /**
     * 判断是否是重复记录错误
     *
     * @param \Throwable $e
     * @return bool
     */
    protected function isDuplicateEntryError(\Throwable $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'Duplicate entry') ||
            str_contains($message, '1062') ||
            str_contains($message, 'Integrity constraint violation');
    }

//    /**
//     * 添加标签关系
//     */
//    public function addTagRelation(int $memberId, int $tagId): bool
//    {
//        $exists = $this->query()
//            ->where('member_id', $memberId)
//            ->where('tag_id', $tagId)
//            ->exists();
//
//        if (!$exists) {
//            $relation = $this->getModel();
//            $relation->member_id = $memberId;
//            $relation->tag_id = $tagId;
//            return $relation->save();
//        }
//
//        return true;
//    }
//
//    /**
//     * 移除标签关系
//     */
//    public function removeTagRelation(int $memberId, int $tagId): bool
//    {
//        return $this->query()
//            ->where('member_id', $memberId)
//            ->where('tag_id', $tagId)
//            ->delete() > 0;
//    }
//
//    /**
//     * 获取会员的标签ID列表
//     */
//    public function getMemberTagIds(int $memberId): array
//    {
//        return $this->query()
//            ->where('member_id', $memberId)
//            ->pluck('tag_id')
//            ->toArray();
//    }
//
//    /**
//     * 获取标签的会员数量
//     */
//    public function getTagMemberCount(int $tagId): int
//    {
//        return $this->count(['tag_id' => $tagId]);
//    }


}