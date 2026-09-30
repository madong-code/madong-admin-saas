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

namespace app\service\admin\system\org;

use app\dao\system\org\DeptDao;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Container;

/**
 * @method getChildIdsIncludingSelf(mixed $deptId)
 */
class DeptService extends BaseService
{

    public function __construct(DeptDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * save
     *
     * @param array $data
     *
     * @return mixed
     * @throws \core\exception\handler\AdminException
     */
    public function save(array $data): mixed
    {
        try {
            return $this->transaction(function () use ($data) {
                $leaders = $data['leader_id_list'] ?? [];//部门领导
                $model   = $this->dao->save($data);
                if (!empty($leaders)) {
                    $model->leader()->sync($leaders);
                }
                return $model;
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * update
     *
     * @param $id
     * @param $data
     *
     * @return void
     * @throws \core\exception\handler\AdminException
     */
    public function update($id, $data): void
    {
        try {
            $this->transaction(function () use ($id, $data) {
                $leaders = $data['leader_id_list'] ?? [];//部门领导
                unset($data['leader_id_list']);
                $this->dao->update($id, $data);
                $model = $this->get($id);
                $model->leader()->sync($leaders);
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * destroy
     *
     * @param $id
     * @param $force
     *
     * @return void
     * @throws \core\exception\handler\AdminException
     */
    public function destroy($id, $force): void
    {
        try {
            $this->transaction(function () use ($id, $force) {
                $res = $this->dao->count(['pid' => $id], true);
                if ($res > 0) {
                    throw new AdminException('该部门下存在子部门，请先删除子部门');
                }
                $this->dao->destroy($id);
                $systemDeptLeaderService = Container::make(DeptLeaderService::class);
                $systemDeptLeaderService->dao->delete(['dept_id' => $id]);
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

}
