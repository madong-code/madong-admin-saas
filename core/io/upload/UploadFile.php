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
     * 获取存储运行时信息（供接口下发给前端，用于拼接资源完整地址）
     *
     * - mode=local：资源与站点同域，前端用相对路径即可
     * - mode=qiniu/oss/cos/s3：资源在云存储，前端必须用 cdn_url 前缀拼接，否则会回落站点域名而 404
     * - is_private：当前空间为私有（非公开读）时前端不能自行拼接，必须按资源 key 向
     *   /adminapi/file/access-urls（或 web 端对应接口）换取带签名的临时直链
     * - storage_prefix：对象在空间内的根目录名（如 upload），前端据此判断一个相对路径
     *   是否属于存储资源
     *
     * @param UploadScene|null $scene 场景，默认 admin（向后兼容）；平台端传 platform
     *
     * @return array{mode:string,cdn_url:string,cdn_url_params:string,is_private:bool,storage_prefix:string}
     */
    public static function runtimeInfo(?UploadScene $scene = null): array
    {
        $mode = 'local';
        try {
            $mode = (string)(self::config('upload', [], $scene)['mode'] ?? '') ?: 'local';
        } catch (\Throwable) {
            // 配置读取失败按本地模式处理，不影响调用方
        }

        $cdnUrl        = '';
        $isPrivate     = false;
        $storagePrefix = '';
        if ($mode !== 'local') {
            // 优先取当前驱动自身配置的访问域名：它才是对象实际存放地，
            // 改存储配置（换桶 / 换域名）后前端应立刻拿到新域名，不能被配置文件的旧值盖住
            try {
                $driverConfig  = self::config($mode, [], $scene);
                $cdnUrl        = rtrim((string)($driverConfig['domain'] ?? ''), '/');
                $isPrivate     = !empty($driverConfig['is_private']);
                $storagePrefix = trim((string)($driverConfig['dirname'] ?? ''), '/');
            } catch (\Throwable) {
            }
            if ($cdnUrl === '') {
                // 兜底：配置文件的 CDN 域名
                $cdnUrl = rtrim((string)config('madong.upload.app.cdn_url', ''), '/');
            }
        }

        return [
            'mode'           => $mode,
            'cdn_url'        => $cdnUrl,
            'cdn_url_params' => (string)config('madong.upload.app.cdn_url_params', ''),
            'is_private'     => $isPrivate,
            'storage_prefix' => $storagePrefix,
        ];
    }

    /**
     * 当前存储空间标识（附件去重作用域，对应附件表 space 字段）
     *
     * 同一驱动下，公开空间与私有空间是两个互相独立的桶 / 访问域名，
     * 同一份文件在两边各存一份副本。若两者共用一条附件记录，切换空间后会返回
     * 另一个空间的地址（当前空间访问不到，私有地址还会因缺签名而 403），
     * 甚至因为提前命中记录而根本没有把对象落到当前空间。
     * 因此去重必须带上本维度：default=公开空间，private=私有空间。
     * `local` 驱动无私有概念，恒为 default。
     *
     * @param string|null $mode 目标驱动（local/qiniu/oss/cos/s3），为空时取当前上传配置
     */
    public static function spaceMark(?string $mode = null, ?UploadScene $scene = null): string
    {
        $scene ??= UploadScene::admin();
        try {
            $mode = $mode ?: ((string)(self::config('upload', [], $scene)['mode'] ?? '') ?: 'local');
            if ($mode === 'local') {
                return 'default';
            }
            return !empty(self::config($mode, [], $scene)['is_private']) ? 'private' : 'default';
        } catch (\Throwable) {
            // 配置读取失败按公开空间处理，不影响上传主流程
            return 'default';
        }
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
