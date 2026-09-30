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

namespace app\service\admin\system\config;

use app\dao\system\config\UploadDao;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use core\io\upload\support\StoragePathResolver;
use core\io\upload\UploadFile;
use madong\helper\Arr;
use support\Container;

class UploadService extends BaseService
{


    public function __construct(UploadDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 远程下载图片到本地
     *
     * @param string $url
     *
     * @return mixed
     * @throws \Exception
     */
    public function saveNetworkImage(string $url): array
    {
        $config = UploadFile::config('local');
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
            default:
                imagedestroy($image_resource);
                throw new AdminException('文件格式错误');
        }
        imagedestroy($image_resource);
        if (!$result) {
            throw new AdminException('文件保存失败');
        }
        $hash = md5_file($save_path);
        $size = filesize($save_path);

        // 去重保持租户作用域（TenantScope / 租户连接），禁止跨租户复用
        $result = $this->dao->get(['hash' => $hash]);
        if (!empty($result)) {
            unlink($save_path);
            return $result->toArray();
        }

        /** @var ConfigService $systemConfigService */
        $systemConfigService = Container::get(ConfigService::class);
        $local               = $systemConfigService->dao->get(['code' => 'local']);
        if (empty($local)) {
            throw new AdminException('缺少本地上传配置信息');
        }
        $root      = Arr::fetchConfigValue($config, 'root') ?: 'public';
        $dirname   = Arr::fetchConfigValue($config, 'dirname') ?: 'upload';
        $folder    = date('Ymd');
        $resolver  = new StoragePathResolver();
        $relative  = $resolver->joinPaths($dirname, $resolver->tenantSegment($config), $folder);
        $full_dir  = base_path() . DIRECTORY_SEPARATOR . $root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative) . DIRECTORY_SEPARATOR;
        if (!is_dir($full_dir)) {
            mkdir($full_dir, 0777, true);
        }
        $object_name = bin2hex(pack('Nn', time(), random_int(1, 65535))) . ".$file_extension";
        $newPath     = $full_dir . $object_name;

        copy($save_path, $newPath);
        unlink($save_path);

        $info['platform']          = 'local';
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
     * 文件上传
     *
     * @param string $upload
     * @param bool   $isLocal
     *
     * @return mixed
     * @throws \Throwable
     */
    public function upload(string $upload = '', bool $isLocal = false): mixed
    {
        try {
            return $this->transaction(function () use ($upload, $isLocal) {
                /** @var  ConfigService $systemConfigService */
                $baseConfig          = UploadFile::config('upload');//获取上次配置
                if (empty($baseConfig)) {
                    throw new AdminException('缺少上传配置信息');
                }
                $type = Arr::fetchConfigValue($baseConfig, 'mode') ?: 'local';//上次模式默认本地
                if ($isLocal) {
                    $type = 'local';
                }
                $options = [];
                if (!empty($upload)) {
                    $options['sub_dir'] = $upload;
                }
                $result = UploadFile::uploadFile($options);
                $data   = $result[0];
                $hash   = $data['unique_id'];

                $url  = str_replace('\\', '/', $data['url']);
                $path = str_replace('\\', '/', $data['save_path']);

                // 检查文件是否已存在（租户作用域内去重，禁止跨租户 hash 复用）
                if ($filesInfo = $this->dao->get(['hash' => $hash])) {
                    return $filesInfo;
                }

                $inData = [
                    'platform'          => $type,
                    'original_filename' => $data['origin_name'] ?? '',
                    'filename'          => $data['save_name'],
                    'hash'              => $hash,
                    'content_type'      => $data['mime_type'],
                    'base_path'         => $data['base_path'],
                    'ext'               => $data['extension'],
                    'size'              => $data['size'],
                    'size_info'         => formatBytes($data['size']),
                    'url'               => full_url($url),
                    'path'              => $path,
                ];
                return $this->dao->save($inData);
            });
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

}
