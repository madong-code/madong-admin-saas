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
namespace core\io\uuid;

use madong\helper\Snowflake as SnowflakeGenerator;
use core\foundation\exception\handler\AdminException;
use RuntimeException;
use Webman\App;

/**
 * 雪花ID生成
 *
 * @author Mr.April
 * @since  1.0
 * @method static string generate()
 */
final class Snowflake
{
    /**
     * @var \madong\helper\Snowflake|null 唯一的 Snowflake 实例
     */
    private static ?SnowflakeGenerator $instance = null;

    /**
     * 私有构造方法 —— 禁止外部 new
     */
    private function __construct()
    {
        // 禁止外部实例化
    }

    /**
     * 私有克隆方法 —— 禁止 clone
     */
    private function __clone()
    {
    }

    /**
     * 私有反序列化方法 —— 禁止反序列化恢复对象
     */
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize singleton.");
    }

    /**
     * 获取唯一的 Snowflake 实例（懒加载）
     *
     * @return \madong\helper\Snowflake
     */
    private static function instance(): SnowflakeGenerator
    {
        if (self::$instance === null) {
            $nodeId         = 1;
            $workerId       = App::worker() ? (App::worker()->id ?: 0) : 0;
            self::$instance = new SnowflakeGenerator(
                $nodeId,
                $workerId,
                array_merge([
                    'node_id_bits'   => 3,
                    'worker_id_bits' => 7,
                    'sequence_bits'  => 12,
                ], config('core.io.snowflake', []))

            );
        }

        return self::$instance;
    }

    /**
     * 静态代理调用：将所有静态方法调用转发到真正的 Snowflake 实例上
     *
     * @param string $method 方法名，比如 "generate"
     * @param array  $args   参数列表
     *
     * @return mixed
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function __callStatic(string $method, array $args)
    {
        $instance = self::instance();

        if (!method_exists($instance, $method)) {
            throw new AdminException(sprintf(
                'Call to undefined method %s::%s()',
                static::class,
                $method
            ));
        }

        return $instance->{$method}(...$args);
    }
}

