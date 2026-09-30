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

    abstract function uploadServerFile(string $filePath, array $options = []): mixed;

    abstract public function uploadBase64(string $base64, string $extension = 'JPEG'): mixed;

    /**
     * 当前空间是否为私有（非公开读）
     */
    public function isPrivate(): bool
    {
        return !empty($this->config['is_private']);
    }

    /**
     * 生成资源访问地址
     *
     * 公开空间返回 域名 + 相对路径；私有空间由各驱动签发临时直链，未实现的驱动直接抛异常。
     */
    public function signedUrl(string $key, int $ttl = 0): string
    {
        if (!$this->isPrivate()) {
            return $this->buildPublicUrl($key);
        }

        throw new UploadException('当前存储驱动未实现私有读:' . static::class);
    }

    /**
     * 删除存储对象
     *
     * 默认不支持，由各驱动按自身协议实现。
     */
    public function deleteFile(string $key): bool
    {
        throw new UploadException('当前存储驱动未实现资源删除:' . static::class);
    }

    /**
     * 拼接公开访问地址（未配置域名时返回相对路径）
     */
    protected function buildPublicUrl(string $key): string
    {
        $key    = ltrim(str_replace('\\', '/', $key), '/');
        $domain = rtrim((string)($this->config['domain'] ?? ''), '/');

        return $domain === '' ? $this->dirSeparator . $key : $domain . $this->dirSeparator . $key;
    }

    /**
     * 将传入地址归一化为对象 key
     *
     * 支持相对路径、以 / 开头的相对路径、协议相对地址，以及本空间域名下的绝对地址；
     * 非本空间地址（外链）返回 null，由调用方保持原地址不变。
     *
     * @param string $key 资源地址或相对 key
     *
     * @return string|null
     */
    protected function normalizeObjectKey(string $key): ?string
    {
        $key = trim(str_replace('\\', '/', $key));
        if ($key === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $key) !== 1) {
            return ltrim($key, '/');
        }

        // 绝对地址 / 协议相对地址：仅本空间域名下的资源才可签名
        $url        = str_starts_with($key, '//') ? 'http:' . $key : $key;
        $domain     = rtrim((string)($this->config['domain'] ?? ''), '/');
        $domainHost = $domain === '' ? '' : (string)parse_url($domain, PHP_URL_HOST);
        $urlHost    = (string)parse_url($url, PHP_URL_HOST);
        if ($domainHost === '' || $urlHost === '' || strcasecmp($domainHost, $urlHost) !== 0) {
            return null;
        }

        return ltrim((string)parse_url($url, PHP_URL_PATH), '/');
    }

    /**
     * 计算签名到期时间戳（对齐整点：同一小时内同一 key 签名一致，避免 CDN 缓存失效）
     */
    protected function resolveDeadline(int $ttl = 0): int
    {
        $ttl = $ttl > 0 ? $ttl : (int)($this->config['ttl'] ?? 0);
        $ttl = $ttl > 0 ? $ttl : 3600;

        return (int)(ceil((time() + $ttl) / 3600) * 3600);
    }

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
     * 解析业务子目录
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
     * 解析本次上传的目标对象 key
     *
     * 默认按 {dirname}/{sub_dir}/{filename} 规则生成（filename 由各驱动按自身哈希策略给出）；
     * 迁移 / 回填场景可通过 options.object_key 指定精确 key：历史文件的 key 不能改名，
     * 否则数据库里的历史引用会全部失效。
     *
     * @param string $filename 驱动生成的默认文件名
     * @param array  $options  上传 options（可含 object_key 指定精确 key）
     *
     * @return string
     */
    protected function resolveTargetKey(string $filename, array $options = []): string
    {
        $explicit = trim(str_replace('\\', '/', (string)($options['object_key'] ?? '')), '/');

        return $explicit !== '' ? $explicit : $this->buildObjectKey($filename, $options);
    }

    /**
     * 判断存储对象是否存在
     *
     * 默认未实现，由各驱动按自身协议实现；调用方应捕获 UploadException 并降级处理
     * （例如退化为「直接覆盖上传」），不要假设所有驱动都支持。
     *
     * @param string $key 对象 key 或本空间域名下的绝对地址
     *
     * @return bool
     * @throws UploadException 驱动未实现时抛出
     */
    public function exists(string $key): bool
    {
        throw new UploadException('当前存储驱动未实现对象存在性检查:' . static::class);
    }

    /**
     * 列举存储对象 key
     *
     * 默认未实现，由各驱动按自身协议实现（云厂商列举接口差异较大，不做统一抽象，
     * 未实现的驱动直接抛异常，由调用方降级）。仅返回对象 key，不分页细节。
     *
     * @param string $prefix 只列举该前缀下的对象
     * @param int    $limit  最多返回条数，0 表示不限
     *
     * @return array<int, string>
     * @throws UploadException 驱动未实现时抛出
     */
    public function listObjects(string $prefix = '', int $limit = 0): array
    {
        throw new UploadException('当前存储驱动未实现对象列举:' . static::class);
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
