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
namespace app\service\admin\member;

use app\dao\member\MemberTagRelationDao;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;


class MemberTagRelationService extends BaseService
{
    public function __construct(MemberTagRelationDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 移除会员用户-关联标签
     *
     * @param array $data
     *
     * @throws \core\exception\handler\AdminException
     */
    public function removeUserTag(array $data): void
    {
        try {
            $this->transaction(function () use ($data) {
                foreach ($data as $item) {
                    $this->dao->getModel()->where($item)->delete();
                }
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 保存用户-关联标签
     *
     * @param array $data
     *
     * @return array 返回保存结果 ['saved' => int, 'duplicates' => int, 'failed' => int]
     * @throws \core\exception\handler\AdminException
     */
    public function saveUserTags(array $data): array
    {
        try {
            return $this->transaction(function () use ($data) {
                // 检查数据有效性
                if (empty($data)) {
                    throw new AdminException('数据不能为空');
                }
                // 检查必填字段
                foreach ($data as $index => $item) {
                    if (!isset($item['member_id']) || !isset($item['tag_id'])) {
                        throw new AdminException("数据格式错误: 第 {$index} 条记录缺少 member_id 或 tag_id");
                    }
                }
                // 使用专门的批量保存方法
                $result = $this->dao->batchSave($data);
                // 如果没有任何数据被保存且没有重复记录，则认为是失败
                if ($result['saved'] === 0 && $result['duplicates'] === 0 && $result['failed'] > 0) {
                    throw new AdminException('批量保存失败: ' . implode(', ', $result['errors']));
                }
                return $result;
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

}