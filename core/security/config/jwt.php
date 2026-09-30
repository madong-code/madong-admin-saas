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
/**
 * JWT 配置文件
 */

return [
    'token_name'      => 'Authorization',
    // ========== 密钥配置 ==========
    // 必须修改为您的实际密钥，建议使用 openssl_random_pseudo_bytes(32) 生成
    'secret'          => env('JWT_SECRET', 'your-secret-key-change-in-production'),

    // 加密算法
    'algo'            => env('JWT_ALGO', 'HS256'),

    // ========== Token 有效期配置 (秒) ==========
    'ttl'             => [
        // 访问令牌有效期
        'access'  => env('JWT_ACCESS_TTL', 7200),

        // 刷新令牌有效期，默认 7 天
        'refresh' => env('JWT_REFRESH_TTL', 604800),
    ],

    // ========== 登录模式 ==========
    // single: 单端登录（同一用户只能在一个设备登录）
    // client: 客户端模式（同一客户端只能在一个设备，不同客户端可同时）
    // multi: 多端登录（同一用户可在多个设备同时登录）
    'login_mode'      => env('JWT_LOGIN_MODE', 'multi'),

    // ========== 客户端类型配置 ==========
    // 预定义类型: admin, api, web, mobile, mini
    // 可在此处添加自定义类型
    'client_types'    => [
        // 示例：添加自定义客户端类型
        // 'pc' => 'pc',
        // 'ipad' => 'ipad',
    ],

    // ========== 存储配置 ==========
    'storage'         => [
        // Token 存储驱动
        'token'        => env('JWT_STORAGE_TOKEN', 'redis'),
        // 黑名单存储驱动
        'blacklist'    => env('JWT_STORAGE_BLACKLIST', 'redis'),
        // Redis 前缀
        'redis_prefix' => env('JWT_REDIS_PREFIX', 'jwt:'),
    ],

    // ========== 安全配置 ==========
    'security'        => [
        // 是否启用刷新令牌
        'refresh_enabled'      => true,

        // 刷新令牌宽限期（秒），允许在令牌即将过期时提前刷新
        'refresh_grace_period' => env('JWT_REFRESH_GRACE_PERIOD', 3600),

        // 最大同时登录设备数（0 表示不限制）
        'max_devices'          => env('JWT_MAX_DEVICES', 0),

        // 是否强制刷新（每次请求都返回新的 access_token）
        'force_refresh'        => false,

        // 滑动过期：每次使用自动延长 access_token 有效期
        'sliding_expiry'       => false,
    ],

    // ========== Redis 连接配置 ==========
    'redis'           => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD'),
        'database' => env('REDIS_DATABASE', 0),
        'timeout'  => env('REDIS_TIMEOUT', 0),
    ],

    // ========== 预留字段 ==========
    // 可在生成 Token 时传入自定义数据
    'reserved_claims' => [
        // 'iss', // 签发者
        // 'aud', // 受众
    ],
];
