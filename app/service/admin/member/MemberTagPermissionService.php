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

use app\dao\member\MemberTagPermissionDao;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Container;

/**
 * 会员标签权限服务类
 */
class MemberTagPermissionService extends BaseService
{

    /**
     * 构造方法
     */
    public function __construct(MemberTagPermissionDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 更新设置角色权限（含事务封装）
     *
     * @param $data
     *
     * @throws \core\exception\handler\AdminException
     */
    public function save($data): void
    {
        try {
            $this->transaction(function () use ($data) {
                $this->saveWithoutTransaction($data);
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 无事务版本：核心业务逻辑（供内部/外部事务复用）
     *
     * @param array $data
     *
     * @throws \core\exception\handler\AdminException
     */
    public function saveWithoutTransaction(array $data): void
    {
        try {
            $tagId          = $data['tag_id'] ?? '';
            $newPermissions = $data['menu_id'] ?? [];
            if (empty($tagId)) {
                throw new AdminException('参数错误缺少tag_id', -1);
            }
            // 1. 获取会员标签模型（确保会员标签存在）
            /** @var MemberTagService $tagService */
            $tagService = Container::make(MemberTagService::class);
            $tagModel   = $tagService->get($tagId, ['*'], ['permissions']);
            if (!$tagModel) {
                throw new AdminException('会员标签不存在', -1);
            }
            $tagModel->permissions()->sync($newPermissions);

        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }
}