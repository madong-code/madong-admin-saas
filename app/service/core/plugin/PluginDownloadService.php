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
namespace app\service\core\plugin;

use core\foundation\exception\handler\PluginException;
use core\foundation\tool\Util;
use ZipArchive;
use GuzzleHttp\Client;

/**
 * 插件下载服务
 * 职责：下载插件ZIP包到插件目录并解压
 */
final class PluginDownloadService extends PluginBaseService
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取下载信息
     *
     * @param string $pluginCode 插件编码
     * @return array 下载信息
     */
    public function getDownloadInfo(string $pluginCode): array
    {
        $pluginDir = $this->plugin_path . DIRECTORY_SEPARATOR . $pluginCode;

        // 本地已存在直接返回
        if (is_dir($pluginDir)) {
            $config = $this->getPluginConfig($pluginCode);
            return [
                'plugin_code' => $pluginCode,
                'source'      => 'local',
                'version'     => $config['version'] ?? '1.0.0',
                'downloaded'  => true,
            ];
        }

        // 本地不存在时返回本地信息，实际下载由安装流程处理
        return [
            'plugin_code' => $pluginCode,
            'source'      => 'remote',
            'downloaded'  => false,
        ];
    }

    /**
     * 下载插件到插件目录并解压
     *
     * @param string $pluginCode 插件编码（name）
     * @param string $downloadUrl 下载链接
     *
     * @return array 返回插件信息
     * @throws PluginException
     */
    public function downloadAndExtract(string $pluginCode, string $downloadUrl): array
    {
        // 1. 准备下载路径
        $pluginDir = $this->plugin_path . DIRECTORY_SEPARATOR . $pluginCode;
        $zipFile    = $this->plugin_path . DIRECTORY_SEPARATOR . $pluginCode . '.zip';

        // 2. 创建插件目录（如果不存在）
        if (!is_dir($this->plugin_path) && !mkdir($this->plugin_path, 0755, true)) {
            throw new PluginException("无法创建插件目录: {$this->plugin_path}");
        }

        // 如果插件目录已存在，先删除
        if (is_dir($pluginDir)) {
            $this->deleteDirectory($pluginDir);
        }

        // 3. 下载ZIP文件
        $this->downloadZipFile($downloadUrl, $zipFile);

        // 4. 解压ZIP到插件目录
        $this->extractZipFile($zipFile, $this->plugin_path);

        // 5. 读取插件配置信息
        $pluginInfo = $this->readPluginInfo($pluginCode);

        return [
            'plugin_code' => $pluginCode,
            'plugin_dir'  => $pluginDir,
            'plugin_info' => $pluginInfo,
        ];
    }

    /**
     * 递归删除目录
     *
     * @param string $dir 目录路径
     * @return void
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * 移动目录内容
     *
     * @param string $source 源目录
     * @param string $target 目标目录
     * @return void
     */
    private function moveDirectoryContents(string $source, string $target): void
    {
        // 确保目标目录存在
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $files = array_diff(scandir($source), ['.', '..']);
        foreach ($files as $file) {
            $sourcePath = $source . DIRECTORY_SEPARATOR . $file;
            $targetPath = $target . DIRECTORY_SEPARATOR . $file;

            if (is_dir($sourcePath)) {
                // 递归移动子目录
                $this->moveDirectoryContents($sourcePath, $targetPath);
            } else {
                // 移动文件
                rename($sourcePath, $targetPath);
            }
        }
    }

    /**
     * 下载ZIP文件
     *
     * @param string $url  下载URL
     * @param string $file 保存路径
     *
     * @return void
     * @throws PluginException
     */
    private function downloadZipFile(string $url, string $file): void
    {
        $client = new Client([
            'timeout' => 300, // 下载大文件设置更长的超时时间
            'verify'  => config('madong.market_verify_ssl', true),
        ]);

        $response = $client->get($url, ['sink' => $file]);
        $status   = $response->getStatusCode();

        if ($status !== 200) {
            throw new PluginException("下载失败，HTTP状态码: {$status}");
        }

        // 检查文件是否下载成功
        if (!file_exists($file) || filesize($file) === 0) {
            throw new PluginException("下载的文件为空或不存在: {$file}");
        }
    }

    /**
     * 解压ZIP文件
     *
     * @param string $zipFile   ZIP文件路径
     * @param string $extractTo 解压目标路径（插件目录）
     *
     * @return void
     * @throws PluginException
     */
    private function extractZipFile(string $zipFile, string $extractTo): void
    {
        $hasZipArchive = class_exists(ZipArchive::class, false);

        if (!$hasZipArchive) {
            $cmd = $this->getUnzipCmd($zipFile, $extractTo);
            if (!$cmd) {
                throw new PluginException('请给php安装zip模块或者给系统安装unzip命令');
            }
            if (!function_exists('proc_open')) {
                throw new PluginException('请解除proc_open函数的禁用或者给php安装zip模块');
            }
        }

        Util::pauseFileMonitor();
        try {
            // 创建临时解压目录
            $tempDir = $extractTo . '_temp';
            if (is_dir($tempDir)) {
                $this->deleteDirectory($tempDir);
            }
            mkdir($tempDir, 0755, true);

            // 解压zip到临时目录
            if ($hasZipArchive) {
                $zip = new ZipArchive();
                if ($zip->open($zipFile) === true) {
                    if (!$zip->extractTo($tempDir)) {
                        throw new PluginException("解压失败: {$zipFile}");
                    }
                    $zip->close();
                } else {
                    throw new PluginException("无法打开压缩包: {$zipFile}");
                }
            } else {
                $this->unzipWithCmd($cmd);
            }

            // 处理解压后的目录结构
            $extractedFiles = scandir($tempDir);
            $extractedFiles = array_diff($extractedFiles, ['.', '..']);

            // 如果只有一个目录，说明ZIP包含了一个根目录（如 nexus/），需要移动内容
            if (count($extractedFiles) === 1) {
                $onlyDir = $tempDir . DIRECTORY_SEPARATOR . $extractedFiles[0];
                if (is_dir($onlyDir)) {
                    // 移动目录内容到目标位置
                    $this->moveDirectoryContents($onlyDir, $extractTo);
                    $this->deleteDirectory($onlyDir);
                } else {
                    // 是单个文件，直接移动
                    rename($onlyDir, $extractTo . DIRECTORY_SEPARATOR . $extractedFiles[0]);
                }
            } else {
                // 多个文件，直接移动所有内容
                foreach ($extractedFiles as $file) {
                    rename($tempDir . DIRECTORY_SEPARATOR . $file, $extractTo . DIRECTORY_SEPARATOR . $file);
                }
            }

            // 删除临时目录
            $this->deleteDirectory($tempDir);

            // 删除临时zip文件
            if (file_exists($zipFile)) {
                unlink($zipFile);
            }
        } finally {
            Util::resumeFileMonitor();
        }
    }

    /**
     * 读取插件配置信息
     *
     * @param string $pluginCode 插件编码
     *
     * @return array 插件信息
     * @throws PluginException
     */
    private function readPluginInfo(string $pluginCode): array
    {
        $infoFile = $this->plugin_path . DIRECTORY_SEPARATOR . $pluginCode . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'info.php';

        if (!file_exists($infoFile)) {
            throw new PluginException("插件配置文件不存在: {$infoFile}");
        }

        $info = include $infoFile;

        if (!is_array($info)) {
            throw new PluginException("插件配置文件格式错误: {$infoFile}");
        }

        return $info;
    }

    /**
     * 获取系统支持的解压命令
     *
     * @param string $zipFile   ZIP文件路径
     * @param string $extractTo 解压目标路径
     *
     * @return string|null 解压命令
     */
    private function getUnzipCmd(string $zipFile, string $extractTo): ?string
    {
        $safeZip = escapeshellarg($zipFile);
        $safeExtract = escapeshellarg($extractTo);

        if ($cmd = $this->findCmd('unzip')) {
            return escapeshellcmd($cmd) . " -o -qq {$safeZip} -d {$safeExtract}";
        }

        if ($cmd = $this->findCmd('7z')) {
            return escapeshellcmd($cmd) . " x -bb0 -y {$safeZip} -o{$safeExtract}";
        }

        if ($cmd = $this->findCmd('7zz')) {
            return escapeshellcmd($cmd) . " x -bb0 -y {$safeZip} -o{$safeExtract}";
        }

        return null;
    }

    /**
     * 使用命令行解压ZIP文件
     *
     * @param string $cmd 解压命令
     *
     * @return void
     * @throws PluginException
     */
    private function unzipWithCmd(string $cmd): void
    {
        $desc = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        $handler = proc_open($cmd, $desc, $pipes);
        if (!is_resource($handler)) {
            throw new PluginException("解压zip时出错: proc_open调用失败");
        }

        $err = fread($pipes[2], 1024);
        fclose($pipes[2]);
        proc_close($handler);

        if (!empty($err)) {
            throw new PluginException("解压zip时出错: {$err}");
        }
    }

    /**
     * 查找系统命令
     *
     * @param string      $name      命令名称
     * @param string|null $default   默认值
     * @param array       $extraDirs 额外搜索目录
     *
     * @return string|null 命令路径
     */
    private function findCmd(string $name, ?string $default = null, array $extraDirs = []): ?string
    {
        if (ini_get('open_basedir')) {
            $searchPath = array_merge(explode(PATH_SEPARATOR, ini_get('open_basedir')), $extraDirs);
            $dirs = [];

            foreach ($searchPath as $path) {
                if (@is_dir($path)) {
                    $dirs[] = $path;
                } else {
                    if (basename($path) === $name && @is_executable($path)) {
                        return $path;
                    }
                }
            }
        } else {
            $dirs = array_merge(
                explode(PATH_SEPARATOR, getenv('PATH') ?: getenv('Path')),
                $extraDirs
            );
        }

        $suffixes = [''];
        if ('\\' === DIRECTORY_SEPARATOR) {
            $pathExt = getenv('PATHEXT');
            $suffixes = array_merge(
                $pathExt ? explode(PATH_SEPARATOR, $pathExt) : ['.exe', '.bat', '.cmd', '.com'],
                $suffixes
            );
        }

        foreach ($suffixes as $suffix) {
            foreach ($dirs as $dir) {
                $file = $dir . DIRECTORY_SEPARATOR . $name . $suffix;
                if (@is_file($file) && ('\\' === DIRECTORY_SEPARATOR || @is_executable($file))) {
                    return $file;
                }
            }
        }

        return $default;
    }
}