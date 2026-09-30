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

/**
 *
 * Cos 文件上传
 * @author Mr.April
 * @since  1.0
 */
class Cos extends BaseUpload
{
    protected ?Client $instance = null;

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

    public function uploadServerFile(string $filePath): array
    {
        $file = new \SplFileInfo($filePath);
        if (!$file->isFile()) {
            throw new UploadException('请检查上传文件是否是一个有效的文件，文件不存在' . $filePath);
        }

        $uniqueId = $this->getUniqueId($file->getPathname());
        $object   = $this->buildObjectKey($uniqueId . '.' . $file->getExtension());

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
}

