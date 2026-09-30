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
use Qcloud\Cos\Client;
use Throwable;

/**
 *
 * Cos 文件上传
 * @author Mr.April
 * @since  1.0
 */
class Cos extends BaseUpload
{
    protected ?Client $instance = null;

    /** 私有读签名专用客户端（domain 为对外访问域名，与上传客户端不同） */
    protected ?Client $signingInstance = null;

    /**
     * 获取实例
     * @return \Qcloud\Cos\Client|null
     */
    public function getInstance(): ?Client
    {
        if (is_null($this->instance)) {
            $this->instance = new Client([
                'region' => $this->config['region'] ?? 'ap-shanghai',
                'schema' => 'https',
                'credentials' => [
                    'secretId' => $this->config['secretId'],
                    'secretKey' => $this->config['secretKey'],
                ],
            ]);
        }
        return $this->instance;
    }

    /**
     * 文件上传
     * @param array $options
     *
     * @return array
     */
    public function uploadFile(array $options = []): array
    {
        $result = [];
        $domain = trim($this->config['domain']);

        foreach ($this->files as $key => $file) {
            $uniqueId = $this->getUniqueId($file->getPathname());
            $saveName = $uniqueId . '.' . $file->getUploadExtension();
            $object   = $this->buildObjectKey($saveName, $options);

            $this->getInstance()->putObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $object,
                'Body' => fopen($file->getPathname(), 'rb'),
            ]);

            $result[] = [
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
        }

        return $result;
    }

    public function uploadServerFile(string $filePath, array $options = []): array
    {
        $file = new \SplFileInfo($filePath);
        if (!$file->isFile()) {
            throw new UploadException('请检查上传文件是否是一个有效的文件，文件不存在' . $filePath);
        }

        $uniqueId = $this->getUniqueId($file->getPathname());
        $object   = $this->resolveTargetKey($uniqueId . '.' . $file->getExtension(), $options);

        $this->getInstance()->putObject([
            'Bucket' => $this->config['bucket'],
            'Key' => $object,
            'Body' => fopen($file->getPathname(), 'rb'),
        ]);

        return [
            'origin_name' => $file->getRealPath(),
            'save_path' => $object,
            'url' => $this->config['domain'] . $this->dirSeparator . $object,
            'unique_id' => $uniqueId,
            'size' => $file->getSize(),
            'extension' => $file->getExtension(),
        ];
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

        $bucket    = (string)($this->config['bucket'] ?? '');
        $domain    = rtrim((string)($this->config['domain'] ?? ''), '/');
        $secretId  = (string)($this->config['secretId'] ?? '');
        $secretKey = (string)($this->config['secretKey'] ?? '');
        $region    = (string)($this->config['region'] ?? '');
        if ($bucket === '' || $domain === '' || $secretId === '' || $secretKey === '') {
            throw new UploadException('私有空间配置不完整：secretId / secretKey / bucket / domain 均不能为空');
        }

        // COS 签名默认包含 Host，签名客户端必须使用对外访问域名作为 domain，
        // 否则签名与前端实际请求的 Host 不一致会返回 403
        $host = (string)preg_replace('#^https?://#i', '', $domain);

        try {
            $client = $this->signingInstance ??= new Client([
                'region' => $region === '' ? 'ap-shanghai' : $region,
                'schema' => 'https',
                'domain' => $host,
                'credentials' => [
                    'secretId' => $secretId,
                    'secretKey' => $secretKey,
                ],
            ]);

            return rtrim($client->getObjectUrl($bucket, $object, gmdate('Y-m-d\TH:i:s\Z', $this->resolveDeadline($ttl))), '&');
        } catch (Throwable $exception) {
            throw new UploadException('COS 私有签名失败: ' . $exception->getMessage());
        }
    }

    public function uploadBase64(string $base64, string $extension = 'png'): array
    {
        $base64 = explode(',', $base64);
        $uniqueId = date('YmdHis') . uniqid();
        $object = $this->buildObjectKey($uniqueId . '.' . $extension);

        $this->getInstance()->putObject([
            'Bucket' => $this->config['bucket'],
            'Key' => $object,
            'Body' => base64_decode($base64[1]),
        ]);

        $fileSize = strlen(base64_decode($base64[1]));

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
            throw new UploadException('COS 资源 key 非法，无法检查对象是否存在: ' . $key);
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
            throw new UploadException('COS 资源 key 非法，已拒绝删除: ' . $key);
        }

        try {
            $this->getInstance()->deleteObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $object,
            ]);
        } catch (Throwable $exception) {
            throw new UploadException('COS 删除失败: ' . $exception->getMessage());
        }

        return true;
    }
}