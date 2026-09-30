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
namespace core\io\upload\storage;

use core\foundation\exception\handler\UploadException;

/**
 * 本地上传
 *
 * @author Mr.April
 * @since  1.0
 */
class Local extends BaseUpload
{
    public function uploadFile(array $options = []): array
    {
        $result   = [];
        $root     = $this->getRootPath();
        $rootDir  = $this->config['root_dir'] ?? $this->config['dirname'] ?? ''; // 根目录
        $basePath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!$this->createDir($basePath)) {
            throw new UploadException('文件夹创建失败，请核查是否有对应权限。');
        }
        $domain = rtrim($this->config['domain'] ?? '', '/');
        foreach ($this->files as $key => $file) {
            $uniqueId     = $this->getUniqueId($file->getPathname());
            $saveFilename = $uniqueId . '.' . $file->getUploadExtension();
            $savePath     = $basePath . $saveFilename;
            $url          = $domain . $this->dirSeparator . $saveFilename;
            $basePathUrl  = $this->dirSeparator . $saveFilename;
            
            if (!empty($rootDir)) {
                // 生成子目录
                $subDir = $this->getSubdir($options);
                $fullDir = $rootDir . ($subDir ? $this->dirSeparator . $subDir : '');
                $savePath = $root . $this->dirSeparator . $fullDir . $this->dirSeparator . $saveFilename;
                $url      = $domain . $this->dirSeparator . $fullDir . $this->dirSeparator . $saveFilename;
                $basePathUrl = $this->dirSeparator . $fullDir . $this->dirSeparator . $saveFilename;
            }
            
            // 确保目录存在
            $dirPath = dirname($savePath);
            if (!is_dir($dirPath) && !$this->createDir($dirPath)) {
                throw new UploadException('子目录创建失败，请核查是否有对应权限。');
            }
            
            $temp = [
                'key'         => $key,
                'origin_name' => $file->getUploadName(),
                'save_name'   => $saveFilename,
                'save_path'   => $savePath,
                'url'         => $url,
                'unique_id'   => $uniqueId,
                'size'        => $file->getSize(),
                'mime_type'   => $file->getUploadMimeType(),
                'extension'   => $file->getUploadExtension(),
                'base_path'   => $basePathUrl
            ];
            // Windows 下 PHP 临时目录（upload_tmp_dir）与项目可能不在同一分区，
            // 此时 rename 会以「Cross-device link not permitted」失败，回落到拷贝后删除源文件
            $source = $file->getPathname();
            if (!@rename($source, $savePath)) {
                if (!@copy($source, $savePath)) {
                    throw new UploadException('文件保存失败，请核查目录权限：' . $savePath);
                }
                @unlink($source);
            }
            $result[] = $temp;
        }
        return $result;
    }
    
    /**
     * 获取子目录
     */
    protected function getSubdir(array $options = []): string
    {
        // 优先使用 options 中的子目录（支持 sub_dir 和 subdirectory、subdir 两种键名）
        if (isset($options['sub_dir']) && !empty($options['sub_dir'])) {
            $bizSub = (string)$options['sub_dir'];
        } elseif (isset($options['subdirectory']) && !empty($options['subdirectory'])) {
            $bizSub = (string)$options['subdirectory'];
        } elseif (isset($options['subdir']) && !empty($options['subdir'])) {
            $bizSub = (string)$options['subdir'];
        } elseif (isset($this->config['sub_dir']) && !empty($this->config['sub_dir'])) {
            // 其次使用配置中的 sub_dir 作为子目录
            $bizSub = (string)$this->config['sub_dir'];
        } elseif (isset($this->config['directory']) && !empty($this->config['directory'])) {
            $bizSub = (string)$this->config['directory'];
        } else {
            // 默认使用年月格式 YYYYMM
            $subdirFormat = $this->config['subdir_format'] ?? 'Ym';
            $bizSub = match ($subdirFormat) {
                'Ymd' => date('Ymd'),
                'Ym' => date('Ym'),
                default => date('Ym')
            };
        }

        return $this->resolveSubdir(trim($bizSub, '/\\'), $options);
    }

    protected function createDir(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }
        $parent = dirname($path);
        if (!is_dir($parent) && !$this->createDir($parent)) {
            return false;
        }
        return mkdir($path, 0755, true);
    }

    private function getRootPath(): string
    {
        $root = $this->config['root'] ?? '';
        return match ($root) {
            'public' => public_path(),
            'runtime' => runtime_path(),
            'default' => runtime_path(),
            default => public_path(),
        };
    }

    /**
     * 删除本地文件
     *
     * 只允许删除存储根目录内的文件，越界（含 ../ 穿越）一律拒绝。
     *
     * @param string $key 绝对文件系统路径，或以存储根目录为基准的相对路径
     *
     * @return bool 文件不存在（已删除）返回 false
     * @throws UploadException 路径非法或越界
     */
    public function deleteFile(string $key): bool
    {
        $path = $this->resolveLocalPath($key);
        if ($path === null) {
            throw new UploadException('本地资源路径非法或越界，已拒绝删除: ' . $key);
        }
        if (!is_file($path)) {
            return false;
        }

        return @unlink($path);
    }

    /**
     * 归一化本地路径并校验是否位于存储根目录内
     *
     * @return string|null 命中根目录时返回归一化路径，越界或非法返回 null
     */
    private function resolveLocalPath(string $key): ?string
    {
        $key = trim(str_replace('\\', '/', $key));
        if ($key === '') {
            return null;
        }

        $root = $this->normalizedRoot();
        $candidates = [$key];
        // 以 / 开头的 key 在类 Unix 系统下会被当作绝对路径，这里补充相对根目录的候选
        if (str_starts_with($key, '/')) {
            $candidates[] = $root . '/' . ltrim($key, '/');
        }

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real === false) {
                // 文件已不存在：仅当所在目录确实位于根目录内才按“已删除”处理
                $dir = realpath(dirname($candidate));
                if ($dir !== false && $this->isWithin(str_replace('\\', '/', $dir), $root)) {
                    return $candidate;
                }
                continue;
            }
            $normalized = str_replace('\\', '/', $real);
            if ($this->isWithin($normalized, $root)) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * 归一化后的存储根目录（去尾斜杠）
     */
    private function normalizedRoot(): string
    {
        $root = $this->getRootPath();
        $real = realpath($root);

        return rtrim(str_replace('\\', '/', $real === false ? $root : $real), '/');
    }

    /**
     * 判断路径是否位于根目录内
     */
    private function isWithin(string $path, string $root): bool
    {
        return $root !== '' && ($path === $root || str_starts_with($path, $root . '/'));
    }

    /**
     * 上传服务端文件
     * @param string $filePath 服务端文件路径
     * @param array  $options  上传选项（支持 sub_dir 指定业务/插件子目录）
     */
    public function uploadServerFile(string $filePath, array $options = []): array
    {
        $file = new \SplFileInfo($filePath);
        if (!$file->isFile()) {
            throw new UploadException('请检查上传文件是否是一个有效的文件，文件不存在: ' . $filePath);
        }

        $root     = $this->getRootPath();
        $rootDir  = $this->config['root_dir'] ?? $this->config['dirname'] ?? '';
        $basePath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!$this->createDir($basePath)) {
            throw new UploadException('文件夹创建失败，请核查是否有对应权限。');
        }

        $domain       = rtrim($this->config['domain'] ?? '', '/');
        $uniqueId     = $this->getUniqueId($file->getPathname());
        $saveFilename = $uniqueId . '.' . $file->getExtension();
        $savePath     = $basePath . $saveFilename;
        $url          = $domain . $this->dirSeparator . $saveFilename;
        $basePathUrl  = $this->dirSeparator . $saveFilename;

        if (!empty($rootDir)) {
            $subDir    = $this->getSubdir($options);
            $fullDir   = $rootDir . ($subDir ? $this->dirSeparator . $subDir : '');
            $savePath  = $root . $this->dirSeparator . $fullDir . $this->dirSeparator . $saveFilename;
            $url       = $domain . $this->dirSeparator . $fullDir . $this->dirSeparator . $saveFilename;
            $basePathUrl = $this->dirSeparator . $fullDir . $this->dirSeparator . $saveFilename;
        }

        $dirPath = dirname($savePath);
        if (!is_dir($dirPath) && !$this->createDir($dirPath)) {
            throw new UploadException('子目录创建失败，请核查是否有对应权限。');
        }

        if (!copy($file->getPathname(), $savePath)) {
            throw new UploadException('文件拷贝失败');
        }

        return [
            'key'         => 0,
            'origin_name' => $file->getFilename(),
            'save_name'   => $saveFilename,
            'save_path'   => $savePath,
            'url'         => $url,
            'unique_id'   => $uniqueId,
            'size'        => $file->getSize(),
            'mime_type'   => mime_content_type($filePath) ?: 'application/octet-stream',
            'extension'   => $file->getExtension(),
            'base_path'   => $basePathUrl,
        ];
    }

    /**
     * Base64 上传文件
     * @param string $base64    Base64 编码数据（支持 data:image/xxx;base64, 前缀）
     * @param string $extension 文件扩展名
     */
    public function uploadBase64(string $base64, string $extension = 'JPEG'): array
    {
        // 处理 data:xxx;base64, 前缀
        if (str_contains($base64, ',')) {
            $parts = explode(',', $base64, 2);
            if (preg_match('/data:image\/(\w+);/', $parts[0], $matches)) {
                $extension = $matches[1];
            }
            $base64Data = $parts[1];
        } else {
            $base64Data = $base64;
        }

        $data = base64_decode($base64Data);
        if ($data === false) {
            throw new UploadException('Base64解码失败');
        }

        $root     = $this->getRootPath();
        $rootDir  = $this->config['root_dir'] ?? $this->config['dirname'] ?? '';
        $basePath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!$this->createDir($basePath)) {
            throw new UploadException('文件夹创建失败，请核查是否有对应权限。');
        }

        $domain       = rtrim($this->config['domain'] ?? '', '/');
        $extension    = strtolower($extension);
        $uniqueId     = md5($base64Data);
        $saveFilename = $uniqueId . '.' . $extension;
        $savePath     = $basePath . $saveFilename;
        $url          = $domain . $this->dirSeparator . $saveFilename;
        $basePathUrl  = $this->dirSeparator . $saveFilename;

        if (!empty($rootDir)) {
            $subDir    = $this->getSubdir();
            $fullDir   = $rootDir . ($subDir ? $this->dirSeparator . $subDir : '');
            $savePath  = $root . $this->dirSeparator . $fullDir . $this->dirSeparator . $saveFilename;
            $url       = $domain . $this->dirSeparator . $fullDir . $this->dirSeparator . $saveFilename;
            $basePathUrl = $this->dirSeparator . $fullDir . $this->dirSeparator . $saveFilename;
        }

        $dirPath = dirname($savePath);
        if (!is_dir($dirPath) && !$this->createDir($dirPath)) {
            throw new UploadException('子目录创建失败，请核查是否有对应权限。');
        }

        if (file_put_contents($savePath, $data) === false) {
            throw new UploadException('文件保存失败');
        }

        return [
            'key'         => 0,
            'origin_name' => $saveFilename,
            'save_name'   => $saveFilename,
            'save_path'   => $savePath,
            'url'         => $url,
            'unique_id'   => $uniqueId,
            'size'        => strlen($data),
            'mime_type'   => mime_content_type($savePath) ?: 'image/' . $extension,
            'extension'   => $extension,
            'base_path'   => $basePathUrl,
        ];
    }
}
