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
namespace app\api\controller\auth;

use app\api\controller\Base;
use app\api\validate\auth\LoginValidate;
use app\api\validate\auth\RegisterValidate;
use app\service\api\auth\AuthService;
use core\security\captcha\Captcha;
use core\foundation\tool\Json;
use Exception;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\Container;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 认证控制器
 */
#[OA\Tag(name: '认证模块')]
final class AuthController extends Base
{
    public function __construct(AuthService $service)
    {
        $this->service = $service;
    }

    #[OA\Post(
        path: '/auth/login',
        summary: '用户登录',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', description: '用户名/手机号/邮箱', type: 'string'),
                    new OA\Property(property: 'password', description: '密码', type: 'string'),
                    new OA\Property(property: 'captcha', description: '验证码', type: 'string'),
                ]
            )
        ),
        tags: ['认证'],
        responses: [
            new OA\Response(response: 200, description: '登录成功'),
            new OA\Response(response: 400, description: '参数错误'),
            new OA\Response(response: 401, description: '登录失败'),
        ]
    )]
    #[SimpleResponse(schema: [], example: ['token' => 'string'])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function login(Request $request): Response
    {
        try {
            /** @var LoginValidate $validate */
            $validate = Container::make(LoginValidate::class);
            if (!$validate->scene('login')->check($request->post())) {
                throw new Exception($validate->getError());
            }
            $data   = $request->all();
            $result = $this->service->login($data);
            return Json::success($result);

        } catch (Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 手机验证码登录
     */
    #[OA\Post(
        path: '/auth/login/mobile',
        summary: '手机验证码登录',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['mobile', 'code'],
                properties: [
                    new OA\Property(property: 'mobile', description: '手机号', type: 'string'),
                    new OA\Property(property: 'code', description: '验证码', type: 'string'),
                ]
            )
        ),
        tags: ['认证'],
    )]
    #[SimpleResponse(schema: [], example: ['token' => 'string'])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function loginWithMobile(Request $request): Response
    {
        $data   = $request->post();
        $result = $this->service->loginWithMobile($data);
        return Json::success($result);
    }

    /**
     * 用户退出登录
     */
    #[OA\Post(
        path: '/auth/logout',
        summary: '用户退出登录',
        tags: ['认证'],
        responses: [
            new OA\Response(response: 200, description: '退出成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    /** @Route(methods=["POST"], path="/auth/logout") */
    public function logout(): Response
    {
        $result = $this->service->logout();
        return Json::success($result);
    }

    /**
     * 用户注册
     */
    #[OA\Post(
        path: '/auth/register',
        summary: '用户注册',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', description: '用户名', type: 'string'),
                    new OA\Property(property: 'password', description: '密码', type: 'string'),
                    new OA\Property(property: 'email', description: '邮箱', type: 'string'),
                    new OA\Property(property: 'mobile', description: '手机号', type: 'string'),
                    new OA\Property(property: 'pid', description: '推荐人ID', type: 'integer'),
                ]
            )
        ),
        tags: ['认证'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function register(Request $request): Response
    {
        try {
            $validate = Container::make(RegisterValidate::class);
            if (!$validate->scene('register')->check($request->post())) {
                throw new Exception($validate->getError());
            }
            $captcha = Container::make(Captcha::class);
            if (!$captcha->check($request->input('captcha_key', ''), $request->input('captcha_code', ''))) {
                throw new Exception('验证码错误');
            }
            $data   = $request->all();
            $result = $this->service->register($data);
            return Json::success($result);
        } catch (Exception $e) {
            return Json::fail($e->getMessage());
        }

    }

    /**
     * 手机号注册
     */
    #[OA\Post(
        path: '/auth/register/mobile',
        summary: '手机号注册',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['mobile', 'code', 'password'],
                properties: [
                    new OA\Property(property: 'mobile', description: '手机号', type: 'string'),
                    new OA\Property(property: 'code', description: '验证码', type: 'string'),
                    new OA\Property(property: 'password', description: '密码', type: 'string'),
                    new OA\Property(property: 'pid', description: '推荐人ID', type: 'integer'),
                ]
            )
        ),
        tags: ['认证']
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function registerWithMobile(Request $request): Response
    {
        try {
            $data   = $request->post();
            $result = $this->service->registerWithMobile($data);
            return Json::success($result);
        } catch (Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 绑定手机号
     */
    #[OA\Post(
        path: '/auth/bind',
        summary: '绑定手机号',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['mobile', 'code'],
                properties: [
                    new OA\Property(property: 'mobile', description: '手机号', type: 'string'),
                    new OA\Property(property: 'code', description: '验证码', type: 'string'),
                    new OA\Property(property: 'pid', description: '推荐人ID', type: 'integer'),
                ]
            )
        ),
        tags: ['认证']
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function bindMobile(Request $request): Response
    {
        $data   = $request->post();
        $result = $this->service->bindMobile($data);
        return Json::success($result);
    }

    /**
     * 验证邮箱和验证码（忘记密码第一步）
     */
    #[OA\Post(
        path: '/auth/verify-email',
        summary: '验证邮箱和验证码',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'code', 'captcha_key'],
                properties: [
                    new OA\Property(property: 'email', description: '邮箱地址', type: 'string'),
                    new OA\Property(property: 'code', description: '邮箱验证码', type: 'string'),
                    new OA\Property(property: 'captcha_key', description: '验证码key', type: 'string'),
                ]
            )
        ),
        tags: ['认证']
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function verifyEmail(Request $request): Response
    {
        try {
            $data   = $request->post();
            $result = $this->service->verifyEmail($data);
            return Json::success($result);
        } catch (Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 发送邮箱验证码
     */
    #[OA\Post(
        path: '/auth/send-email-code',
        summary: '发送邮箱验证码',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'captcha_key', 'captcha_code'],
                properties: [
                    new OA\Property(property: 'email', description: '邮箱地址', type: 'string'),
                    new OA\Property(property: 'captcha_key', description: '图片验证码key', type: 'string'),
                    new OA\Property(property: 'captcha_code', description: '图片验证码', type: 'string'),
                ]
            )
        ),
        tags: ['认证']
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function sendEmailCode(Request $request): Response
    {
        try {
            $email       = $request->post('email');
            $captchaKey  = $request->post('captcha_key');
            $captchaCode = $request->post('captcha_code');

            // 验证图片验证码
            if (empty($captchaKey) || empty($captchaCode)) {
                throw new Exception('图片验证码不能为空', 400);
            }

            $captcha = new Captcha();
            if (!$captcha->check($captchaKey, $captchaCode)) {
                throw new Exception('图片验证码错误', 400);
            }
            $this->service->sendEmailCode($email);
            return Json::success('success', []);
        } catch (Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 重置密码（忘记密码第二步）
     */
    #[OA\Post(
        path: '/auth/forget-password',
        summary: '重置密码',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'code', 'new_password'],
                properties: [
                    new OA\Property(property: 'email', description: '邮箱地址', type: 'string'),
                    new OA\Property(property: 'code', description: '邮箱验证码', type: 'string'),
                    new OA\Property(property: 'new_password', description: '新密码', type: 'string'),
                    new OA\Property(property: 'confirm_password', description: '确认密码', type: 'string'),
                ]
            )
        ),
        tags: ['认证']
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function forgetPassword(Request $request): Response
    {
        try {
            $data = $request->post();
            $this->service->forgetPassword($data);
            return Json::success('操作成功', []);
        } catch (Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 刷新 token
     */
    #[OA\Post(
        path: '/auth/refresh',
        summary: '刷新 token',
        tags: ['认证'],
        responses: [
            new OA\Response(response: 200, description: '刷新成功'),
            new OA\Response(response: 401, description: '刷新失败'),
        ]
    )]
    #[SimpleResponse(schema: [], example: ['access_token' => 'string', 'refresh_token' => 'string'])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function refresh(): Response
    {
        try {
            $jwt   = new \core\security\jwt\JwtToken();
            $token = $jwt->refresh();
            return Json::success('ok', [
                'access_token'  => $token->accessToken,
                'refresh_token' => $token->refreshToken,
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], 401);
        }
    }
}