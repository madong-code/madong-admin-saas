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
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;

class Qiniu extends BaseUpload
{
    protected ?UploadManager $instance = null;
    protected ?string $uploadToken = null;
    protected ?BucketManager $bucketManager = null;

    public function getInstance(): UploadManager
    {
        return $this->instance ??= new UploadManager();
    }

    public function getBucketManager(): BucketManager
    {
        return $this->bucketManager ??= new BucketManager(new Auth($this->config['accessKey'], $this->config['secretKey']));
    }

    public function getUploadToken(): string
    {
        return $this->uploadToken ??= (new Auth($this->config['accessKey'], $this->config['secretKey']))->uploadToken($this->config['bucket']);
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
                'key'         => $key,
                'origin_name' => $file->getUploadName(),
                'save_name'   => $saveName,
                'save_path'   => $object,
                'url'         => $domain . $this->dirSeparator . $object,
                'unique_id'   => $uniqueId,
                'size'        => $file->getSize(),
                'mime_type'   => $file->getUploadMimeType(),
                'extension'   => $file->getUploadExtension(),
                'base_path'   => $this->dirSeparator . $object
            ];

            try {
                [$ret, $err] = $this->getInstance()->putFile($this->getUploadToken(), $object, $file->getPathname());
                if ($err) {
                    throw new UploadException((string)$err->message());
                }
                $result[] = $temp;
            } catch (\Throwable $exception) {
                throw new UploadException($exception->getMessage());
            }
        }

        return $result;
    }

    public function uploadServerFile(string $filePath, array $options = []): array
    {
        $file = new \SplFileInfo($filePath);
        if (!$file->isFile()) {
            throw new UploadException('请检查上传文件是否是一个有效的文件，文件不存在: ' . $filePath);
        }

        $uniqueId = hash_file('sha256', $file->getPathname());
        $object   = $this->resolveTargetKey($uniqueId . '.' . $file->getExtension(), $options);

        $result = [
            'origin_name' => $file->getRealPath(),
            'save_path'   => $object,
            'url'         => $this->config['domain'] . $this->dirSeparator . $object,
            'unique_id'   => $uniqueId,
            'size'        => $file->getSize(),
            'mime_type'   => mime_content_type($file->getPathname()) ?: 'application/octet-stream',
            'extension'   => $file->getExtension(),
            'base_path'   => $this->dirSeparator . $object,
        ];

        $token = $this->getUploadToken();
        if ($file->getSize() === 0) {
            // 零字节文件：SDK 的 putFile() 内部会执行 fread($file, 0) 而报错，改用二进制内容上传空对象
            [$ret, $err] = $this->getInstance()->put($token, $object, '');
        } else {
            // putFile 在 >4MB 时自动切换分片上传，无需显式判断
            [$ret, $err] = $this->getInstance()->putFile($token, $object, $file->getPathname());
        }
        if ($err) {
            throw new UploadException((string)$err->message());
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
            throw new UploadException('七牛资源 key 非法，无法检查对象是否存在: ' . $key);
        }

        [, $err] = $this->getBucketManager()->stat($this->config['bucket'], $object);

        if ($err) {
            // 612：文件不存在
            if ((int)$err->code() === 612) {
                return false;
            }
            throw new UploadException((string)$err->message());
        }

        return true;
    }

    /**
     * 列举云端对象 key
     *
     * @param string $prefix 只列举该前缀下的对象
     * @param int    $limit  最多返回条数，0 表示不限
     *
     * @return array<int, string>
     * @throws UploadException
     */
    public function listObjects(string $prefix = '', int $limit = 0): array
    {
        $keys   = [];
        $marker = null;

        do {
            $size = $limit > 0 ? min(1000, $limit - count($keys)) : 1000;
            [$ret, $err] = $this->getBucketManager()->listFiles($this->config['bucket'], $prefix, $marker, $size);
            if ($err) {
                throw new UploadException((string)$err->message());
            }

            foreach ($ret['items'] ?? [] as $item) {
                $keys[] = (string)$item['key'];
            }

            $marker = $ret['marker'] ?? null;
        } while (!empty($marker) && ($limit === 0 || count($keys) < $limit));

        return $keys;
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
        $accessKey = (string)($this->config['accessKey'] ?? '');
        $secretKey = (string)($this->config['secretKey'] ?? '');
        if ($bucket === '' || $domain === '' || $accessKey === '' || $secretKey === '') {
            throw new UploadException('私有空间配置不完整：bucket / domain / accessKey / secretKey 均不能为空');
        }

        $baseUrl = $this->buildPublicUrl($object);
        $expires = $this->resolveDeadline($ttl) - time();

        return (new Auth($accessKey, $secretKey))->privateDownloadUrl($baseUrl, $expires);
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
            throw new UploadException('七牛资源 key 非法，已拒绝删除: ' . $key);
        }

        [$ret, $err] = $this->getBucketManager()->delete($this->config['bucket'], $object);
        if ($err) {
            // 612：文件不存在，按已删除处理
            if ((int)$err->code() === 612) {
                return false;
            }
            throw new UploadException((string)$err->message());
        }

        return true;
    }

    public function uploadBase64(string $base64, string $extension = 'png'): array
    {
        $base64   = explode(',', $base64);
        $uniqueId = date('YmdHis') . uniqid();
        $object   = $this->buildObjectKey($uniqueId . '.' . $extension);

        [$ret, $err] = $this->getInstance()->put($this->getUploadToken(), $object, base64_decode($base64[1]));
        if ($err) {
            throw new UploadException((string)$err->message());
        }

        $imgLen   = strlen($base64[1]);
        $fileSize = $imgLen - ($imgLen / 8) * 2;

        return [
            'save_path' => $object,
            'url'       => $this->config['domain'] . $this->dirSeparator . $object,
            'unique_id' => $uniqueId,
            'size'      => $fileSize,
            'extension' => $extension,
        ];
    }
}