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
namespace core\io\upload\storage;

use core\foundation\exception\handler\UploadException;
use OSS\Core\OssException;
use OSS\OssClient;
use Throwable;

class Oss extends BaseUpload
{
    protected ?OssClient $instance = null;

    /**
     * @desc: OSS实例
     */
    public function getInstance(): OssClient
    {
        if ($this->instance === null) {
            $this->instance = new OssClient(
                $this->config['accessKeyId'],
                $this->config['accessKeySecret'],
                $this->config['endpoint']
            );
        }
        return $this->instance;
    }

    public function uploadFile(array $options = []): array
    {
        $result = [];
        $domain = rtrim($this->config['domain'], '/');

        foreach ($this->files as $key => $file) {
            $uniqueId = $this->getUniqueId($file->getPathname());
            $saveName = $uniqueId . '.' . $file->getUploadExtension();
            $object   = $this->buildObjectKey($saveName, $options);

            $temp = [
                'key' => $key,
                'origin_name' => $file->getUploadName(),
                'save_name' => $saveName,
                'save_path' => $object,
                'url' => $domain . $this->dirSeparator . $object,
                'unique_id' => $uniqueId,
                'size' => $file->getSize(),
                'mime_type' => $file->getUploadMimeType(),
                'extension' => $file->getUploadExtension(),
                'base_path' => $this->dirSeparator . $object
            ];

            try {
                $upload = $this->getInstance()->uploadFile($this->config['bucket'], $object, $file->getPathname());
                if (!isset($upload['info']) || $upload['info']['http_code'] !== 200) {
                    throw new UploadException('Upload failed: ' . json_encode($upload));
                }
                $result[] = $temp;
            } catch (OssException $exception) {
                throw new UploadException($exception->getMessage());
            }
        }

        return $result;
    }

    /**
     * 私有空间：签发带签名的临时直链
     *
     * 传入地址非本空间域名时原样返回（外链不做签名）。
     */
    public function signedUrl(string $key, int $ttl = 0): string
    {
        $object = $this->normalizeObjectKey($key);
        if ($object === null) {
            return trim(str_replace('\\', '/', $key));
        }

        if (!$this->isPrivate()) {
            return $this->buildPublicUrl($object);
        }

        $bucket          = (string)($this->config['bucket'] ?? '');
        $domain          = rtrim((string)($this->config['domain'] ?? ''), '/');
        $accessKeyId     = (string)($this->config['accessKeyId'] ?? '');
        $accessKeySecret = (string)($this->config['accessKeySecret'] ?? '');
        $endpoint        = (string)($this->config['endpoint'] ?? '');
        if ($bucket === '' || $domain === '' || $accessKeyId === '' || $accessKeySecret === '' || $endpoint === '') {
            throw new UploadException('私有空间配置不完整：accessKeyId / accessKeySecret / bucket / domain / endpoint 均不能为空');
        }

        try {
            $url = $this->getInstance()->generatePresignedUrl(
                $bucket,
                $object,
                $this->resolveDeadline($ttl),
                OssClient::OSS_HTTP_GET
            );
        } catch (Throwable $exception) {
            throw new UploadException('OSS 私有签名失败: ' . $exception->getMessage());
        }

        // V1 签名只签 CanonicalizedResource（/{bucket}/{object}）与 Expires，与 Host 无关，
        // 因此把 SDK 生成的查询串挂到配置域名下即可，前端拿到的是自有 CDN 域名的临时直链
        $query = (string)parse_url($url, PHP_URL_QUERY);

        return $this->buildPublicUrl($object) . ($query === '' ? '' : '?' . $query);
    }

    public function uploadBase64(string $base64, string $extension = 'image'): array|bool
    {
        $base64 = explode(',', $base64);
        $uniqueId = date('YmdHis') . uniqid();
        $object = $this->buildObjectKey($uniqueId . '.' . $extension);

        try {
            $result = $this->getInstance()->putObject($this->config['bucket'], $object, base64_decode($base64[1]));
            if (!isset($result['info']) || $result['info']['http_code'] !== 200) {
                throw new UploadException('Upload failed: ' . json_encode($result));
            }
        } catch (OssException $e) {
            throw new UploadException($e->getMessage());
        }

        $imgLen = strlen($base64[1]);
        $fileSize = $imgLen - ($imgLen / 8) * 2;

        return [
            'save_path' => $object,
            'url' => $this->config['domain'] . $this->dirSeparator . $object,
            'unique_id' => $uniqueId,
            'size' => $fileSize,
            'extension' => $extension,
        ];
    }

    public function uploadServerFile(string $filePath, array $options = []): array
    {
        $file = new \SplFileInfo($filePath);
        if (!$file->isFile()) {
            throw new UploadException('请检查上传文件是否是一个有效的文件，文件不存在: ' . $filePath);
        }

        $uniqueId = hash_file('sha256', $file->getPathname());
        $object = $this->resolveTargetKey($uniqueId . '.' . $file->getExtension(), $options);

        $result = [
            'origin_name' => $file->getRealPath(),
            'save_path' => $object,
            'url' => $this->config['domain'] . $this->dirSeparator . $object,
            'unique_id' => $uniqueId,
            'size' => $file->getSize(),
            'extension' => $file->getExtension(),
        ];

        try {
            $upload = $this->getInstance()->uploadFile($this->config['bucket'], $object, $file->getRealPath());
            if (!isset($upload['info']) || $upload['info']['http_code'] !== 200) {
                throw new UploadException('Upload failed: ' . json_encode($upload));
            }
        } catch (OssException $exception) {
            throw new UploadException($exception->getMessage());
        }
        return $result;
    }

    /**
     * 判断云端对象是否存在
     *
     * @param string $key 对象 key 或本空间域名下的绝对地址
     *
     * @return bool
     * @throws UploadException
     */
    public function exists(string $key): bool
    {
        $object = $this->normalizeObjectKey($key);
        if ($object === null || $object === '') {
            throw new UploadException('OSS 资源 key 非法，无法检查对象是否存在: ' . $key);
        }

        try {
            return $this->getInstance()->doesObjectExist($this->config['bucket'], $object);
        } catch (OssException $exception) {
            throw new UploadException($exception->getMessage());
        }
    }

    /**
     * 删除云端对象
     *
     * @param string $key 对象 key 或本空间域名下的绝对地址
     *
     * @return bool 对象不存在返回 false
     * @throws UploadException
     */
    public function deleteFile(string $key): bool
    {
        $object = $this->normalizeObjectKey($key);
        if ($object === null || $object === '') {
            throw new UploadException('OSS 资源 key 非法，已拒绝删除: ' . $key);
        }

        try {
            $this->getInstance()->deleteObject($this->config['bucket'], $object);
        } catch (OssException $exception) {
            if ($exception->getErrorCode() === 'NoSuchKey') {
                return false;
            }
            throw new UploadException($exception->getMessage());
        }

        return true;
    }
}