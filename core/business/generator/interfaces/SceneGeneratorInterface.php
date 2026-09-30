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
namespace core\business\generator\interfaces;

/**
 * 场景生成器接口
 * 负责生成特定场景的文件路径
 */
interface SceneGeneratorInterface
{
    /**
     * 生成文件路径
     * @param string $fileType 文件类型
     * @param string $extension 文件扩展名
     * @return string 文件路径
     */
    public function generateFilePath(string $fileType, string $extension = 'php'): string;
}
