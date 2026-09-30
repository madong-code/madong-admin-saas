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
use app\model\system\config\Upload;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use core\io\upload\support\StoragePathResolver;
use core\io\upload\UploadFile;
use core\io\upload\UploadScene;
use madong\helper\Arr;
use support\Container;
use support\Log;

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
     * @param string $subDir 子目录（如 image/202609），为空时落在存储根目录
     *
     * @return mixed
     * @throws \Exception
     */
    public function saveNetworkImage(string $url, string $subDir = ''): array
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

        // 存储空间标识（default=公开 / private=私有）
        $space = UploadFile::spaceMark('local');

        // 去重保持租户作用域（TenantScope / 租户连接），且区分平台与空间，禁止跨租户/跨空间复用
        $result = $this->dao->get(['hash' => $hash, 'platform' => 'local', 'space' => $space]);
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
        $relative  = $resolver->joinPaths($dirname, $resolver->tenantSegment($config), $subDir, $folder);
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

                // 存储空间标识（default=公开 / private=私有）：切换空间后同一份文件在当前
                // 空间已重新落盘，必须新建记录，不能复用另一空间的旧地址
                $space = UploadFile::spaceMark($type);

                // 检查文件是否已存在（租户作用域内去重：同一 hash + 同一平台 + 同一空间才复用）
                if ($filesInfo = $this->dao->get(['hash' => $hash, 'platform' => $type, 'space' => $space])) {
                    return $filesInfo;
                }

                $inData = [
                    'platform'          => $type,
                    'space'             => $space,
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

    /**
     * 删除附件记录并同步清理已落盘的物理资源
     *
     * 顺序保证：先在同一事务内删除数据库记录，事务提交后再按记录所属平台清理
     * 本地文件 / 云端对象。物理资源清理失败只记日志，不回滚记录删除（避免残留
     * 记录指向已不存在的文件）。
     *
     * 数据隔离：'$model->newQuery()' 会带上模型的全局 TenantScope，物理清理使用
     * UploadScene::admin() 读取「当前租户」的存储配置，因此不会误删他租户的记录与对象。
     *
     * @param array $ids 附件ID集合
     *
     * @return array 实际删除的记录ID集合
     * @throws \Throwable
     */
    public function removeWithStorage(array $ids): array
    {
        $ids = array_values(array_filter(array_map(static fn($id) => (string)$id, $ids), static fn($id) => $id !== ''));
        if (empty($ids)) {
            throw new AdminException('删除参数不能为空');
        }

        $model      = $this->dao->getModel();
        $primaryKey = $model->getKeyName();
        $records    = $model->newQuery()->whereIn($primaryKey, $ids)->get();

        $deletedIds = [];
        $this->transaction(function () use ($records, $primaryKey, &$deletedIds) {
            foreach ($records as $record) {
                $record->delete();
                $deletedIds[] = (string)$record->{$primaryKey};
            }
        });

        foreach ($records as $record) {
            /** @var Upload $record */
            $this->purgeStorageObject($record);
        }

        return $deletedIds;
    }

    /**
     * 清理单条附件记录对应的物理资源
     *
     * 记录中 path 为对象 key（云存储）或绝对文件路径（本地），base_path 为兜底。
     */
    private function purgeStorageObject(Upload $record): void
    {
        $platform = trim((string)$record->platform);
        $key      = trim((string)($record->path ?: $record->base_path ?: ''));
        if ($platform === '' || $key === '') {
            return;
        }

        try {
            // 显式传当前租户场景，确保用租户自己的存储配置（桶 / 前缀）定位对象
            $deleted = UploadFile::disk($platform, false, UploadScene::admin())->deleteFile($key);
            if (!$deleted) {
                Log::warning('附件物理资源不存在或已删除', [
                    'id'       => (string)$record->id,
                    'platform' => $platform,
                    'key'      => $key,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('附件物理资源删除失败', [
                'id'       => (string)$record->id,
                'platform' => $platform,
                'key'      => $key,
                'error'    => $e->getMessage(),
            ]);
        }
    }

}
