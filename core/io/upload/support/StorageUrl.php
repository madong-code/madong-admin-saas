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
namespace core\io\upload\support;

use core\io\upload\UploadFile;
use core\io\upload\UploadScene;
use support\Log;

/**
 * 资源访问地址解析
 *
 * 默认（公开）空间保持原有「域名 + 相对路径」拼接逻辑；仅当当前空间配置声明 is_private=true
 * （非公开读）时，改由后端按资源 key 签发带签名的临时直链。
 *
 * 前端拿到的仍是相对路径或空间域名下的原始地址，私有模式下需按 key 调用
 * 文件接口换取可访问地址，而不是自行用 cdn_url 前缀拼接。
 */
class StorageUrl
{
    /** 单次批量解析的 key 数量上限 */
    public const MAX_BATCH = 200;

    /**
     * 允许换取地址的媒体扩展名
     *
     * 仅放行可直接内联渲染的图片/音频/视频，避免附件、下载包（zip/rar/pdf/docx 等）
     * 通过该接口绕过各自的归属校验拿到签名直链。
     */
    private const MEDIA_EXTENSIONS = [
        // 图片
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg', 'ico', 'avif', 'heic', 'heif',
        // 音频
        'mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac',
        // 视频
        'mp4', 'webm', 'ogv', 'mov', 'm4v',
    ];

    /**
     * 是否为可内联渲染的媒体资源（按扩展名判断，忽略 query 与 hash）
     */
    private static function isInlineMedia(string $key): bool
    {
        $path = preg_replace('~[?#].*$~', '', $key) ?? $key;
        $ext  = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' && in_array($ext, self::MEDIA_EXTENSIONS, true);
    }

    /**
     * 批量解析资源地址（供前端按 key 换取可访问地址）
     *
     * 公开空间返回「访问域名 + 相对路径」，私有空间返回带签名的临时直链；
     * 仅处理存储根目录下、扩展名属于可内联渲染媒体的对象，其余（外链 / data URL /
     * 与存储无关的路径 / 附件与下载包）不返回，由前端保持原有拼接逻辑或走专用下载接口。
     *
     * 返回有序列表而非「key => url」映射：key 形如 /storage/xxx 含斜杠，
     * 用列表承载可避免把路径当作数据结构的键带来的歧义与解析差异。
     *
     * @param array            $keys  资源地址集合（相对路径或本空间域名下的绝对地址）
     * @param UploadScene|null $scene 配置场景
     *
     * @return array<int,array{key:string,url:string}> 可访问地址列表
     */
    public static function resolveMany(array $keys, ?UploadScene $scene = null): array
    {
        $scene  = $scene ?? UploadScene::admin();
        $result = [];
        if (empty($keys)) {
            return $result;
        }

        try {
            $mode = (string)(UploadFile::config('upload', [], $scene)['mode'] ?? '') ?: 'local';
            if ($mode === 'local') {
                // 本地存储与站点同域，前端用相对路径即可，无需解析
                return $result;
            }
            $driverConfig = UploadFile::config($mode, [], $scene);
            $prefix       = trim((string)($driverConfig['dirname'] ?? ''), '/');
            $disk         = UploadFile::disk(null, false, $scene);
        } catch (\Throwable $e) {
            Log::warning('资源地址批量解析初始化失败', ['error' => $e->getMessage()]);

            return $result;
        }

        foreach (array_slice($keys, 0, self::MAX_BATCH) as $key) {
            $key = trim(str_replace('\\', '/', (string)$key));
            if ($key === '' || str_starts_with($key, 'data:') || strlen($key) > 1024) {
                continue;
            }
            // 仅签发可内联渲染的媒体，附件/下载包须走各自的归属校验下载接口
            if (!self::isInlineMedia($key)) {
                continue;
            }
            // 相对路径只解析存储根目录下的对象；绝对地址由驱动按域名判断是否属于本空间
            if (preg_match('#^(https?:)?//#i', $key) !== 1 && $prefix !== ''
                && !str_starts_with(ltrim($key, '/'), $prefix . '/')) {
                continue;
            }

            try {
                $url = $disk->signedUrl($key);
            } catch (\Throwable $e) {
                Log::warning('资源地址解析失败，跳过', ['key' => $key, 'error' => $e->getMessage()]);
                continue;
            }
            if ($url !== $key) {
                $result[] = ['key' => $key, 'url' => $url];
            }
        }

        return $result;
    }

    /**
     * 私有空间资源签发带签名的临时直链
     *
     * @param string           $key   资源地址：相对路径 / 本空间域名下的绝对地址均可（data URL 不处理）
     * @param UploadScene|null $scene 配置场景
     *
     * @return string|null 命中私有空间返回签名直链；否则返回 null（调用方保持原有逻辑）
     */
    public static function signIfPrivate(string $key, ?UploadScene $scene = null): ?string
    {
        $key = trim(str_replace('\\', '/', $key));
        if ($key === '' || str_starts_with($key, 'data:')) {
            return null;
        }

        try {
            $disk = UploadFile::disk(null, false, $scene);
            if (!$disk->isPrivate()) {
                return null;
            }

            // 外链（非本空间域名）由驱动原样返回，等同于未命中
            $signed = $disk->signedUrl($key);

            return $signed === $key ? null : $signed;
        } catch (\Throwable $e) {
            // 签名失败不阻断业务，回落原始地址（由调用方按公开逻辑拼接）
            Log::warning('私有空间资源签名失败，已回落原始地址', ['key' => $key, 'error' => $e->getMessage()]);

            return null;
        }
    }
}