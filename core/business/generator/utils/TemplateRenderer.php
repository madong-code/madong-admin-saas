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
namespace core\business\generator\utils;

// 定义目录分隔符常量
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

/**
 * 模板渲染器
 * 负责处理模板文件的渲染
 */
class TemplateRenderer
{
    /**
     * @var string 模板目录路径
     */
    private string $templateDir;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->templateDir = base_path() . DS . 'core' . DS . 'business' . DS . 'generator' . DS . 'stubs';
    }

    /**
     * 渲染模板
     *
     * @param string $templatePath 模板路径
     * @param array  $data         模板数据
     *
     * @return string 渲染后的内容
     * @throws \Exception
     */
    public function render(string $templatePath, array $data): string
    {
        $templateFile = $this->templateDir . DS . $templatePath;
        
        if (!file_exists($templateFile)) {
            throw new \Exception('Template file not found: ' . $templateFile);
        }
        
        // 读取模板内容
        $content = file_get_contents($templateFile);
        
        // 替换变量
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                // 对于布尔值，转换为 TypeScript 布尔字面量
                $boolValue = $value ? 'true' : 'false';
                // 支持 {key} 格式的占位符
                $content = str_replace('{' . $key . '}', $boolValue, $content);
                // 支持 {$key} 格式的占位符
                $content = str_replace('{$' . $key . '}', $boolValue, $content);
            } elseif (is_scalar($value)) {
                // 支持 {key} 格式的占位符
                $content = str_replace('{' . $key . '}', $value, $content);
                // 支持 {$key} 格式的占位符
                $content = str_replace('{$' . $key . '}', $value, $content);
            } elseif (is_array($value)) {
                // 处理数组类型的数据，转换为 TypeScript 对象数组
                $arrayString = $this->arrayToTypeScript($value);
                $content = str_replace('{{' . $key . '}}', $arrayString, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * 将 PHP 数组转换为 TypeScript 对象数组字符串
     * @param array $array PHP 数组
     * @return string TypeScript 对象数组字符串
     */
    private function arrayToTypeScript(array $array): string
    {
        if (empty($array)) {
            return '';
        }
        
        $items = [];
        foreach ($array as $item) {
            if (is_array($item)) {
                $items[] = $this->objectToTypeScript($item);
            }
        }
        
        return implode(",\n        ", $items);
    }
    
    /**
     * 将 PHP 关联数组转换为 TypeScript 对象字符串
     * @param array $object PHP 关联数组
     * @return string TypeScript 对象字符串
     */
    private function objectToTypeScript(array $object): string
    {
        $properties = [];
        foreach ($object as $key => $value) {
            if (is_bool($value)) {
                // 对于布尔值，转换为 TypeScript 布尔字面量
                $properties[] = $key . ': ' . ($value ? 'true' : 'false');
            } elseif (is_string($value)) {
                $properties[] = $key . ': \'' . addslashes($value) . '\'';
            } elseif (is_numeric($value)) {
                $properties[] = $key . ': ' . $value;
            } elseif (is_array($value)) {
                $properties[] = $key . ': ' . $this->objectToTypeScript($value);
            }
        }
        
        return '{' . implode(', ', $properties) . '}';
    }

    /**
     * 检查模板是否存在
     * @param string $templatePath 模板路径
     * @return bool 是否存在
     */
    public function templateExists(string $templatePath): bool
    {
        $templateFile = $this->templateDir . DS . $templatePath;
        return file_exists($templateFile);
    }
}
