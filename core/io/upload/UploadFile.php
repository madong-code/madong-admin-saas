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
namespace core\io\upload;

use app\service\admin\system\config\ConfigService as AdminConfigService;
use app\service\platform\system\ConfigService as PlatformConfigService;
use core\foundation\exception\handler\UploadException;
use core\io\upload\contract\UploadFileInterface;
use support\Container;

/**
 * 文件上传
 *
 * @author Mr.April
 * @since  1.0
 * @method static uploadFile()
 */
class UploadFile
{

    static array $allowStorage = [];

    /**
     * group_code → ConfigService 类名映射
     * 新增 group_code 只需在此添加映射
     */
    private const CONFIG_SERVICE_MAP = [
        UploadScene::GROUP_PLATFORM => PlatformConfigService::class,
        UploadScene::GROUP_DEFAULT  => AdminConfigService::class,
    ];

    protected static function init(): void
    {
        $configAllowStorage = config('core.io.upload.adapter_classes');
        self::$allowStorage = array_unique(array_merge([
            'local',
            'oss',
            'cos',
            'qiniu',
            's3',
        ], array_keys($configAllowStorage)));
    }

    /**
     * 根据 group_code 决议对应的 ConfigService 实例
     */
    private static function resolveConfigService(UploadScene $scene): object
    {
        $class = self::CONFIG_SERVICE_MAP[$scene->getGroupCode()] ?? AdminConfigService::class;
        return Container::make($class);
    }

    /**
     * 获取存储适配器的文件默认配置
     * 从 config/storage.php 模板中加载，避免数据库配置缺失时拿到空配置
     */
    private static function getAdapterDefaults(string $adapter): array
    {
        $templates = config('storage.templates', []);
        foreach ($templates as $template) {
            if (($template['code'] ?? '') === $adapter) {
                return $template['content'] ?? [];
            }
        }
        return [];
    }

    /**
     * 获取配置信息
     *
     * 通用方法，按 code 查询配置，不传 group_code。
     * code 在 tenant scope 内唯一（admin）或 tenant_id=null 范围唯一（platform），无需额外分组过滤。
     * 如需场景化分组过滤，请使用 disk() 方法。
     *
     * @param string      $name    配置 code（如 'upload', 'local', 'oss'）
     * @param array       $default 默认值
     * @param UploadScene $scene   场景，默认 admin（向后兼容）
     *
     * @return array|null
     * @throws \Exception
     */
    public static function config(string $name = '', array $default = [], ?UploadScene $scene = null): ?array
    {
        $scene ??= UploadScene::admin();
        $configService = self::resolveConfigService($scene);
        $config = $configService->config($name, $default);
        return $config ?? [];
    }

    /**
     * @throws UploadException
     */
    public static function disk(string|null $storage = null, bool $is_file_upload = true, ?UploadScene $scene = null): UploadFileInterface
    {
        self::init();
        $scene ??= UploadScene::admin();
        $configService = self::resolveConfigService($scene);
        $defaultConfig = $configService->config('upload', [
            'mode'         => 'local',
            'single_limit' => 1024 * 1024,
            'total_limit'  => 1024 * 1024,
            'nums'         => 10,
            'exclude'      => ['php', 'ext', 'exe'],
        ]);
        if (empty($storage)) {
            $adapter       = $defaultConfig['mode'];
            $defaults      = self::getAdapterDefaults($adapter);
            $adapterConfig = $configService->config($adapter, $defaults);
            // 数据库中配置为空或缺失时，回退到文件默认配置
            if (empty($adapterConfig) && !empty($defaults)) {
                $adapterConfig = $defaults;
            }
        } else {
            $adapter       = $storage;
            $defaults      = self::getAdapterDefaults($adapter);
            $adapterConfig = $configService->config($storage, $defaults);
            if (empty($adapterConfig) && !empty($defaults)) {
                $adapterConfig = $defaults;
            }
        }
        if (!in_array($adapter, self::$allowStorage)) {
            throw new UploadException("不支持的存储类型:" . $adapter);
        }
        $config = array_merge($defaultConfig, $adapterConfig, ['_is_file_upload' => $is_file_upload]);
        // 平台场景：配置未指定 tenant_path 时默认不按租户分目录
        if ($scene->isPlatform() && !array_key_exists('tenant_path', $config)) {
            $config['tenant_path'] = false;
        }
        $handle = config('core.io.upload.adapter_classes.' . $adapter);
        if (!$handle) {
            throw new UploadException("未找到适配器处理器:" . $handle);
        }
        return new $handle($config);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return static::disk()->{$name}(...$arguments);
    }

}
