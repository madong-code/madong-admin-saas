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

namespace app\adminapi\controller\auth;

use app\adminapi\controller\Crud;
use app\adminapi\schema\request\auth\login\LoginRequest;
use app\adminapi\schema\request\auth\ThirdPartyLoginRequest;
use app\model\tenant\Tenant;
use app\service\admin\system\admin\AdminService;
use app\service\admin\system\config\ConfigService;
use core\infrastructure\cache\CacheService;
use core\security\captcha\Captcha;
use core\foundation\exception\handler\AdminException;
use core\security\jwt\JwtToken;
use core\foundation\tool\Json;
use core\foundation\tool\RSAService;
use core\io\uuid\UUIDGenerator;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use support\Container;
use support\Request;

final class LoginController extends Crud
{
    public function __construct(AdminService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/auth/get-captcha-open-flag',
        summary: '验证码开启状态',
        tags: ['无需授权'],
    )]
    #[SimpleResponse(schema: ['flag' => true], example:[])]
    public function getCaptchaOpenFlag(Request $request): \support\Response
    {
        try {
            // 生成密钥对
            $cache = Container::make(CacheService::class, []);
            $keyId = md5(UUIDGenerator::generate());
            $keys  = RSAService::generateKeys();
            // 存储私钥到缓存，用于解密密码
            $cache->set("rsa_private_key_$keyId", $keys['private'], 60); // 5分钟过期
            return Json::success('ok', ['flag' => config('core.captcha.app.enable', false), 'key_id' => $keyId, 'public_key' => $keys['public']]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/auth/captcha',
        summary: '验证码',
        tags: ['无需授权'],
    )]
    #[SimpleResponse(schema: [], example:[])]
    public function captcha(Request $request): \support\Response
    {
        try {
            $captcha = new Captcha();
            $type    = $request->input('type', 'admin-login');
            $result  = $captcha->captcha($request, $type);
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/auth/send-sms',
        summary: '手机验证码',
        tags: ['无需授权']
    )]
    #[OA\Parameter(
        name: 'mobile_phone',
        description: '手机号码',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'integer', default: 18888888888)
    )]
    #[SimpleResponse(schema:[],example: '{"code": 185841,"uid":18888888888}}')]
    public function sendSms(Request $request): \support\Response
    {
        // 1.0生成验证码
        $captcha = new Captcha();
        $result  = $captcha->generateSmsCaptcha($request);
        // 2.0发送验证码到手机
        // 3.0 存储验证码到 Redis，设置过期时间（如 5 分钟）
        return Json::success('验证码发送成功', $result);
    }

    #[OA\Post(
        path: '/auth/login',
        summary: '登录',
        tags: ['无需授权']
    )]
    #[RequestBody(
        description: '请求参数',
        required: true,
        content: new OA\JsonContent(ref: LoginRequest::class)
    )]
    #[SimpleResponse(schema:[],example: '{"token_type": "Bearer","expires_in": 7200,"access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJtYWRvbmcudGVjaCIsImF1ZCI6Im1hZG9uZy50ZWNoIiwiaWF0IjoxNzYyNDExMjY5LCJuYmYiOjE3NjI0MTEyNjksImV4cCI6MTc2MjQxODQ2OSwiZXh0ZW5kIjp7ImlkIjoiMiIsInVzZXJfbmFtZSI6ImFkbWluIiwicmVhbF9uYW1lIjoiXHU4ZDg1XHU3ZWE3XHU3YmExXHU3NDA2XHU1NDU4Iiwibmlja19uYW1lIjoiXHU4ZDg1XHU3ZWE3XHU3YmExXHU3NDA2XHU1NDU4IiwiaXNfc3VwZXIiOjEsImlzX3RlbmFudF9hZG1pbiI6MSwibW9iaWxlX3Bob25lIjpudWxsLCJlbWFpbCI6bnVsbCwiYXZhdGFyIjoiL3VwbG9hZC80MmY4NmI5YjM2Nzk0ZmI5ZTM4MDkxN2MyNTFmNmQ4MS5wbmciLCJzaWduZWQiOm51bGwsImRhc2hib2FyZCI6bnVsbCwiZGVwdF9pZCI6bnVsbCwiZW5hYmxlZCI6MSwibG9naW5faXAiOiIxMjAuNDEuMjQ2LjIyNSIsImxvZ2luX3RpbWUiOjE3NjI0MTEyNjksInVwZGF0ZWRfYXQiOiIyMDI1LTExLTA2VDA2OjQxOjA5LjAwMDAwMFoiLCJzZXgiOjAsImJpcnRoZGF5IjpudWxsLCJ0ZWwiOm51bGwsImlzX2xvY2tlZCI6MCwiZGVwdHMiOltdLCJwb3N0cyI6W10sInRlbmFudHMiOlt7ImlkIjoiMSIsImRiX25hbWUiOiJtYWRvbmdfYWRtaW5fc2FhcyIsImNvZGUiOiJwbGF0Zm9ybSIsInR5cGUiOjAsImNvbnRhY3RfcGVyc29uIjoiYWRtaW4iLCJjb250YWN0X3Bob25lIjoiMTg4ODg4ODg4ODgiLCJjb21wYW55X25hbWUiOiJ4eHh4IFx1NjcwOVx1OTY1MFx1NTE2Y1x1NTNmOCIsImxpY2Vuc2VfbnVtYmVyIjoiIiwiYWRkcmVzcyI6Ilx1NGUyZFx1NTZmZCIsImRlc2NyaXB0aW9uIjoiXHU1MTg1XHU3ZjZlXHU4ZDI2XHU1M2Y3IiwiZG9tYWluIjoiaHR0cHM6Ly93d3cubWFkb25nLnRlY2giLCJlbmFibGVkIjoxLCJpc29sYXRpb25fbW9kZSI6MiwiaXNfZGVmYXVsdCI6MSwiZXhwaXJlZF9hdCI6bnVsbCwiZGVsZXRlZF9hdCI6bnVsbCwiY3JlYXRlZF9hdCI6IjIwMjUtMTAtMDNUMDM6MjM6NDcuMDAwMDAwWiIsImNyZWF0ZWRfYnkiOjEsInVwZGF0ZWRfYnkiOm51bGwsInVwZGF0ZWRfYXQiOm51bGwsImNyZWF0ZWRfZGF0ZSI6IjIwMjUtMTAtMDMgMTE6MjM6NDciLCJ1cGRhdGVkX2RhdGUiOm51bGwsImV4cGlyZWRfZGF0ZSI6bnVsbCwicGl2b3QiOnsiYWRtaW5faWQiOjIsInRlbmFudF9pZCI6MSwiY3JlYXRlZF9kYXRlIjpudWxsLCJ1cGRhdGVkX2RhdGUiOm51bGx9fV0sImNhc2JpbiI6W119fQ.rCaHoiI1gU7xyoJazt3V5JQrS2guV34PH-JjkWnceE4","refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJtYWRvbmcudGVjaCIsImF1ZCI6Im1hZG9uZy50ZWNoIiwiaWF0IjoxNzYyNDExMjY5LCJuYmYiOjE3NjI0MTEyNjksImV4cCI6MTc2MzAxNjA2OSwiZXh0ZW5kIjp7ImlkIjoiMiIsInVzZXJfbmFtZSI6ImFkbWluIiwicmVhbF9uYW1lIjoiXHU4ZDg1XHU3ZWE3XHU3YmExXHU3NDA2XHU1NDU4Iiwibmlja19uYW1lIjoiXHU4ZDg1XHU3ZWE3XHU3YmExXHU3NDA2XHU1NDU4IiwiaXNfc3VwZXIiOjEsImlzX3RlbmFudF9hZG1pbiI6MSwibW9iaWxlX3Bob25lIjpudWxsLCJlbWFpbCI6bnVsbCwiYXZhdGFyIjoiL3VwbG9hZC80MmY4NmI5YjM2Nzk0ZmI5ZTM4MDkxN2MyNTFmNmQ4MS5wbmciLCJzaWduZWQiOm51bGwsImRhc2hib2FyZCI6bnVsbCwiZGVwdF9pZCI6bnVsbCwiZW5hYmxlZCI6MSwibG9naW5faXAiOiIxMjAuNDEuMjQ2LjIyNSIsImxvZ2luX3RpbWUiOjE3NjI0MTEyNjksInVwZGF0ZWRfYXQiOiIyMDI1LTExLTA2VDA2OjQxOjA5LjAwMDAwMFoiLCJzZXgiOjAsImJpcnRoZGF5IjpudWxsLCJ0ZWwiOm51bGwsImlzX2xvY2tlZCI6MCwiZGVwdHMiOltdLCJwb3N0cyI6W10sInRlbmFudHMiOlt7ImlkIjoiMSIsImRiX25hbWUiOiJtYWRvbmdfYWRtaW5fc2FhcyIsImNvZGUiOiJwbGF0Zm9ybSIsInR5cGUiOjAsImNvbnRhY3RfcGVyc29uIjoiYWRtaW4iLCJjb250YWN0X3Bob25lIjoiMTg4ODg4ODg4ODgiLCJjb21wYW55X25hbWUiOiJ4eHh4IFx1NjcwOVx1OTY1MFx1NTE2Y1x1NTNmOCIsImxpY2Vuc2VfbnVtYmVyIjoiIiwiYWRkcmVzcyI6Ilx1NGUyZFx1NTZmZCIsImRlc2NyaXB0aW9uIjoiXHU1MTg1XHU3ZjZlXHU4ZDI2XHU1M2Y3IiwiZG9tYWluIjoiaHR0cHM6Ly93d3cubWFkb25nLnRlY2giLCJlbmFibGVkIjoxLCJpc29sYXRpb25fbW9kZSI6MiwiaXNfZGVmYXVsdCI6MSwiZXhwaXJlZF9hdCI6bnVsbCwiZGVsZXRlZF9hdCI6bnVsbCwiY3JlYXRlZF9hdCI6IjIwMjUtMTAtMDNUMDM6MjM6NDcuMDAwMDAwWiIsImNyZWF0ZWRfYnkiOjEsInVwZGF0ZWRfYnkiOm51bGwsInVwZGF0ZWRfYXQiOm51bGwsImNyZWF0ZWRfZGF0ZSI6IjIwMjUtMTAtMDMgMTE6MjM6NDciLCJ1cGRhdGVkX2RhdGUiOm51bGwsImV4cGlyZWRfZGF0ZSI6bnVsbCwicGl2b3QiOnsiYWRtaW5faWQiOjIsInRlbmFudF9pZCI6MSwiY3JlYXRlZF9kYXRlIjpudWxsLCJ1cGRhdGVkX2RhdGUiOm51bGx9fV0sImNhc2JpbiI6W119fQ.IgLkKhjjsgjAsjS4qReg51WpzwjiuU27_tbqTpUuD3M","client_id": "127.0.0.1-690c430548ecd5.55813566","expires_time": 1762418469}')]
    public function login(Request $request): \support\Response
    {
        try {
            $username  = $request->input('user_name');
            $password  = $request->input('password', '');
            $phone     = $request->input('mobile_phone', '');
            $code      = $request->input('code', '');
            $uuid      = $request->input('uuid', '');
            $type      = $request->input('type', 'admin');
            $grantType = $request->input('grant_type', 'deafult');//refresh_token   sms   default 可以自行定义拓展登录方式
            $keyId     = $request->input('key_id', '');//获取公钥Id
            $tenantId  = $request->input('tenant_id');//多租户登录（租户ID）
            $tenantCode = $request->input('tenant_code');//多租户登录（租户代码，与 tenant_id 二选一）


            $captcha = new Captcha();

            if (config('core.captcha.app.enable') && $grantType === 'default') {
                if (!$captcha->check($uuid, $code)) {
                    throw new AdminException('图片验证码错误！');
                }
            }

            if ($grantType == 'sms' && !empty($phone)) {
                if (!$captcha->check($phone, $code)) {
                    throw new AdminException('手机验证码错误！');
                }
                $info = $this->service->get(['mobile_phone' => $phone]);
                if (empty($info)) {
                    throw new AdminException('当前手机未绑定用户！');
                }
                $username = $info->getData('user_name');
            }

            $data = $this->service->login($username, $password, $type, $grantType, [
                'key_id'      => $keyId ?? '',
                'tenant_id'   => $tenantId,
                'tenant_code' => $tenantCode,
            ]);

            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/auth/tenant-mode',
        summary: '租户模式状态',
        tags: ['无需授权'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function getTenantModeFlag(Request $request): \support\Response
    {
        return Json::success('ok', [
            'enabled'           => filter_var(env('APP_TENANT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'auto_select_first' => filter_var(env('APP_TENANT_AUTO_SELECT_FIRST', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    #[OA\Get(
        path: '/auth/site-info',
        summary: '平台站点信息（登录前使用）',
        description: '返回平台级站点配置（code=site_setting_platform），包括站点名称、Logo、Favicon等。与登录后的租户级配置（code=site_setting）相互独立。',
        tags: ['无需授权'],
    )]
    #[SimpleResponse(schema: ['site_name' => 'SaaS平台', 'site_logo' => '/upload/logo.png', 'site_favicon' => '/favicon.ico', 'site_copyright' => 'Copyright 2024'], example: [])]
    public function getSiteInfo(Request $request): \support\Response
    {
        try {
            $configService = Container::get(ConfigService::class);
            $siteInfo = $configService->getSiteConfig();
            return Json::success('ok', $siteInfo);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/auth/tenants',
        summary: '租户列表（供登录选择）',
        tags: ['无需授权'],
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: '按租户名称或代码模糊搜索（可选）',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function getTenantList(Request $request): \support\Response
    {
        try {
            $keyword = trim((string)$request->input('keyword', ''));

            $query = Tenant::withoutGlobalScopes()
                ->where('status', Tenant::STATUS_ACTIVE)
                ->orderBy('sort')
                ->orderBy('id');

            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('code', 'like', '%' . $keyword . '%');
                });
            }

            $format = fn($t) => [
                'id'            => (string)$t->id,
                'name'          => $t->name,
                'code'          => $t->code,
                'domain'        => $t->domain,
                'database_mode' => $t->database_mode,
                'database_name' => $t->database_name,
            ];

            // 不传 page 时保持旧行为：返回全量数组
            $page = $request->input('page');
            if ($page === null || $page === '') {
                return Json::success('ok', $query->get()->map($format));
            }

            $pageSize = (int)$request->input('page_size', 20);
            $pageSize = max(1, min($pageSize, 200));
            $page     = max(1, (int)$page);

            $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

            return Json::success('ok', [
                'list'  => $paginator->getCollection()->map($format),
                'total' => $paginator->total(),
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: '注销',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['无需授权'],
    )]
    #[OA\Parameter(
        name: 'token',
        description: '令牌（可选，也可通过Authorization头传递）',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[SimpleResponse(schema:[],example: [])]
    public function logout(Request $request): \support\Response
    {
        // 1. 统一使用 JwtToken 提取 token 的方式，与中间件保持一致
        $jwtToken = new JwtToken();
        $token    = $jwtToken->getAccessTokenFromRequest() ?: $request->input('token');

        // 2. 快速验证无效token情况
        if (empty($token) || $token === 'undefined') {
            return Json::success('ok', []);
        }

        // 3. 处理Bearer Token格式
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7); // 移除"Bearer "前缀
            if (empty($token) || $token === 'undefined') {
                return Json::success('ok', []);
            }
        }

        // 4. 退出登录（强制加入黑名单，防止 token 被复用）
        $loggedOut = $jwtToken->logout($token);
        if (!$loggedOut) {
            return Json::fail('退出登录失败，请重试');
        }

        return Json::success('ok', []);
    }

    #[OA\Get(
        path: '/auth/auth/public-key',
        summary: '生成RSA密钥对',
        tags: ['无需授权'],
    )]
    #[SimpleResponse(schema:[],example: [])]
    public function generateRSAKeyPair(Request $request): \support\Response
    {
        try {
            // 生成密钥对
            $cache = Container::make(CacheService::class, []);
            $keyId = md5(UUIDGenerator::generate());
            $keys  = RSAService::generateKeys();
            // 存储私钥到缓存，用于解密密码
            $cache->set("rsa_private_key_$keyId", $keys['private'], 60); // 5分钟过期
            return Json::success('ok', ['public_key' => $keys['public'], 'key_id' => $keyId]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/auth/third-party/login',
        summary: '第三方测试登录接口（免RSA）',
        tags: ['无需授权']
    )]
    #[RequestBody(
        description: '请求参数',
        required: true,
        content: new OA\JsonContent(ref: ThirdPartyLoginRequest::class)
    )]
   #[SimpleResponse(schema:[],example: '{"code": 0,"msg": "ok","data": {"token_type": "Bearer","expires_in": 7200,"access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJtYWRvbmcudGVjaCIsImF1ZCI6Im1hZG9uZy50ZWNoIiwiaWF0IjoxNzYyNDExMjY5LCJuYmYiOjE3NjI0MTEyNjksImV4cCI6MTc2MjQxODQ2OSwiZXh0ZW5kIjp7ImlkIjoiMiIsInVzZXJfbmFtZSI6ImFkbWluIiwicmVhbF9uYW1lIjoiXHU4ZDg1XHU3ZWE3XHU3YmExXHU3NDA2XHU1NDU4Iiwibmlja19uYW1lIjoiXHU4ZDg1XHU3ZWE3XHU3YmExXHU3NDA2XHU1NDU4IiwiaXNfc3VwZXIiOjEsImlzX3RlbmFudF9hZG1pbiI6MSwibW9iaWxlX3Bob25lIjpudWxsLCJlbWFpbCI6bnVsbCwiYXZhdGFyIjoiL3VwbG9hZC80MmY4NmI5YjM2Nzk0ZmI5ZTM4MDkxN2MyNTFmNmQ4MS5wbmciLCJzaWduZWQiOm51bGwsImRhc2hib2FyZCI6bnVsbCwiZGVwdF9pZCI6bnVsbCwiZW5hYmxlZCI6MSwibG9naW5faXAiOiIxMjAuNDEuMjQ2LjIyNSIsImxvZ2luX3RpbWUiOjE3NjI0MTEyNjksInVwZGF0ZWRfYXQiOiIyMDI1LTExLTA2VDA2OjQxOjA5LjAwMDAwMFoiLCJzZXgiOjAsImJpcnRoZGF5IjpudWxsLCJ0ZWwiOm51bGwsImlzX2xvY2tlZCI6MCwiZGVwdHMiOltdLCJwb3N0cyI6W10sInRlbmFudHMiOlt7ImlkIjoiMSIsImRiX25hbWUiOiJtYWRvbmdfYWRtaW5fc2FhcyIsImNvZGUiOiJwbGF0Zm9ybSIsInR5cGUiOjAsImNvbnRhY3RfcGVyc29uIjoiYWRtaW4iLCJjb250YWN0X3Bob25lIjoiMTg4ODg4ODg4ODgiLCJjb21wYW55X25hbWUiOiJ4eHh4IFx1NjcwOVx1OTY1MFx1NTE2Y1x1NTNmOCIsImxpY2Vuc2VfbnVtYmVyIjoiIiwiYWRkcmVzcyI6Ilx1NGUyZFx1NTZmZCIsImRlc2NyaXB0aW9uIjoiXHU1MTg1XHU3ZjZlXHU4ZDI2XHU1M2Y3IiwiZG9tYWluIjoiaHR0cHM6Ly93d3cubWFkb25nLnRlY2giLCJlbmFibGVkIjoxLCJpc29sYXRpb25fbW9kZSI6MiwiaXNfZGVmYXVsdCI6MSwiZXhwaXJlZF9hdCI6bnVsbCwiZGVsZXRlZF9hdCI6bnVsbCwiY3JlYXRlZF9hdCI6IjIwMjUtMTAtMDNUMDM6MjM6NDcuMDAwMDAwWiIsImNyZWF0ZWRfYnkiOjEsInVwZGF0ZWRfYnkiOm51bGwsInVwZGF0ZWRfYXQiOm51bGwsImNyZWF0ZWRfZGF0ZSI6IjIwMjUtMTAtMDMgMTE6MjM6NDciLCJ1cGRhdGVkX2RhdGUiOm51bGwsImV4cGlyZWRfZGF0ZSI6bnVsbCwicGl2b3QiOnsiYWRtaW5faWQiOjIsInRlbmFudF9pZCI6MSwiY3JlYXRlZF9kYXRlIjpudWxsLCJ1cGRhdGVkX2RhdGUiOm51bGx9fV0sImNhc2JpbiI6W119fQ.rCaHoiI1gU7xyoJazt3V5JQrS2guV34PH-JjkWnceE4","refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJtYWRvbmcudGVjaCIsImF1ZCI6Im1hZG9uZy50ZWNoIiwiaWF0IjoxNzYyNDExMjY5LCJuYmYiOjE3NjI0MTEyNjksImV4cCI6MTc2MzAxNjA2OSwiZXh0ZW5kIjp7ImlkIjoiMiIsInVzZXJfbmFtZSI6ImFkbWluIiwicmVhbF9uYW1lIjoiXHU4ZDg1XHU3ZWE3XHU3YmExXHU3NDA2XHU1NDU4Iiwibmlja19uYW1lIjoiXHU4ZDg1XHU3ZWE3XHU3YmExXHU3NDA2XHU1NDU4IiwiaXNfc3VwZXIiOjEsImlzX3RlbmFudF9hZG1pbiI6MSwibW9iaWxlX3Bob25lIjpudWxsLCJlbWFpbCI6bnVsbCwiYXZhdGFyIjoiL3VwbG9hZC80MmY4NmI5YjM2Nzk0ZmI5ZTM4MDkxN2MyNTFmNmQ4MS5wbmciLCJzaWduZWQiOm51bGwsImRhc2hib2FyZCI6bnVsbCwiZGVwdF9pZCI6bnVsbCwiZW5hYmxlZCI6MSwibG9naW5faXAiOiIxMjAuNDEuMjQ2LjIyNSIsImxvZ2luX3RpbWUiOjE3NjI0MTEyNjksInVwZGF0ZWRfYXQiOiIyMDI1LTExLTA2VDA2OjQxOjA5LjAwMDAwMFoiLCJzZXgiOjAsImJpcnRoZGF5IjpudWxsLCJ0ZWwiOm51bGwsImlzX2xvY2tlZCI6MCwiZGVwdHMiOltdLCJwb3N0cyI6W10sInRlbmFudHMiOlt7ImlkIjoiMSIsImRiX25hbWUiOiJtYWRvbmdfYWRtaW5fc2FhcyIsImNvZGUiOiJwbGF0Zm9ybSIsInR5cGUiOjAsImNvbnRhY3RfcGVyc29uIjoiYWRtaW4iLCJjb250YWN0X3Bob25lIjoiMTg4ODg4ODg4ODgiLCJjb21wYW55X25hbWUiOiJ4eHh4IFx1NjcwOVx1OTY1MFx1NTE2Y1x1NTNmOCIsImxpY2Vuc2VfbnVtYmVyIjoiIiwiYWRkcmVzcyI6Ilx1NGUyZFx1NTZmZCIsImRlc2NyaXB0aW9uIjoiXHU1MTg1XHU3ZjZlXHU4ZDI2XHU1M2Y3IiwiZG9tYWluIjoiaHR0cHM6Ly93d3cubWFkb25nLnRlY2giLCJlbmFibGVkIjoxLCJpc29sYXRpb25fbW9kZSI6MiwiaXNfZGVmYXVsdCI6MSwiZXhwaXJlZF9hdCI6bnVsbCwiZGVsZXRlZF9hdCI6bnVsbCwiY3JlYXRlZF9hdCI6IjIwMjUtMTAtMDNUMDM6MjM6NDcuMDAwMDAwWiIsImNyZWF0ZWRfYnkiOjEsInVwZGF0ZWRfYnkiOm51bGwsInVwZGF0ZWRfYXQiOm51bGwsImNyZWF0ZWRfZGF0ZSI6IjIwMjUtMTAtMDMgMTE6MjM6NDciLCJ1cGRhdGVkX2RhdGUiOm51bGwsImV4cGlyZWRfZGF0ZSI6bnVsbCwicGl2b3QiOnsiYWRtaW5faWQiOjIsInRlbmFudF9pZCI6MSwiY3JlYXRlZF9kYXRlIjpudWxsLCJ1cGRhdGVkX2RhdGUiOm51bGx9fV0sImNhc2JpbiI6W119fQ.IgLkKhjjsgjAsjS4qReg51WpzwjiuU27_tbqTpUuD3M","client_id": "127.0.0.1-690c430548ecd5.55813566","expires_time": 1762418469}}')]
    public function thirdPartyLogin(Request $request): \support\Response
    {
        try {
            $acctId    = $request->get('acct_id');
            $appId     = $request->get('app_id');
            $appSecret = $request->get('app_secret');
            $userName  = $request->get('user_name');
            $data      = $this->service->thirdPartyLogin($acctId, $appId, $appSecret, $userName);
            return Json::success('登录成功', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/site/mode',
        summary: '获取当前运行模式',
        description: '返回系统运行模式（SaaS多租户/单机版）和租户隔离模式',
        tags: ['站点'],
    )]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": {"mode": "saas","tenant_enabled": true,"isolation_mode": "field"}}')]
    public function mode(): \support\Response
    {
        $tenantEnabled = (bool) config('tenant.enable', false);

        return Json::success('ok', [
            'mode'            => $tenantEnabled ? 'saas' : 'standalone',
            'tenant_enabled'  => $tenantEnabled,
            'isolation_mode'  => config('tenant.mode', 'single'),
        ]);
    }
}
