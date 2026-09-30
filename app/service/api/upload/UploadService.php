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

namespace app\service\api\upload;

use app\dao\system\config\UploadDao;
use core\business\tenant\context\TenantContext;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use core\io\upload\support\StoragePathResolver;
use core\io\upload\UploadFile;
use core\io\upload\UploadScene;
use madong\helper\Arr;

/**
 * 上传服务类
 *
 * 支持两种上传场景：
 * - 租户模式（TenantContext::hasTenant() === true）：使用 UploadScene::admin()
 * - 默认模式（无租户上下文）：使用 UploadScene::api()（group_code=default）
 */
class UploadService extends BaseService
{

    public function __construct(UploadDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据当前请求上下文决议上传场景
     */
    private function getScene(): UploadScene
    {
        return TenantContext::hasTenant() ? UploadScene::admin() : UploadScene::api();
    }

    /**
     * 图片上传
     *
     * @param string $upload
     * @param bool   $isLocal
     *
     * @return mixed
     * @throws \Throwable
     */
    public function uploadImage(string $upload = '', bool $isLocal = false): mixed
    {
        try {
            return $this->transaction(function () use ($upload, $isLocal) {
                $config = $this->getUploadConfig();
                if ($isLocal) {
                    $config['mode'] = 'local';
                }
                return $this->handleUpload($config, $upload);
            });
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 视频上传
     *
     * @param string $upload
     * @param bool   $isLocal
     *
     * @return mixed
     * @throws \Throwable
     */
    public function uploadVideo(string $upload = '', bool $isLocal = false): mixed
    {
        try {
            return $this->transaction(function () use ($upload, $isLocal) {
                $config = $this->getUploadConfig();
                if ($isLocal) {
                    $config['mode'] = 'local';
                }
                return $this->handleUpload($config, $upload);
            });
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 文件上传
     *
     * @param string $upload
     * @param bool   $isLocal
     *
     * @return mixed
     * @throws \Throwable
     */
    public function uploadFile(string $upload = '', bool $isLocal = false): mixed
    {
        try {
            return $this->transaction(function () use ($upload, $isLocal) {
                $baseConfig = $this->getUploadConfig();
                $config     = [
                    'mode'         => $baseConfig['mode'] ?? 'local',
                    'single_limit' => 50 * 1024 * 1024,
                    'total_limit'  => 50 * 1024 * 1024,
                    'nums'         => 1,
                    'include'      => ['doc', 'docx', 'xls', 'xlsx', 'pdf', 'txt', 'zip', 'rar', '7z'],
                    'exclude'      => $baseConfig['exclude'] ?? ['php', 'js', 'html', 'sh', 'exe'],
                ];
                if ($isLocal) {
                    $config['mode'] = 'local';
                }
                return $this->handleUpload($config, $upload);
            });
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 远程图片拉取
     *
     * @param string $url
     *
     * @return mixed
     * @throws \Exception
     */
    public function fetchImage(string $url): array
    {
        $scene = $this->getScene();
        $config = UploadFile::config('local', [], $scene);
        $data   = file_get_contents($url);
        if ($data === false) {
            throw new AdminException('获取文件资源失败');
        }
        $image_resource = imagecreatefromstring($data);
        if (!$image_resource) {
            throw new AdminException('创建图片资源失败');
        }
        $filename       = basename($url);
        $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
        $full_dir       = runtime_path() . '/resource/';
        if (!is_dir($full_dir)) {
            mkdir($full_dir, 0777, true);
        }
        $save_path    = $full_dir . $filename;
        $content_type = 'image/';
        switch ($file_extension) {
            case 'jpg':
            case 'jpeg':
                $content_type = 'image/jpeg';
                $result       = imagejpeg($image_resource, $save_path);
                break;
            case 'png':
                $content_type = 'image/png';
                $result       = imagepng($image_resource, $save_path);
                break;
            case 'gif':
                $content_type = 'image/gif';
                $result       = imagegif($image_resource, $save_path);
                break;
            case 'webp':
                $content_type = 'image/webp';
                $result       = imagewebp($image_resource, $save_path);
                break;
            default:
                imagedestroy($image_resource);
                throw new AdminException('文件格式错误');
        }
        imagedestroy($image_resource);
        if (!$result) {
            throw new AdminException('文件保存失败');
        }
        $hash   = md5_file($save_path);
        $size   = filesize($save_path);
        // 去重保持租户作用域，且区分存储平台与存储空间，禁止跨租户/跨空间复用
        $space  = UploadFile::spaceMark('local', $this->getScene());
        $result = $this->dao->get(['hash' => $hash, 'platform' => 'local', 'space' => $space]);
        if (!empty($result)) {
            unlink($save_path);
            return $result->toArray();
        }
        $root     = Arr::fetchConfigValue($config, 'root') ?: 'public';
        $dirname  = Arr::fetchConfigValue($config, 'dirname') ?: 'upload';
        $folder   = date('Ymd');
        $resolver = new StoragePathResolver();
        $relative = $resolver->joinPaths($dirname, $resolver->tenantSegment($config), $folder);
        $full_dir = base_path() . DIRECTORY_SEPARATOR . $root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative) . DIRECTORY_SEPARATOR;
        if (!is_dir($full_dir)) {
            mkdir($full_dir, 0777, true);
        }
        $object_name = bin2hex(pack('Nn', time(), random_int(1, 65535))) . ".{$file_extension}";
        $newPath     = $full_dir . $object_name;
        copy($save_path, $newPath);
        unlink($save_path);
        $info['platform']          = 'local';
        $info['space']             = $space;
        $info['original_filename'] = $filename;
        $info['filename']          = $object_name;
        $info['hash']              = $hash;
        $info['content_type']      = $content_type;
        $info['base_path']         = '/' . $relative . '/' . $object_name;
        $info['path']              = $relative . '/' . $object_name;
        $info['ext']               = $file_extension;
        $info['size']              = $size;
        $info['size_info']         = formatBytes($size);
        $info['url']               = $relative . '/' . $object_name;
        $result                    = $this->dao->save($info);
        return $result->toArray();
    }

    /**
     * Base64图片上传
     *
     * @param string $base64Data
     *
     * @return mixed
     * @throws \Exception
     */
    public function uploadBase64Image(string $base64Data): array
    {
        if (str_starts_with($base64Data, 'data:image/')) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        }
        $data = base64_decode($base64Data);
        if ($data === false) {
            throw new AdminException('Base64解码失败');
        }
        $image_resource = imagecreatefromstring($data);
        if (!$image_resource) {
            throw new AdminException('创建图片资源失败');
        }
        $file_extension = 'png';
        $content_type   = 'image/png';
        $file_info      = finfo_open(FILEINFO_MIME_TYPE);
        if ($file_info) {
            $mime_type = finfo_buffer($file_info, $data);
            finfo_close($file_info);
            switch ($mime_type) {
                case 'image/jpeg':
                case 'image/jpg':
                    $file_extension = 'jpg';
                    $content_type   = 'image/jpeg';
                    break;
                case 'image/png':
                    $file_extension = 'png';
                    $content_type   = 'image/png';
                    break;
                case 'image/gif':
                    $file_extension = 'gif';
                    $content_type   = 'image/gif';
                    break;
                case 'image/webp':
                    $file_extension = 'webp';
                    $content_type   = 'image/webp';
                    break;
            }
        }
        $full_dir = runtime_path() . '/resource/';
        if (!is_dir($full_dir)) {
            mkdir($full_dir, 0777, true);
        }
        $filename  = 'upload_' . time() . '_' . random_int(1000, 9999) . '.' . $file_extension;
        $save_path = $full_dir . $filename;
        switch ($file_extension) {
            case 'jpg':
            case 'jpeg':
                $result = imagejpeg($image_resource, $save_path, 90);
                break;
            case 'png':
                $result = imagepng($image_resource, $save_path, 9);
                break;
            case 'gif':
                $result = imagegif($image_resource, $save_path);
                break;
            case 'webp':
                $result = imagewebp($image_resource, $save_path, 90);
                break;
            default:
                imagedestroy($image_resource);
                throw new AdminException('文件格式错误');
        }
        imagedestroy($image_resource);
        if (!$result) {
            throw new AdminException('文件保存失败');
        }
        $hash   = md5_file($save_path);
        $size   = filesize($save_path);
        // 去重保持租户作用域，且区分存储平台与存储空间，禁止跨租户/跨空间复用
        $space  = UploadFile::spaceMark('local', $this->getScene());
        $result = $this->dao->get(['hash' => $hash, 'platform' => 'local', 'space' => $space]);
        if (!empty($result)) {
            unlink($save_path);
            return $result->toArray();
        }
        $scene    = $this->getScene();
        $config   = UploadFile::config('local', [], $scene);
        $root     = Arr::fetchConfigValue($config, 'root') ?: 'public';
        $dirname  = Arr::fetchConfigValue($config, 'dirname') ?: 'upload';
        $folder   = date('Ymd');
        $resolver = new StoragePathResolver();
        $relative = $resolver->joinPaths($dirname, $resolver->tenantSegment($config), $folder);
        $full_dir = base_path() . DIRECTORY_SEPARATOR . $root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative) . DIRECTORY_SEPARATOR;
        if (!is_dir($full_dir)) {
            mkdir($full_dir, 0777, true);
        }
        $object_name = bin2hex(pack('Nn', time(), random_int(1, 65535))) . ".{$file_extension}";
        $newPath     = $full_dir . $object_name;
        copy($save_path, $newPath);
        unlink($save_path);
        $info['platform']          = 'local';
        $info['space']             = $space;
        $info['original_filename'] = $filename;
        $info['filename']          = $object_name;
        $info['hash']              = $hash;
        $info['content_type']      = $content_type;
        $info['base_path']         = '/' . $relative . '/' . $object_name;
        $info['path']              = $relative . '/' . $object_name;
        $info['ext']               = $file_extension;
        $info['size']              = $size;
        $info['size_info']         = formatBytes($size);
        $info['url']               = $relative . '/' . $object_name;
        $result                    = $this->dao->save($info);
        return $result->toArray();
    }

    /**
     * 处理文件上传
     *
     * @param array  $config
     * @param string $upload
     *
     * @return mixed
     * @throws \Throwable
     */
    private function handleUpload(array $config, string $upload = ''): mixed
    {
        $scene = $this->getScene();

        $options = [];
        if (!empty($upload)) {
            $options['sub_dir'] = $upload;
        }
        // 使用显式 scene 调用，替代默认静态 UploadFile::uploadFile()
        $result = UploadFile::disk(null, true, $scene)->uploadFile($options);
        $data   = $result[0];
        $url    = str_replace('\\', '/', $data['url']);
        $path   = str_replace('\\', '/', $data['save_path']);

        // 存储空间标识（default=公开 / private=私有）：切换公开、私有空间后同一份文件
        // 已在当前空间重新落盘，必须新建记录，不能复用另一空间的旧地址
        $space = UploadFile::spaceMark($config['mode'], $scene);

        // 检查文件是否已存在（租户作用域内去重：同一 hash + 同一存储平台 + 同一存储空间才复用）
        if ($filesInfo = $this->dao->get(['hash' => $data['unique_id'], 'platform' => $config['mode'], 'space' => $space])) {
            return $filesInfo;
        }

        $inData = [
            'platform'          => $config['mode'],
            'space'             => $space,
            'original_filename' => $data['origin_name'] ?? '',
            'filename'          => $data['save_name'],
            'hash'              => $data['unique_id'],
            'content_type'      => $data['mime_type'],
            'base_path'         => $data['base_path'],
            'ext'               => $data['extension'],
            'size'              => $data['size'],
            'size_info'         => formatBytes($data['size']),
            'url'               => full_url($url),
            'path'              => $path,
        ];
        return $this->dao->save($inData);
    }

    /**
     * 获取上传配置
     *
     * @return array
     * @throws \Exception
     */
    private function getUploadConfig(): array
    {
        $scene = $this->getScene();
        return UploadFile::config('upload', [
            'mode'         => 'local',
            'single_limit' => 1024 * 1024,
            'total_limit'  => 1024 * 1024,
            'nums'         => 10,
            'exclude'      => ['php', 'ext', 'exe'],
        ], $scene);
    }
}
