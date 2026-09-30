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
namespace core\foundation\base;


use core\infrastructure\cache\CacheService;
use core\foundation\trait\ServiceTrait;
use support\Db as LaravelDb;

/**
 * @method getModel()
 */
abstract class BaseService
{

    use ServiceTrait;

    /**
     * 模型注入
     */
    protected ?BaseDao $dao;

    /**
     * 缓存管理
     *
     * @return \core\infrastructure\cache\CacheService
     */
    public function cacheDriver(): CacheService
    {
        return new CacheService();
    }

    /**
     * 获取分页配置
     *
     * @param bool $isPage
     * @param bool $isRelieve
     *
     * @return int[]
     */
    public function getPageValue(bool $isPage = true, bool $isRelieve = true): array
    {
        // 获取请求实例
        $request = request();
        $page    = $limit = 0;
        if ($isPage) {
            $page  = $request->input(Config('database.page.pageKey', 'page') . '/d', 0);
            $limit = $request->input(Config('database.page.limitKey', 'limit') . '/d', 0);
        }
        $limitMax     = Config('database.page.limitMax');
        $defaultLimit = Config('database.page.defaultLimit', 10);
        if ($limit > $limitMax && $isRelieve) {
            $limit = $limitMax;
        }
        return [(int)$page, (int)$limit, (int)$defaultLimit, (int)$limitMax];
    }

    /**
     * 执行指定框架的事务
     *
     * @param callable    $closure
     * @param bool        $isTran 是否启用事务
     * @param string|null $connectionName
     *
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(callable $closure, bool $isTran = true, ?string $connectionName = null): mixed
    {
        if ($isTran) {
            // 1. 优先使用显式传入的连接名
            if (!empty($connectionName)) {
                return LaravelDb::connection($connectionName)->transaction($closure);
            }

            // 2. 利用模型的动态连接（库隔离模式自动使用 tenant_{id}，字段隔离/单租户使用默认连接）
            if (isset($this->dao)) {
                $conn = $this->dao->getModel()->getConnection();
                return $conn->transaction($closure);
            }

            // 3. 回退到默认连接
            return LaravelDb::connection(config('database.default', 'default'))->transaction($closure);
        }

        return $closure();
    }

    /**
     * 密码hash加密
     *
     * @param string $password
     *
     * @return string
     */
    public function passwordHash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->dao, $name], $arguments);
    }
}
