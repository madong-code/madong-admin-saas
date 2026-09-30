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

use core\foundation\exception\handler\AdminException;
use madong\helper\Dict;

/**
 * 插件远程服务
 */
final class PluginRemoteService extends PluginBaseService
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 创建 HTTP 客户端
     *
     * @param string $baseUri 基础URI
     *
     * @return \GuzzleHttp\Client
     */
    private function createHttpClient(string $baseUri): \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client([
            'base_uri'        => $baseUri,
            'timeout'         => 60,
            'connect_timeout' => 5,
            'verify'          => config('madong.market_verify_ssl', true),
            'http_errors'     => false,
            'headers'         => [
                'Referer'    => \request()->fullUrl() ?? '',
                'User-Agent' => 'madong-app-plugin',
                'Accept'     => 'application/json;charset=UTF-8',
            ],
        ]);
    }

    /**
     * 生成 API 签名
     * 使用 HMAC-SHA256，兼容配置回退到 MD5
     */
    private function generateSign(string $authCode, string $authSecret): string
    {
        $payload = $authCode . $authSecret . (string) time();
        $algo    = config('madong.market_sign_algo', 'hmac-sha256');
        return match ($algo) {
            'hmac-sha256' => hash_hmac('sha256', $payload, $authSecret),
            default       => md5($payload),
        };
    }

    /**
     * 获取授权市场的购买应用
     *
     * @param array $params
     *
     * @return array
     */
    public function getPurchasedModules(array $params = []): array
    {
        try {
            $config = [
                'auth_code'   => config('madong.auth_code', ''),
                'auth_secret' => config('madong.auth_secret', ''),
                'page'        => config('madong.page', 1),
                'limit'       => config('madong.limit', 999),
                'market_host' => config('madong.market_host', 'https://madong.tech'),
            ];
            $args   = Dict::of($config);
            //优先使用传入的参数
            if (!empty($params['auth_code'])) {
                $args->put('auth_code', $params['auth_code']);
            }
            if (!empty($params['auth_secret'])) {
                $args->put('auth_secret', $params['auth_secret']);
            }
            if (!empty($params['page'])) {
                $args->put('page', $params['page']);
            }
            if (!empty($params['limit'])) {
                $args->put('limit', $params['limit']);
            }
            if (!empty($params['market_host'])) {
                $args->put('market_host', $params['market_host']);
            }

            if (empty($args->get('auth_code')) || empty($args->get('auth_secret'))) {
                return [];
            }

            // 所有请求都通过远程 curl 获取
            return $this->getPurchasedModulesFromRemote($args->toArray());
        } catch (\Throwable $e) {
            \support\Log::error('getPurchasedModules error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取远程模块
     *
     * @param array $args
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getPurchasedModulesFromRemote(array $args): array
    {
        try {
            $client = $this->createHttpClient($args['market_host']);

            $sign = $this->generateSign($args['auth_code'], $args['auth_secret']);
            $response = $client->get('/api/madong/apps', [
                'query' => [
                    'auth_code'   => $args['auth_code'],
                    'auth_secret' => $args['auth_secret'],
                    'timestamp'   => time(),
                    'sign'        => $sign,
                    'page'        => 1,
                    'limit'       => 999,
                ],
            ]);

            $content = $response->getBody()->getContents();
            $data    = json_decode($content, true);

            // 如果返回格式错误，记录日志并返回空数组
            if (json_last_error() !== JSON_ERROR_NONE) {
                \support\Log::error('getPurchasedModulesFromRemote: JSON解析失败 - ' . json_last_error_msg());
                return [];
            }

            // 检查并验证返回的数据结构
            if (!is_array($data)) {
                \support\Log::error('getPurchasedModulesFromRemote: 返回数据不是数组 - ' . $content);
                return [];
            }

            // API返回格式: {code: int, msg: string, data: {items: array, total: int}}
            if (isset($data['code']) && $data['code'] !== 0) {
                \support\Log::error('getPurchasedModulesFromRemote: API返回错误 - ' . ($data['msg'] ?? '未知错误'));
                return [];
            }

            // 提取 items 列表
            if (isset($data['data']) && is_array($data['data']) && isset($data['data']['items'])) {
                $items = $data['data']['items'];
                if (!is_array($items)) {
                    \support\Log::error('getPurchasedModulesFromRemote: items 不是数组');
                    return [];
                }
                return $items;
            }

            // 没有找到items，返回空数组
            return [];
        } catch (\Exception $e) {
            \support\Log::error('getPurchasedModulesFromRemote error: ' . $e->getMessage());
            return [];
        }

    }

    /**
     * 获取远程模块的更新列表
     *
     * @param string $authCode  授权码
     * @param string $secretKey 密钥
     * @param array  $filters   过滤条件
     *
     * @return array
     */
    public function getRemoteUpdateList(string $authCode, string $secretKey, array $filters = []): array
    {
        try {
            $config = [
                    'auth_code'   => $authCode,
                    'auth_secret' => $secretKey,
                    'market_host' => config('madong.market_host', 'https://madong.tech'),
                ] + $filters;

            // 所有请求都通过远程 curl 获取
            return $this->getRemoteUpdateListFromRemote($config);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * 从远程获取模块更新列表
     *
     * @param array $args
     *
     * @return array
     */
    private function getRemoteUpdateListFromRemote(array $args): array
    {
        try {
            $client = $this->createHttpClient($args['market_host']);

            $sign = $this->generateSign($args['auth_code'], $args['auth_secret']);
            $response = $client->get('/api/madong/apps', [
                'query' => [
                        'auth_code'   => $args['auth_code'],
                        'auth_secret' => $args['auth_secret'],
                        'timestamp'   => time(),
                        'sign'        => $sign,
                        'page'        => 1,
                        'limit'       => 999,
                    ],
            ]);

            $content = $response->getBody()->getContents();
            $data    = json_decode($content, true);

            // 如果返回格式错误，记录日志并返回空数组
            if (json_last_error() !== JSON_ERROR_NONE) {
                \support\Log::error('getRemoteUpdateListFromRemote: JSON解析失败 - ' . json_last_error_msg());
                return [];
            }

            // 检查并验证返回的数据结构
            if (!is_array($data)) {
                \support\Log::error('getRemoteUpdateListFromRemote: 返回数据不是数组 - ' . $content);
                return [];
            }

            // API返回格式: {code: int, msg: string, data: {items: array, total: int}}
            // 检查code，非0表示错误
            if (isset($data['code']) && $data['code'] !== 0) {
                \support\Log::error('getRemoteUpdateListFromRemote: API返回错误 - ' . ($data['msg'] ?? '未知错误'));
                return [];
            }

            // 提取 items 列表
            if (isset($data['data']) && is_array($data['data']) && isset($data['data']['items'])) {
                $items = $data['data']['items'];
                if (!is_array($items)) {
                    \support\Log::error('getRemoteUpdateListFromRemote: items 不是数组');
                    return [];
                }
                return $items;
            }

            // 没有找到items，返回空数组
            return [];
        } catch (\Exception $e) {
            \support\Log::error('getRemoteUpdateListFromRemote error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取远程模块下载服务
     *
     * @param string $authCode  授权码
     * @param string $secretKey 密钥
     * @param string $appId     应用ID
     * @param string $version   版本号
     *
     * @return array
     */
    public function getRemoteDownloadService(string $authCode, string $secretKey, string $appId, string $version): array
    {
        try {
            $config = [
                'auth_code'   => $authCode,
                'auth_secret' => $secretKey,
                'app_id'      => $appId,
                'version'     => $version,
                'market_host' => config('madong.market_host', 'https://madong.tech'),
            ];

            // 所有请求都通过远程 curl 获取
            return $this->getRemoteDownloadServiceFromRemote($config);
        } catch (\Exception $e) {
            \support\Log::error('getRemoteDownloadService error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 从远程获取模块下载服务
     *
     * @param array $args
     *
     * @return array
     */
    private function getRemoteDownloadServiceFromRemote(array $args): array
    {
        try {
            $client = $this->createHttpClient($args['market_host']);

            $sign = $this->generateSign($args['auth_code'], $args['auth_secret']);
            
            // 记录请求参数
            \support\Log::info('getRemoteDownloadServiceFromRemote 请求参数', [
                'market_host' => $args['market_host'],
                'app_id' => $args['app_id'],
                'version' => $args['version'],
                'auth_code' => $args['auth_code'],
                'auth_secret_prefix' => substr($args['auth_secret'], 0, 4) . '***',
            ]);

            $response = $client->get('/api/madong/apps/download', [
                'query' => [
                    'auth_code'   => $args['auth_code'],
                    'auth_secret' => $args['auth_secret'],
                    'app_id'      => $args['app_id'],
                    'version'     => $args['version'],
                    'timestamp'   => time(),
                    'sign'        => $sign,
                ],
            ]);

            $content = $response->getBody()->getContents();
            $data    = json_decode($content, true);

            // 记录响应内容
            \support\Log::info('getRemoteDownloadServiceFromRemote 响应内容', [
                'status_code' => $response->getStatusCode(),
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 500),
            ]);

            // 如果返回格式错误，记录日志并返回空数组
            if (json_last_error() !== JSON_ERROR_NONE) {
                \support\Log::error('getRemoteDownloadServiceFromRemote: JSON解析失败 - ' . json_last_error_msg(), [
                    'content' => $content,
                ]);
                return [];
            }

            // 检查并验证返回的数据结构
            if (!is_array($data)) {
                \support\Log::error('getRemoteDownloadServiceFromRemote: 返回数据不是数组 - ' . $content);
                return [];
            }

            // API返回格式: {code: int, msg: string, data: {...}}
            // 检查code，非0表示错误
            if (isset($data['code']) && $data['code'] !== 0) {
                \support\Log::error('getRemoteDownloadServiceFromRemote: API返回错误', [
                    'code' => $data['code'],
                    'msg' => $data['msg'] ?? '未知错误',
                    'data' => $data['data'] ?? null,
                ]);
                return [];
            }

            // 确保返回的是下载服务数据
            if (isset($data['data']) && is_array($data['data'])) {
                \support\Log::info('getRemoteDownloadServiceFromRemote: 成功获取下载链接', [
                    'has_url' => isset($data['data']['url']),
                    'url_length' => isset($data['data']['url']) ? strlen($data['data']['url']) : 0,
                ]);
                return $data['data'];
            }

            return [];
        } catch (\Exception $e) {
            \support\Log::error('getRemoteDownloadServiceFromRemote error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * 验证远程授权
     *
     * @param string $authCode  授权码
     * @param string $secretKey 密钥
     *
     * @return array
     * @throws \core\exception\handler\AdminException
     */
    public function verifyRemoteAuthorization(string $authCode, string $secretKey): array
    {
        $config = [
            'auth_code'   => $authCode,
            'auth_secret' => $secretKey,
            'market_host' => config('madong.market_host', 'https://madong.tech'),
        ];

        return $this->doVerifyRemoteAuthorization($config);
    }

    /**
     * 执行远程授权验证
     *
     * @param array $args
     *
     * @return array
     * @throws \core\exception\handler\AdminException
     */
    private function doVerifyRemoteAuthorization(array $args): array
    {
        try {
            $client = $this->createHttpClient($args['market_host']);

            $sign = $this->generateSign($args['auth_code'], $args['auth_secret']);
            $response = $client->get('/api/madong/authorization/verify', [
                'query' => [
                    'auth_code'   => $args['auth_code'],
                    'auth_secret' => $args['auth_secret'],
                    'timestamp'   => time(),
                    'sign'        => $sign,
                ],
            ]);

            $content = $response->getBody()->getContents();
            $data    = json_decode($content, true);

            // 如果返回格式错误
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON解析失败: ' . json_last_error_msg());
            }

            // 检查并验证返回的数据结构
            if (!is_array($data)) {
                throw new \Exception('返回数据不是数组');
            }

            // 检查返回的code是否为0
            if (isset($data['code']) && $data['code'] !== 0) {
                throw new \Exception('授权验证失败: ' . ($data['msg'] ?? '未知错误'));
            }

            // 确保返回的是授权验证数据
            if (isset($data['data']) && is_array($data['data'])) {
                return $data['data'];
            }

            return $data;
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'Could not resolve host')) {
                throw new AdminException('无法连接到授权服务器（域名解析失败），请检查网络连接');
            }
            if (str_contains($message, 'Connection timed out')) {
                throw new AdminException('连接授权服务器超时，请检查网络连接');
            }
            if (str_contains($message, 'Connection refused')) {
                throw new AdminException('授权服务器连接被拒绝');
            }
            throw new AdminException('远程授权服务器连接失败: ' . $message);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new AdminException('远程授权请求失败: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 获取远程应用升级日志
     *
     * @param string $authCode  授权码
     * @param string $secretKey 密钥
     * @param string $appCode   应用编码
     * @param int    $limit     限制数量
     *
     * @return array
     */
    public function getRemoteUpdateLogs(string $authCode, string $secretKey, string $appCode, int $limit = 100): array
    {
        try {
            $config = [
                'auth_code'   => $authCode,
                'auth_secret' => $secretKey,
                'code'        => $appCode,
                'limit'       => $limit,
                'market_host' => config('madong.market_host', 'https://madong.tech'),
            ];

            // 所有请求都通过远程 curl 获取
            return $this->getRemoteUpdateLogsFromRemote($config);
        } catch (\Exception $e) {
            \support\Log::error('getRemoteUpdateLogs error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 从远程获取应用升级日志
     *
     * @param array $args
     *
     * @return array
     */
    private function getRemoteUpdateLogsFromRemote(array $args): array
    {
        try {
            $client = $this->createHttpClient($args['market_host']);

            $sign = $this->generateSign($args['auth_code'], $args['auth_secret']);
            $response = $client->get('/api/madong/apps/update-logs', [
                'query' => [
                    'auth_code'   => $args['auth_code'],
                    'auth_secret' => $args['auth_secret'],
                    'code'        => $args['code'],
                    'limit'       => $args['limit'],
                    'timestamp'   => time(),
                    'sign'        => $sign,
                ],
            ]);

            $content = $response->getBody()->getContents();
            $data    = json_decode($content, true);

            // 如果返回格式错误，记录日志并返回空数组
            if (json_last_error() !== JSON_ERROR_NONE) {
                \support\Log::error('getRemoteUpdateLogsFromRemote: JSON解析失败 - ' . json_last_error_msg());
                return [];
            }

            // 检查并验证返回的数据结构
            if (!is_array($data)) {
                \support\Log::error('getRemoteUpdateLogsFromRemote: 返回数据不是数组 - ' . $content);
                return [];
            }

            // API返回格式: {code: int, msg: string, data: {...}}
            // 检查code，非0表示错误
            if (isset($data['code']) && $data['code'] !== 0) {
                \support\Log::error('getRemoteUpdateLogsFromRemote: API返回错误 - ' . ($data['msg'] ?? '未知错误'));
                return [];
            }

            // 确保返回的是升级日志数据
            if (isset($data['data']) && is_array($data['data'])) {
                return $data['data'];
            }

            return [];
        } catch (\Exception $e) {
            \support\Log::error('getRemoteUpdateLogsFromRemote error: ' . $e->getMessage());
            return [];
        }
    }
}