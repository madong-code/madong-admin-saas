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
 * Official Website: https://madong.tech
 */

namespace core\business\install\traits;

use app\model\system\config\Config as SysConfig;
use app\model\tenant\ConfigTemplate;
use core\io\uuid\Snowflake;

/**
 * 配置导入 Trait
 */
trait ConfigTrait
{

    /**
     * 运行配置种子
     *
     * @param bool $enableTenant 是否启用多租户模式
     *        - true:  多租户模式，只写入 saas_template_config，租户创建时同步
     *        - false: 非租户模式，直接写入 sys_config
     */
    public function runConfig(bool $enableTenant = false): void
    {
        $configFile = base_path('resource/data/config/config.php');
        if (!file_exists($configFile)) {
            return;
        }

        $configs = require $configFile;

        // 单库模式写 sys_config，多租户模式写 saas_template_config
        $configTable = $enableTenant ? $this->table((new ConfigTemplate())->getTable()) : $this->table((new SysConfig())->getTable());
        $pdo = $this->getPdo();

        $pdo->exec("TRUNCATE TABLE `{$configTable}`");

        foreach ($configs as $config) {
            $content = isset($config['content']) 
                ? (is_array($config['content']) 
                    ? json_encode($config['content'], JSON_UNESCAPED_UNICODE) 
                    : $config['content']) 
                : '';

            $data = [
                'id'         => Snowflake::generate(),
                'group_code' => $config['group_code'] ?? 'system',
                'code'       => $config['code'] ?? '',
                'name'       => $config['name'] ?? '',
                'content'    => $content,
                'is_sys'     => $config['is_sys'] ?? 0,
                'enabled'    => $config['enabled'] ?? 0,
                'remark'     => $config['remark'] ?? '',
                'created_at' => $this->currentTime,
                'created_by' => $config['created_by'] ?? null,
                'updated_at' => $this->currentTime,
                'updated_by' => $config['updated_by'] ?? null,
                'deleted_at' => $config['deleted_at'] ?? null,
            ];

            // 非租户模式写入 sys_config：标记来源为 template
            if (!$enableTenant) {
                $data['source'] = 'template';
            } else {
                // 多租户模式：模板表需要 sort
                $data['sort'] = $config['sort'] ?? 0;
            }

            $this->insert($configTable, $data);
        }
    }
}
