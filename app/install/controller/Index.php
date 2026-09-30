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
namespace app\install\controller;

use core\business\install\InstallService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use support\Request;
use support\Response;
use Throwable;
use OpenApi\Attributes as OA;

class Index
{
    protected InstallService $installService;

    public function __construct()
    {
        $this->installService = new InstallService();
    }

    #[OA\Get(
        path: '/install/check',
        summary: '检查安装状态',
        tags: ['安装'])
    ]
    #[OA\Response(
        response: 200,
        description: '安装状态检查结果',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'installed',
                    description: '系统是否已安装',
                    type: 'boolean'
                ),
            ]
        )
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function check(Request $request): Response
    {
        try {
            $saasMode = config('tenant.enable', false);
            if ($this->installService->checkInstalled()) {
                // 获取安装时间
                $lockFile = base_path() . '/install.lock';
                $installTime = '';
                if (file_exists($lockFile)) {
                    $installTime = trim(file_get_contents($lockFile));
                }
                return Json::fail('系统已安装', ['installed' => true, 'install_time' => $installTime, 'saas_mode' => $saasMode]);
            }

            return Json::success('系统未安装', ['installed' => false, 'saas_mode' => $saasMode]);
        } catch (Throwable $e) {
            return Json::fail('检查失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取许可协议
     *
     * @param Request $request
     *
     * @return Response
     */
    #[OA\Get(
        path: '/install/agreement',
        summary: '获取许可协议',
        tags: ['安装'])
    ]
    #[OA\Response(
        response: 200,
        description: '许可协议内容',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'agreement',
                    description: '许可协议内容',
                    type: 'string'
                ),
            ]
        )
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function agreement(Request $request): Response
    {
        try {
            $agreement = $this->installService->getAgreementData();

            return Json::success('许可协议获取成功',
                $agreement
            );
        } catch (Throwable $e) {
            return Json::fail('获取许可协议失败: ' . $e->getMessage());
        }
    }

    /**
     * 检查系统环境
     *
     * @param Request $request
     *
     * @return Response
     */
    #[OA\Get(
        path: '/install/environment',
        summary: '检查系统环境',
        tags: ['安装'])
    ]
    #[OA\Response(
        response: 200,
        description: '环境检查结果',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'environment',
                    description: '环境检查结果',
                    type: 'object'
                ),
            ]
        )
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function environment(Request $request): Response
    {
        try {
            $environment = $this->installService->checkEnvironment();
            return Json::success('环境检查完成', $environment);
        } catch (Throwable $e) {
            return Json::fail('环境检查失败: ' . $e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    #[OA\get(
        path: '/install',
        summary: '执行安装',
        tags: ['安装'])
    ]
    #[SimpleResponse(schema: [], example: [])]
    public function install(Request $request): \Generator
    {

        $connection = $request->connection;
        $data       = $request->all();
        $connection->send(new Response(200, [
            'Content-Type'                     => 'text/event-stream',
            'Cache-Control'                    => 'no-cache',
            'X-Accel-Buffering'                => 'no', // 禁用 Nginx 缓冲
            'Access-Control-Allow-Origin'      => '*', // 或者允许所有来源(不推荐生产环境使用)
            'Access-Control-Allow-Credentials' => 'true', // 如果需要凭证
            'Access-Control-Expose-Headers'    => 'Content-Type', // 暴露必要的头
        ], "\r\n"));
        $generator = $this->installService->install($data);
        foreach ($generator as $chunk) {
            $connection->send($chunk);
        }
    }

    #[OA\Post(
        path: '/install/test-database',
        summary: '测试数据库连接',
        tags: ['安装'])
    ]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'database',
                    description: '数据库配置',
                    type: 'object'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: '数据库连接测试结果',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'result',
                    description: '数据库连接测试结果',
                    type: 'object'
                ),
            ]
        )
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function testDatabase(Request $request): Response
    {
        try {
            $databaseConfig = $request->all();

            // 验证数据库配置
            $dbErrors = $this->installService->validateDatabaseConfig($databaseConfig);
            if (!empty($dbErrors)) {
                return Json::fail(implode('; ', $dbErrors));
            }

            // 测试数据库连接逻辑
            $result = $this->installService->testDatabaseConnection($databaseConfig);

            // 测试成功后，配置 .env 文件（为后续安装步骤准备）
            $this->installService->saveDatabaseConfig($databaseConfig);

            return Json::success('数据库连接测试成功', $result);
        } catch (Throwable $e) {
            return Json::fail('数据库连接测试失败: ' . $e->getMessage());
        }
    }

    #[OA\Post(
        path: '/install/validate-config',
        summary: '验证安装配置',
        tags: ['安装'])
    ]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'database',
                    description: '数据库配置',
                    type: 'object'
                ),
                new OA\Property(
                    property: 'admin',
                    description: '管理员配置',
                    type: 'object'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: '配置验证结果',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'result',
                    description: '配置验证结果',
                    type: 'object'
                ),
            ]
        )
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function validateConfig(Request $request): Response
    {
        try {
            $databaseConfig = $request->post('database', []);
            $adminConfig    = $request->post('admin', []);

            // 验证数据库配置
            $dbErrors = $this->installService->validateDatabaseConfig($databaseConfig);
            if (!empty($dbErrors)) {
                return Json::fail('数据库配置验证失败', ['database' => $dbErrors]);
            }

            // 验证管理员配置
            $adminErrors = $this->installService->validateAdminConfig($adminConfig);
            if (!empty($adminErrors)) {
                return Json::fail('管理员配置验证失败', ['admin' => $adminErrors]);
            }

            return Json::success('配置验证通过');
        } catch (Throwable $e) {
            return Json::fail('配置验证失败: ' . $e->getMessage());
        }
    }
}