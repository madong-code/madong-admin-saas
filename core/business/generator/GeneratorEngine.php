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
namespace core\business\generator;

use core\business\generator\factory\GeneratorFactory;
use core\business\generator\utils\TemplateRenderer;
use core\business\generator\utils\ConfigParser;
use support\Log;

/**
 * 代码生成器引擎
 * 负责协调各个生成器，处理整体生成流程
 */
class GeneratorEngine
{
    /**
     * @var array 配置信息
     */
    private array $config;

    /**
     * @var GeneratorFactory 生成器工厂
     */
    private GeneratorFactory $generatorFactory;

    /**
     * @var TemplateRenderer 模板渲染器
     */
    private TemplateRenderer $templateRenderer;

    /**
     * 构造函数
     * @param array|object $config 配置信息（支持数组或对象）
     */
    public function __construct($config)
    {
        // 使用 ConfigParser 解析配置
        $parser = new ConfigParser($config);
        $this->config = $parser->getConfig();

        $this->generatorFactory = new GeneratorFactory();
        $this->templateRenderer = new TemplateRenderer();

        // DI 注入：通过工厂把 TR 实例注入到所有 FileGenerator
        $this->generatorFactory->setTemplateRenderer($this->templateRenderer);
    }

    /**
     * 预览代码生成
     * @return array 预览结果
     */
    public function preview(): array
    {
        $result = [];
        $sceneTypes = $this->config['scene_types'] ?? [$this->config['scene_type'] ?? 'backend'];
        $fileTypesMap = $this->config['file_types_map'] ?? [];

        foreach ($sceneTypes as $sceneType) {
            try {
                $sceneGenerator = $this->generatorFactory->createSceneGenerator($sceneType, $this->config);
                $fileTypes = $fileTypesMap[$sceneType] ?? [];

                foreach ($fileTypes as $fileType) {
                    try {
                        $fileGenerator = $this->generatorFactory->createFileGenerator($fileType, $this->config);
                        $content = $fileGenerator->generateContent();
                        $extension = $fileGenerator->getFileExtension();
                        $filePath = $sceneGenerator->generateFilePath($fileType, $extension);

                        $result[] = [
                            'name' => basename($filePath),
                            'type' => $this->getFileType($filePath),
                            'content' => $content,
                            'file_dir' => $this->getRelativePath(dirname($filePath), $sceneType),
                            'scene_type' => $sceneType,
                        ];
                    } catch (\Exception $e) {
                        $result[] = [
                            'name' => $fileType,
                            'type' => 'error',
                            'content' => 'Error: ' . $e->getMessage(),
                            'file_dir' => '',
                            'scene_type' => $sceneType,
                        ];
                    }
                }
            } catch (\Exception $e) {
                $result[] = [
                    'name' => $sceneType,
                    'type' => 'error',
                    'content' => 'Scene Error: ' . $e->getMessage(),
                    'file_dir' => '',
                    'scene_type' => $sceneType,
                ];
            }
        }

        return $result;
    }

    /**
     * 部署代码生成
     * @return array 部署结果
     */
    public function deploy(): array
    {
        $result = [];
        $sceneTypes = $this->config['scene_types'] ?? [$this->config['scene_type'] ?? 'backend'];
        $fileTypesMap = $this->config['file_types_map'] ?? [];

        foreach ($sceneTypes as $sceneType) {
            try {
                $sceneGenerator = $this->generatorFactory->createSceneGenerator($sceneType, $this->config);
                $fileTypes = $fileTypesMap[$sceneType] ?? [];

                foreach ($fileTypes as $fileType) {
                    try {
                        $fileGenerator = $this->generatorFactory->createFileGenerator($fileType, $this->config);
                        $content = $fileGenerator->generateContent();

                        if (empty($content)) {
                            throw new \Exception('Generated content is empty for file type: ' . $fileType);
                        }

                        $extension = $fileGenerator->getFileExtension();
                        $filePath = $sceneGenerator->generateFilePath($fileType, $extension);

                        $dirPath = dirname($filePath);
                        if (!is_dir($dirPath)) {
                            mkdir($dirPath, 0777, true);
                        }

                        $written = file_put_contents($filePath, $content);
                        if ($written === false) {
                            throw new \Exception('Failed to write file: ' . $filePath);
                        }

                        $result[] = [
                            'file_type' => $fileType,
                            'file_path' => $filePath,
                            'status' => 'success',
                            'scene_type' => $sceneType,
                        ];
                    } catch (\Exception $e) {
                        $result[] = [
                            'file_type' => $fileType,
                            'status' => 'error',
                            'message' => $e->getMessage(),
                            'scene_type' => $sceneType,
                        ];
                    }
                }
            } catch (\Exception $e) {
                $result[] = [
                    'file_type' => $sceneType,
                    'status' => 'error',
                    'message' => 'Scene Error: ' . $e->getMessage(),
                    'scene_type' => $sceneType,
                ];
            }
        }

        return $result;
    }

