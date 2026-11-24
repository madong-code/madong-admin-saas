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

namespace core\io\network;

/**
 * 安全远程图片拉取器
 *
 * 在 SSRF 防护（HttpUrlGuard）之上额外叠加：
 * - 图片格式白名单（jpg / png / gif / webp）
 * - 服务端 MIME + Magic Number 双重校验，防止 Content-Type 伪造
 * - 图片大小上限（默认 5MB）
 *
 * 返回值包含干净的图片二进制与识别出的扩展名，便于上游落盘入库。
 */
final class RemoteImageFetcher
{
    /** 允许的图片扩展名 → MIME 映射 */
    private const ALLOWED_IMAGES = [
        'jpg'  => ['image/jpeg', 'image/jpg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
    ];

    /** 默认图片大小上限 5MB */
    private const DEFAULT_MAX_IMAGE_SIZE = 5 * 1024 * 1024;

    public function __construct(
        private readonly HttpUrlGuard $guard = new HttpUrlGuard(),
        private readonly int $maxImageSize = self::DEFAULT_MAX_IMAGE_SIZE,
    ) {
    }

    /**
     * 安全拉取远程图片
     *
     * @param string $url
     *
     * @return array{body:string, ext:string, content_type:string, final_url:string, size:int}
     *
     * @throws \RuntimeException SSRF / 协议 / 图片格式 / 大小校验失败
     */
    public function fetchImage(string $url): array
    {
        // SSRF 防护的完整 fetch（会自动校验 URL 结构 + DNS + IP 黑名单）
        $result = $this->guard->fetch($url, maxSize: $this->maxImageSize + 1024);

        $body        = $result['body'];
        $contentType = strtolower(trim($result['content_type']));
        $size        = strlen($body);

        if ($size > $this->maxImageSize) {
            throw new \RuntimeException('图片体积超过限制');
        }

        // 1. MIME 白名单校验（允许 image/* 且扩展名在白名单内）
        $ext = $this->matchExtensionByMime($contentType);

        // 2. Magic Number 二次校验（防 Content-Type 伪造）
        $magicExt = $this->detectMagicExtension($body);
        if ($magicExt === null) {
            throw new \RuntimeException('图片文件头无法识别');
        }
        // 若服务端返回了 MIME 但 Magic 不一致，以 Magic 为准（更可信）
        if ($ext !== null && $ext !== $magicExt) {
            throw new \RuntimeException('图片 Content-Type 与实际格式不匹配');
        }
        $ext = $magicExt;

        // 3. 必须能真的被 PHP GD 解析
        $resource = @imagecreatefromstring($body);
        if ($resource === false) {
            throw new \RuntimeException('图片无法被解析');
        }
        imagedestroy($resource);

        // 重新查准确的 content_type
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $contentType;
        if ($finfo !== false) {
            $realMime = (string) finfo_buffer($finfo, $body);
            finfo_close($finfo);
        }

        return [
            'body'         => $body,
            'ext'          => $ext,
            'content_type' => $this->mimeByExt($ext) ?: $realMime,
            'final_url'    => $result['final_url'],
            'size'         => $size,
        ];
    }

    /**
     * 根据 content-type 匹配扩展名（白名单内才返回）
     */
    private function matchExtensionByMime(string $contentType): ?string
    {
        // 去掉参数，如 "image/jpeg; charset=utf-8"
        $base = explode(';', $contentType)[0];
        $base = trim(strtolower($base));

        foreach (self::ALLOWED_IMAGES as $ext => $mimes) {
            if (in_array($base, $mimes, true)) {
                return $ext;
            }
        }
        return null;
    }

    /**
     * Magic Number 识别图片格式
     */
    private function detectMagicExtension(string $body): ?string
    {
        if (strlen($body) < 8) {
            return null;
        }
        $bytes = substr($body, 0, 12);
        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }
        // JPEG: FF D8 FF
        if (str_starts_with($bytes, "\xff\xd8\xff")) {
            return 'jpg';
        }
        // GIF: GIF87a / GIF89a
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'gif';
        }
        // WEBP: "RIFF" + 4 bytes + "WEBP"
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'webp';
        }
        return null;
    }

    /**
     * 根据扩展名返回标准 MIME
     */
    private function mimeByExt(string $ext): ?string
    {
        return self::ALLOWED_IMAGES[$ext][0] ?? null;
    }
}
