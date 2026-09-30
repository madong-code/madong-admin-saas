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
namespace core\business\generator\file;

use core\business\generator\interfaces\FileGeneratorInterface;
use core\business\generator\utils\TemplateRenderer;

/**
 * 文件生成器抽象基类
 * 统一管理 TemplateRenderer 注入，子类只需实现 generateContent()
 */
abstract class AbstractFileGenerator implements FileGeneratorInterface
{
    /**
     * @var array 配置信息
     */
    protected array $config;

    /**
     * @var TemplateRenderer 模板渲染器
     */
    protected TemplateRenderer $templateRenderer;

    /**
     * 构造函数
     * @param array                $config           配置信息
     * @param TemplateRenderer|null $templateRenderer 模板渲染器（可选，由工厂注入实现单例）
     */
    public function __construct(array $config, ?TemplateRenderer $templateRenderer = null)
    {
        $this->config = $config;
        $this->templateRenderer = $templateRenderer ?? new TemplateRenderer();
    }

    /**
     * {@inheritDoc}
     */
    abstract public function generateContent(): string;

    /**
     * {@inheritDoc}
     */
    public function getFileExtension(): string
    {
        return 'php';
    }
}
