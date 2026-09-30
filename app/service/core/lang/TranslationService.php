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

namespace app\service\core\lang;

use core\foundation\base\BaseService;

/**
 * 翻译服务类
 *
 * @author Mr.April
 * @since  1.0
 */
final class TranslationService extends BaseService
{
    /**
     * 语言包目录
     */
    protected string $translationsPath;

    /**
     * 支持的语言列表
     */
    protected array $supportedLanguages;

    public function __construct()
    {
        $this->initialize();
    }

    protected function initialize(): void
    {
        $this->translationsPath = config('translation.path', base_path('resource/translations'));
        $this->supportedLanguages = $this->getSupportedLanguages();
    }

    /**
     * 获取支持的语言列表
     *
     * @return array
     */
    public function getSupportedLanguages(): array
    {
        $languages = [];
        if (is_dir($this->translationsPath)) {
            $dirItems = scandir($this->translationsPath);
            foreach ($dirItems as $item) {
                if ($item === '.' || $item === '..' || !is_dir($this->translationsPath . DIRECTORY_SEPARATOR . $item)) {
                    continue;
                }
                $languages[] = $item;
            }
        }
        return $languages;
    }

    /**
     * 获取语言包列表
     *
     * @param string|null $language 语言类型（如en|zh_CN等）
     * @param string|null $file 翻译文件名称
     * @param string|null $keyword 搜索关键词（支持key和翻译内容）
     * @param int $page 页码
     * @param int $limit 每页数量
     *
     * @return array 语言包列表
     */
    public function getList(string|null $language = null, string|null $file = null, string|null $keyword = null, int $page = 1, int $limit = 10): array
    {
        // 获取所有翻译内容
        $allTranslations = $this->getAllTranslations($language, $file);

        // 搜索过滤
        $filteredTranslations = $this->filterTranslations($allTranslations, $keyword);

        // 分页处理
        $total = count($filteredTranslations);
        $items = array_slice($filteredTranslations, ($page - 1) * $limit, $limit);

        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'items' => $items
        ];
    }

    /**
     * 获取所有翻译内容
     *
     * @param string|null $language 语言类型
     * @param string|null $file 翻译文件名称
     *
     * @return array
     */
    protected function getAllTranslations(string|null $language = null, string|null $file = null): array
    {
        $translations = [];
        $languages = $language ? [$language] : $this->supportedLanguages;

        foreach ($languages as $lang) {
            $langPath = $this->translationsPath . DIRECTORY_SEPARATOR . $lang;
            $files = $file ? [$file] : $this->getTranslationFiles($langPath);

            foreach ($files as $fileName) {
                $filePath = $langPath . DIRECTORY_SEPARATOR . $fileName;
                $translationData = $this->loadTranslationFile($filePath);

                if (!empty($translationData)) {
                    $fileKey = pathinfo($fileName, PATHINFO_FILENAME);
                    $this->parseTranslations($translationData, $fileKey, $lang, $translations);
                }
            }
        }

        return $translations;
    }

    /**
     * 获取语言包目录下的所有翻译文件
     *
     * @param string $langPath 语言目录路径
     *
     * @return array
     */
    protected function getTranslationFiles(string $langPath): array
    {
        $files = [];
        if (is_dir($langPath)) {
            $dirItems = scandir($langPath);
            foreach ($dirItems as $item) {
                if ($item === '.' || $item === '..' || !is_file($langPath . DIRECTORY_SEPARATOR . $item) || pathinfo($item, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }
                $files[] = $item;
            }
        }
        return $files;
    }

    /**
     * 加载翻译文件内容
     *
     * @param string $filePath 文件路径
     *
     * @return array
     */
    protected function loadTranslationFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [];
        }

        $data = include $filePath;
        return is_array($data) ? $data : [];
    }

    /**
     * 解析翻译内容
     *
     * @param array $translationData 翻译数据
     * @param string $fileKey 文件键名
     * @param string $language 语言类型
     * @param array &$result 结果数组
     * @param string $prefix 前缀（用于处理嵌套key）
     */
    protected function parseTranslations(array $translationData, string $fileKey, string $language, array &$result, string $prefix = ''): void
    {
        foreach ($translationData as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            $translationKey = $fileKey . '.' . $fullKey;

            if (is_array($value)) {
                $this->parseTranslations($value, $fileKey, $language, $result, $fullKey);
            } else {
                // 确保每个翻译key都有一个条目
                if (!isset($result[$translationKey])) {
                    $result[$translationKey] = [
                        'key' => $translationKey,
                        'file' => $fileKey,
                        'translations' => []
                    ];
                }

                // 添加对应语言的翻译
                $result[$translationKey]['translations'][$language] = $value;
            }
        }
    }

    /**
     * 过滤翻译内容
     *
     * @param array $translations 翻译内容
     * @param string|null $keyword 搜索关键词
     *
     * @return array
     */
    protected function filterTranslations(array $translations, string|null $keyword = null): array
    {
        if (empty($keyword)) {
            return array_values($translations);
        }

        $filtered = [];
        foreach ($translations as $translation) {
            // 检查key是否匹配
            if (stripos($translation['key'], $keyword) !== false) {
                $filtered[] = $translation;
                continue;
            }

            // 检查翻译内容是否匹配
            foreach ($translation['translations'] as $lang => $content) {
                if (is_string($content) && stripos($content, $keyword) !== false) {
                    $filtered[] = $translation;
                    break;
                }
            }
        }

        return $filtered;
    }

    /**
     * 获取翻译文件列表
     *
     * @return array
     */
    public function getFileList(): array
    {
        $files = [];
        if (!empty($this->supportedLanguages)) {
            // 获取第一个语言的文件列表作为参考
            $firstLangPath = $this->translationsPath . DIRECTORY_SEPARATOR . $this->supportedLanguages[0];
            $fileList = $this->getTranslationFiles($firstLangPath);

            foreach ($fileList as $fileName) {
                $files[] = pathinfo($fileName, PATHINFO_FILENAME);
            }
        }
        return $files;
    }

    /**
     * 获取语言包统计信息
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $stats = [
            'languages' => count($this->supportedLanguages),
            'files' => count($this->getFileList()),
            'total_translations' => 0,
            'language_details' => []
        ];

        foreach ($this->supportedLanguages as $lang) {
            $langPath = $this->translationsPath . DIRECTORY_SEPARATOR . $lang;
            $files = $this->getTranslationFiles($langPath);
            $fileCount = count($files);
            $translationCount = 0;

            foreach ($files as $fileName) {
                $filePath = $langPath . DIRECTORY_SEPARATOR . $fileName;
                $translationData = $this->loadTranslationFile($filePath);
                $translationCount += $this->countTranslations($translationData);
            }

            $stats['language_details'][$lang] = [
                'files' => $fileCount,
                'translations' => $translationCount
            ];
            $stats['total_translations'] += $translationCount;
        }

        return $stats;
    }

    /**
     * 统计翻译条目数量
     *
     * @param array $translationData 翻译数据
     *
     * @return int
     */
    protected function countTranslations(array $translationData): int
    {
        $count = 0;
        foreach ($translationData as $value) {
            if (is_array($value)) {
                $count += $this->countTranslations($value);
            } else {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 根据key获取翻译内容
     *
     * @param string $key 翻译key
     * @param string|null $language 语言类型
     * @param array $parameters 替换参数
     *
     * @return string|null
     */
    public function getTranslationByKey(string $key, ?string $language = null, array $parameters = []): ?string
    {
        // 如果没有指定语言，使用默认语言
        if (!$language) {
            $language = $this->supportedLanguages[0] ?? 'zh_CN';
        }

        // 解析key，获取文件和实际key
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$file, $actualKey] = $parts;

        // 加载对应的翻译文件
        $filePath = $this->translationsPath . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . $file . '.php';
        $translationData = $this->loadTranslationFile($filePath);

        if (empty($translationData)) {
            return null;
        }

        // 获取翻译内容
        $value = $this->getValueByKey($translationData, $actualKey);

        if ($value === null) {
            return null;
        }

        // 替换参数
        if (!empty($parameters) && is_string($value)) {
            foreach ($parameters as $paramKey => $paramValue) {
                $value = str_replace(':' . $paramKey, $paramValue, $value);
            }
        }

        return $value;
    }

    /**
     * 根据key获取嵌套数组中的值
     *
     * @param array $data 数据数组
     * @param string $key 嵌套key
     *
     * @return mixed|null
     */
    protected function getValueByKey(array $data, string $key)
    {
        $keys = explode('.', $key);
        $current = $data;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return null;
            }
            $current = $current[$k];
        }

        return $current;
    }
}
