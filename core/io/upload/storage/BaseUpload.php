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

use core\io\upload\contract\UploadFileInterface;
use core\io\upload\support\StoragePathResolver;
use core\foundation\exception\handler\UploadException;
use Webman\Http\UploadFile;

abstract class BaseUpload implements UploadFileInterface
{

    protected bool $_isFileUpload;
    protected string $dirSeparator = '/';
    protected array $files = [];
    protected array $includes = [];
    protected array $excludes = [];
    protected int $singleLimit = 0;
    protected int $totalLimit = 0;
    protected int $nums = 0;
    protected array $config = [];
    protected string $algo = 'md5';
    protected ?StoragePathResolver $pathResolver = null;

    public function __construct(array $config = [])
    {
        $this->loadConfig($config);
        $this->_isFileUpload = $config['_is_file_upload'] ?? true;

        if ($this->_isFileUpload) {
            $this->files = request()->file();
            $this->verify();
        }
    }

    abstract function uploadFile(array $options): mixed;

    abstract function uploadServerFile(string $filePath): mixed;

    abstract public function uploadBase64(string $base64, string $extension = 'JPEG'): mixed;

    protected function loadConfig(array $config): void
    {
        $this->config = $config;
        // 处理 root_dir 配置（作为根目录）
        if (isset($this->config['root_dir']) && is_callable($this->config['root_dir'])) {
            $this->config['root_dir'] = (string)$this->config['root_dir']() ?: $this->config['root_dir'];
        }
        // 处理 dirname 配置（作为根目录，保持兼容）
        if (isset($this->config['dirname']) && is_callable($this->config['dirname'])) {
            $this->config['dirname'] = (string)$this->config['dirname']() ?: $this->config['dirname'];
        }
        // 处理 sub_dir 配置（作为子目录）
        if (isset($this->config['sub_dir']) && is_callable($this->config['sub_dir'])) {
            $this->config['sub_dir'] = (string)$this->config['sub_dir']() ?: $this->config['sub_dir'];
        }
    }

    /**
     * 获取路径解析器
     */
    protected function pathResolver(): StoragePathResolver
    {
        return $this->pathResolver ??= new StoragePathResolver();
    }

    /**
     * 解析业务子目录（含租户前缀）
     *
     * @param string $bizSub  业务子目录
     * @param array  $options 上传 options
     *
     * @return string
     */
    protected function resolveSubdir(string $bizSub = '', array $options = []): string
    {
        return $this->pathResolver()->resolveSubdir($bizSub, $this->config, $options);
    }

    /**
     * 构建对象 key / 相对路径
     *
     * @param string $filename 文件名
     * @param array  $options  上传 options
     *
     * @return string
     */
    protected function buildObjectKey(string $filename, array $options = []): string
    {
        $dirname = (string)($this->config['dirname'] ?? '');

        return $this->pathResolver()->buildObjectKey($dirname, $filename, $this->config, $options);
    }

    /**
     * 文件校验
     */
    protected function verify(): void
    {
        if (empty($this->files)) {
            throw new UploadException('未找到符合条件的文件资源');
        }
        foreach ($this->files as $file) {
            if (!$file->isValid()) {
                throw new UploadException('未选择文件或者无效的文件');
            }
        }
    }

    /**
     * 文件大小
     *
     * @param \Webman\Http\UploadFile $file
     *
     * @return int
     */
    protected function getSize(UploadFile $file): int
    {
        return $file->getSize();
    }

    /**
     * 计算文件哈希值
     *
     * @param string $pathname
     *
     * @return string
     */
    protected function getUniqueId(string $pathname): string
    {
        return hash_file($this->algo, $pathname);
    }
}
