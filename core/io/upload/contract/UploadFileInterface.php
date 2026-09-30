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
namespace core\io\upload\contract;

interface UploadFileInterface
{
    /**
     * @desc: 上传文件
     *
     * @param array $options
     *
     * @return mixed
     */
    public function uploadFile(array $options): mixed;

    /**
     * @desc: 上传服务端文件
     *
     * @param string $filePath
     * @param array  $options  上传选项（支持 sub_dir 指定业务/插件子目录）
     *
     * @return mixed
     */
    public function uploadServerFile(string $filePath, array $options = []): mixed;

    /**
     * @desc: Base64上传文件
     *
     * @param string $base64
     * @param string $extension
     *
     * @return mixed
     */
    public function uploadBase64(string $base64, string $extension = 'image'): mixed;

    /**
     * @desc: 当前空间是否为私有（非公开读）
     *
     * @return bool
     */
    public function isPrivate(): bool;

    /**
     * @desc: 生成资源访问地址
     * - 公开空间：访问域名 + 相对路径（本地存储返回相对路径）
     * - 私有空间：带签名的临时直链（驱动未实现私有读时抛 UploadException）
     *
     * @param string $key 资源相对 key
     * @param int    $ttl 签名有效期（秒），0 表示取配置
     *
     * @return string
     */
    public function signedUrl(string $key, int $ttl = 0): string;

    /**
     * @desc: 删除存储对象
     * - 本地存储：$key 支持绝对文件系统路径或以存储根目录为基准的相对路径
     * - 云存储：$key 支持对象 key 或本空间域名下的绝对地址
     *
     * @param string $key 资源 key / 本地文件路径
     *
     * @return bool 对象不存在（已删除）返回 false，删除成功返回 true
     */
    public function deleteFile(string $key): bool;

    /**
     * @desc: 判断存储对象是否存在
     * - 驱动未实现时抛 UploadException，调用方需降级处理
     *
     * @param string $key 对象 key 或本空间域名下的绝对地址
     *
     * @return bool
     */
    public function exists(string $key): bool;

    /**
     * @desc: 列举存储对象 key（仅 key，不含分页细节）
     * - 驱动未实现时抛 UploadException，调用方需降级处理
     *
     * @param string $prefix 只列举该前缀下的对象
     * @param int    $limit  最多返回条数，0 表示不限
     *
     * @return array<int, string>
     */
    public function listObjects(string $prefix = '', int $limit = 0): array;
}
