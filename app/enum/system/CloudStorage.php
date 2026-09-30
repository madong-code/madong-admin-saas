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

namespace app\enum\system;

enum CloudStorage: string
{

    case LOCAL = 'local';
    case OSS = 'oss';
    case COS = 'cos';
    case QINIU = 'qiniu';
    case S3 = 's3';

    /**
     * 获取人类可读标签
     *
     * @param bool $english 是否返回英文标签
     *
     * @return string
     */
    public function label(bool $english = false): string
    {
        return match ($this) {
            self::LOCAL => '私有云(本地)',
            self::OSS => '阿里云',
            self::COS =>  '腾讯云',
            self::QINIU => '七牛云',
            self::S3 =>  '亚马逊(S3)',
        };
    }
}
