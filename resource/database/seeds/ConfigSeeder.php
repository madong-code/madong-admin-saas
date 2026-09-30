<?php
/**
 * 系统配置种子
 */

declare(strict_types=1);
namespace resource\database\seeds;

use app\model\system\config\Config;
use app\model\tenant\ConfigTemplate;
use core\io\uuid\Snowflake;
use Illuminate\Database\Seeder;

class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = include base_path('resource/data/config/config.php');

        if (config('tenant.enable', false)) {
            $this->seedToConfigTemplate($configs);
        } else {
            $this->seedToSysConfig($configs);
        }
    }

    /**
     * single 模式：写入 sys_config（直接使用）
     */
    private function seedToSysConfig(array $configs): void
    {
        Config::truncate();

        foreach ($configs as $config) {
            Config::create([
                'id'         => Snowflake::generate(),
                'group_code' => $config['group_code'] ?? 'default',
                'code'       => $config['code'] ?? '',
                'name'       => $config['name'] ?? '',
                'content'    => is_array($config['content'] ?? '') ? json_encode($config['content'], JSON_UNESCAPED_UNICODE) : ($config['content'] ?? ''),
                'is_sys'     => $config['is_sys'] ?? 0,
                'enabled'    => $config['enabled'] ?? 1,
                'source'     => 'template',
                'created_at' => $config['created_at'] ?? time(),
                'updated_at' => $config['updated_at'] ?? time(),
                'deleted_at' => $config['deleted_at'] ?? null,
                'remark'     => $config['remark'] ?? '',
            ]);
        }
    }

    /**
     * field/database 模式：写入 saas_template_config（平台管理，同步到租户）
     */
    private function seedToConfigTemplate(array $configs): void
    {
        ConfigTemplate::truncate();

        foreach ($configs as $config) {
            ConfigTemplate::create([
                'id'         => Snowflake::generate(),
                'group_code' => $config['group_code'] ?? 'default',
                'code'       => $config['code'] ?? '',
                'name'       => $config['name'] ?? '',
                'content'    => is_array($config['content'] ?? '') ? json_encode($config['content'], JSON_UNESCAPED_UNICODE) : ($config['content'] ?? ''),
                'is_sys'     => $config['is_sys'] ?? 0,
                'enabled'    => $config['enabled'] ?? 1,
                'sort'       => $config['sort'] ?? 0,
                'created_at' => $config['created_at'] ?? time(),
                'updated_at' => $config['updated_at'] ?? time(),
                'deleted_at' => $config['deleted_at'] ?? null,
                'remark'     => $config['remark'] ?? '',
            ]);
        }
    }
}
