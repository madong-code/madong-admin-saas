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

use core\business\generator\utils\TemplateRenderer;

/**
 * 路由生成器
 * 负责生成 Swagger 自动注册的路由配置文件
 */
class RouteGenerator extends AbstractFileGenerator
{
    public function __construct(array $config, ?TemplateRenderer $templateRenderer = null)
    {
        parent::__construct($config, $templateRenderer);
    }

    public function generateContent(): string
    {
        $className = $this->config['class_name'] ?? 'DefaultModel';
        $packageName = $this->config['package_name'] ?? 'default';

        $data = [
            'class_name'   => $className,
            'package_name' => $packageName,
        ];

        return $this->templateRenderer->render('server/route/route.stub', $data);
    }

    public function getFileExtension(): string
    {
        return 'php';
    }
}
