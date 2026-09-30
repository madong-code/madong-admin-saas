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

    public function uploadServerFile(string $filePath): array
    {
        $file = new \SplFileInfo($filePath);
        if (!$file->isFile()) {
            throw new UploadException('请检查上传文件是否是一个有效的文件，文件不存在: ' . $filePath);
        }

        $uniqueId = hash_file('sha256', $file->getPathname());
        $object = $this->buildObjectKey($uniqueId . '.' . $file->getExtension());

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
}