    /**
     * 获取文件类型
     * @param string $filePath 文件路径
     * @return string 文件类型
     */
    private function getFileType(string $filePath): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        return $extension ?: 'unknown';
    }

    /**
     * 获取相对路径
     * @param string $fullPath 完整路径
     * @param string $sceneType 场景类型
     * @return string 相对路径
     */
    private function getRelativePath(string $fullPath, string $sceneType): string
    {
        $projectRoot = dirname(base_path());

        // 从 config 数组读取场景定义（由 ConfigParser 注入 _scene_type_defs）
        $sceneTypeDefs = $this->config['_scene_type_defs'] ?? [];
        $sceneDef = $sceneTypeDefs[$sceneType] ?? [];

        // 根据 template_type 判断是否为前端场景
        $templateType = $sceneDef['template_type'] ?? '';
        if ($templateType !== '') {
            // 前端场景：template_type 作为 template/ 下的目录名
            // 如 admin → 'mono'（template/mono/apps/admin/src）
            // 如 h5    → 'h5'  （template/h5/src）
            $rootDir = $this->config['template_root_dir'] ?? 'template';
            $type = $templateType;

            // 插件模式使用 plugin_sub_path，app 模式使用 default_sub_path
            $isPlugin = ($this->config['template'] ?? 'app') !== 'app';
            if ($isPlugin) {
                $subPath = $sceneDef['plugin_sub_path'] ?? $sceneDef['default_sub_path'] ?? 'plugin/{plugin}';
                $pluginName = $this->config['namespace'] ?? 'plugin';
                $subPath = str_replace('{plugin}', $pluginName, $subPath);
            } else {
                $subPath = $sceneDef['default_sub_path'] ?? '';
            }

            $subPathSegment = $subPath !== '' ? DS . $subPath : '';
            // 插件模式的 plugin_sub_path 已包含 src，不再追加
            $srcSuffix = $isPlugin ? '' : DS . 'src';
            $srcUrlSuffix = $isPlugin ? '' : '/src';
            $templateRoot = $projectRoot . DS . $rootDir . DS . $type . $subPathSegment . $srcSuffix;
            $subPathUrl = $subPath !== '' ? '/' . $subPath : '';
            $relative = str_replace($templateRoot, '', $fullPath);
            $path = $rootDir . '/' . $type . $subPathUrl . $srcUrlSuffix . $relative;
        } else {
            // 后端场景
            $backendRootName = basename(base_path());
            $path = $backendRootName . str_replace(base_path(), '', $fullPath);
        }

        $path = str_replace('\\', '/', $path);
        if (!empty($path) && substr($path, -1) !== '/') {
            $path .= '/';
        }
        return $path;
    }

    /**
     * 生成文件下载包
     * @return array 下载包信息
     * @throws \Exception
     */
    public function download(): array
    {
        $this->validateModuleName($this->config);

        $result = [];
        $sceneTypes = $this->config['scene_types'] ?? [$this->config['scene_type'] ?? 'backend'];
        $fileTypesMap = $this->config['file_types_map'] ?? [];

        $tempDir = sys_get_temp_dir() . DS . 'generator_' . uniqid();
        if (!mkdir($tempDir, 0777, true)) {
            throw new \Exception('Failed to create temporary directory');
        }

        foreach ($sceneTypes as $sceneType) {
            try {
                $sceneGenerator = $this->generatorFactory->createSceneGenerator($sceneType, $this->config);
                $fileTypes = $fileTypesMap[$sceneType] ?? [];

                foreach ($fileTypes as $fileType) {
                    try {
                        $fileGenerator = $this->generatorFactory->createFileGenerator($fileType, $this->config);
                        $content = $fileGenerator->generateContent();

                        if (empty($content)) {
                            throw new \Exception('Generated content is empty for file type: ' . $fileType);
                        }

                        $extension = $fileGenerator->getFileExtension();
                        $filePath = $sceneGenerator->generateFilePath($fileType, $extension);

                        $relativePath = str_replace(dirname(base_path()), '', $filePath);
                        $relativePath = ltrim($relativePath, DS);
                        $tempFilePath = $tempDir . DS . $relativePath;

                        $tempDirPath = dirname($tempFilePath);
                        if (!is_dir($tempDirPath)) {
                            mkdir($tempDirPath, 0777, true);
                        }

                        if (file_put_contents($tempFilePath, $content) === false) {
                            throw new \Exception('Failed to write temporary file: ' . $tempFilePath);
                        }
                    } catch (\Exception $e) {
                        Log::error('Error generating file: ' . $e->getMessage(), [
                            'file_type' => $fileType,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error in scene generation: ' . $e->getMessage(), [
                    'scene_type' => $sceneType,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $zipFileName = 'generator_' . uniqid() . '.zip';
        $zipFilePath = sys_get_temp_dir() . DS . $zipFileName;

        if ($this->createZip($tempDir, $zipFilePath)) {
            $this->cleanupDir($tempDir);

            $zipFilePath = realpath($zipFilePath);
            if (!$zipFilePath) {
                throw new \Exception('Failed to get real path for zip file');
            }

            $fileSize = filesize($zipFilePath);
            if ($fileSize < 22) {
                throw new \Exception('Generated zip file is empty');
            }

            $zipFilePath = str_replace('/', '\\', $zipFilePath);

            return [
                'status' => 'success',
                'file_path' => $zipFilePath,
                'file_name' => $zipFileName,
                'file_size' => $fileSize,
            ];
        } else {
            $this->cleanupDir($tempDir);
            throw new \Exception('Failed to create zip file');
        }
    }

    /**
     * 创建 zip 文件
     * @param string $sourceDir 源目录
     * @param string $zipFilePath zip 文件路径
     * @return bool 是否成功
     */
    private function createZip(string $sourceDir, string $zipFilePath): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        return $zip->close();
    }

    /**
     * 清理目录
     * @param string $dir 目录路径
     */
    private function cleanupDir(string $dir): void
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . DS . $file;
                if (is_dir($path)) {
                    $this->cleanupDir($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($dir);
        }
    }

    /**
     * 验证模块命名
     * @param array $config 生成配置
     * @throws \Exception 验证失败时抛出异常
     */
    private function validateModuleName(array $config): void
    {
        $configFile = __DIR__ . '/config/app.php';
        $restrictions = file_exists($configFile) ? include $configFile : [];
        $restrictions = $restrictions['module_name_restrictions'] ?? [];
        if (!isset($restrictions['enabled']) || !$restrictions['enabled']) {
            return;
        }

        $moduleName = $config['package_name'] ?? null;
        if (!$moduleName) {
            return;
        }

        if (isset($restrictions['reserved_names']) && is_array($restrictions['reserved_names'])) {
            if (in_array(strtolower($moduleName), array_map('strtolower', $restrictions['reserved_names']))) {
                throw new \Exception(sprintf('Module name "%s" is reserved and cannot be used', $moduleName));
            }
        }

        if (isset($restrictions['pattern']) && $restrictions['pattern']) {
            if (!preg_match($restrictions['pattern'], $moduleName)) {
                throw new \Exception(sprintf('Module name "%s" is invalid. It should match pattern: %s', $moduleName, $restrictions['pattern']));
            }
        }
    }
}
