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

    public function uploadServerFile(string $filePath): array
    {
        $file = new \SplFileInfo($filePath);
        if (!$file->isFile()) {
            throw new UploadException('不是一个有效的文件: ' . $filePath);
        }

        $uniqueId = hash_file($this->algo, $file->getPathname());
        $object   = $this->buildObjectKey($uniqueId . '.' . $file->getExtension());

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
}

