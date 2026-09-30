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
     *
     * @return mixed
     */
    public function uploadServerFile(string $filePath): mixed;

    /**
     * @desc: Base64上传文件
     *
     * @param string $base64
     * @param string $extension
     *
     * @return mixed
     */
    public function uploadBase64(string $base64, string $extension = 'image'): mixed;
}
