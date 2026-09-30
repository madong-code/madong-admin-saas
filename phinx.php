<?php
/**
 *+------------------
 * madong
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

return [
    "paths" => [
        "migrations" => "resource/database/migrations",
        "seeds"      => "resource/database/seeds"
    ],
    "environments" => [
        "default_migration_table" => "phinxlog",
        "default_database"        => "dev",
        "default_environment"     => "dev",
        "dev" => [
            "adapter" => "mysql",
            "host"    => "127.0.0.1",
            "name"    => "madong_saas",
            "user"    => "root",
            "pass"    => "root",
            "port"    => "3306",
            "charset" => "utf8mb4"
        ]
    ]
];
