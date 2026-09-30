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

use Aws\S3\S3Client;
use core\foundation\exception\handler\UploadException;
use Throwable;

class S3 extends BaseUpload
{
    protected ?S3Client $instance = null;

    /** 私有读签名专用客户端（endpoint 为对外访问域名，与上传客户端不同） */
    protected ?S3Client $signingInstance = null;

    public function getInstance(): S3Client
    {
        return $this->instance ??= new S3Client([
            'version' => $this->config['version'],
            'endpoint' => $this->config['endpoint'],
            'region' => $this->config['region'],
            'use_path_style_endpoint' => $this->config['use_path_style_endpoint'],
            'credentials' => [
                'key' => $this->config['key'],
                'secret' => $this->config['secret'],
            ],
        ]);
    }

    public function uploadFile(array $options = []): array
    {
        $result = [];

        foreach ($this->files as $key => $file) {
            $uniqueId = hash_file($this->algo, $file->getPathname());
            $saveName = $uniqueId . '.' . $file->getUploadExtension();
            $object   = $this->buildObjectKey($saveName, $options);

            $temp = [
                'key' => $key,
                'origin_name' => $file->getUploadName(),
                'save_name' => $saveName,
                'save_path' => $object,
                'url' => $this->config['domain'] . $this->dirSeparator . $object,
                'unique_id' => $uniqueId,
                'size' => $file->getSize(),
                'mime_type' => $file->getUploadMimeType(),
                'extension' => $file->getUploadExtension(),
                'base_path' => $this->dirSeparator . $object
            ];

            try {
                $this->getInstance()->putObject([
                    'Bucket' => $this->config['bucket'],
                    'Key' => $object,
                    'Body' => fopen($file->getPathname(), 'rb'),
                    'ACL' => $this->config['acl'],
                ]);
                $result[] = $temp;
            } catch (Throwable $exception) {
                throw new UploadException('上传文件失败: ' . $exception->getMessage());
            }
        }

        return $result;
    }

    public function uploadServerFile(string $filePath, array $options = []): array
    {
        $file = new \SplFileInfo($filePath);
        if (!$file->isFile()) {
            throw new UploadException('不是一个有效的文件: ' . $filePath);
        }

        $uniqueId = hash_file($this->algo, $file->getPathname());
        $object   = $this->resolveTargetKey($uniqueId . '.' . $file->getExtension(), $options);

        $result = [
            'origin_name' => $file->getRealPath(),
            'save_path' => $object,
            'url' => $this->config['domain'] . $this->dirSeparator . $object,
            'unique_id' => $uniqueId,
            'size' => $file->getSize(),
            'extension' => $file->getExtension(),
        ];

        try {
            $this->getInstance()->putObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $object,
                'Body' => fopen($file->getPathname(), 'rb'),
                'ACL' => $this->config['acl'],
            ]);
        } catch (Throwable $exception) {
            throw new UploadException('上传服务端文件失败: ' . $exception->getMessage());
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

        $bucket = (string)($this->config['bucket'] ?? '');
        $domain = rtrim((string)($this->config['domain'] ?? ''), '/');
        $keyId  = (string)($this->config['key'] ?? '');
        $secret = (string)($this->config['secret'] ?? '');
        if ($bucket === '' || $domain === '' || $keyId === '' || $secret === '') {
            throw new UploadException('私有空间配置不完整：key / secret / bucket / domain 均不能为空');
        }

        // SigV4 签名包含 Host，因此签名客户端的 endpoint 必须就是对外访问域名，
        // 并配合 bucket_endpoint + path_style 让 bucket 不出现在 Host 与路径中，
        // 保证签名结果与前端实际请求的地址完全一致
        $client = $this->signingInstance ??= new S3Client([
            'version' => $this->config['version'] ?? 'latest',
            'region' => $this->config['region'] ?? 'us-east-1',
            'endpoint' => $domain,
            'bucket_endpoint' => true,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $keyId,
                'secret' => $secret,
            ],
        ]);

        try {
            $command = $client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $object,
            ]);

            return (string)$client->createPresignedRequest($command, $this->resolveDeadline($ttl))->getUri();
        } catch (Throwable $exception) {
            throw new UploadException('S3 私有签名失败: ' . $exception->getMessage());
        }
    }

    public function uploadBase64(string $base64, string $extension = 'png'): array
    {
        $base64 = explode(',', $base64);
        $uniqueId = date('YmdHis') . uniqid();
        $object = $this->buildObjectKey($uniqueId . '.' . $extension);

        try {
            $this->getInstance()->putObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $object,
                'Body' => base64_decode($base64[1]),
                'ACL' => $this->config['acl'],
            ]);
        } catch (Throwable $exception) {
            throw new UploadException('上传Base64失败: ' . $exception->getMessage());
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
            throw new UploadException('S3 资源 key 非法，无法检查对象是否存在: ' . $key);
        }

        try {
            return $this->getInstance()->doesObjectExist($this->config['bucket'], $object);
        } catch (Throwable $exception) {
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
            throw new UploadException('S3 资源 key 非法，已拒绝删除: ' . $key);
        }

        try {
            $this->getInstance()->deleteObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $object,
            ]);
        } catch (Throwable $exception) {
            throw new UploadException('S3 删除失败: ' . $exception->getMessage());
        }

        return true;
    }
}

