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
namespace app\service\api\auth;

use app\api\CurrentMember;
use app\dao\member\MemberDao;
use app\enum\common\EnabledStatus;
use app\model\member\Member;
use app\service\admin\system\ConfigService;
use core\foundation\base\BaseService;
use core\communication\email\MailService;
use core\security\jwt\JwtToken;
use support\Container;
use support\Redis;


/**
 * 认证服务
 */
class AuthService extends BaseService
{

    public function __construct(MemberDao $dao){
        $this->dao = $dao;
    }
    /**
     * 用户登录
     *
     * @throws \Exception
     */
    public function login(array $data): array
    {
        // 验证参数
        if (empty($data['username']) || empty($data['password'])) {
            throw new \Exception('用户名和密码不能为空', 400);
        }

        // 查找用户（支持用户名、手机号、邮箱登录）
        $member = $this->dao->query()
            ->where(function ($query) use ($data) {
                $query->where('username', $data['username'])
                    ->orWhere('phone', $data['username'])
                    ->orWhere('email', $data['username']);
            })->first();

        if (!$member) {
            throw new \Exception('用户不存在', 401);
        }

        // 验证用户状态
        if ($member->enabled !== EnabledStatus::ENABLED->value) {
            throw new \Exception('用户已被禁用', 401);
        }

        // 验证密码
        if (!$member->verifyPassword($data['password'])) {
            throw new \Exception('密码错误', 401);
        }

        // 更新登录信息
        $this->updateLoginInfo($member);
        $userInfo = [
            'id'          => $member->id,
            'username'    => $member->username,
            'nickname'    => $member->nickname,
            'avatar'      => $member->avatar,
            'phone'       => $member->phone,
            'email'       => $member->email,
            'client'      => 'web',
            'access_exp'  => 7200,
            'refresh_exp' => 604800,
        ];
        // 使用新的 JwtToken 生成 token
        $jwt = new JwtToken();
        $tokenObj = $jwt->generate((string)$member->id, 'api', $userInfo);
        $token = [
            'access_token' => $tokenObj->accessToken,
            'refresh_token' => $tokenObj->refreshToken,
            'expires_in' => $tokenObj->expiresIn
        ];

        // 获取会员权限码
        $permissions = $this->getMemberPermissions($member);

        return array_merge($token, [
            'user_info'   => $userInfo,
            'permissions' => $permissions,
        ]);
    }

    public function loginWithMobile(array $data): array
    {
        if (empty($data['mobile']) || empty($data['code'])) {
            throw new \Exception('手机号和验证码不能为空', 400);
        }

        // 验证验证码
        $this->verifySmsCode($data['mobile'], $data['code']);

        // 查找用户
        $member = $this->dao->query()->where('phone', $data['mobile'])->first();

        if (!$member) {
            // 自动注册
            $member = $this->autoRegisterWithMobile($data['mobile']);
        }

        // 验证用户状态
        if ($member->enabled !== EnabledStatus::ENABLED->value) {
            throw new \Exception('用户已被禁用', 401);
        }

        // 更新登录信息
        $this->updateLoginInfo($member);

        // 生成token
        $userInfo = [
            'id'          => $member->id,
            'username'    => $member->username,
            'nickname'    => $member->nickname,
            'avatar'      => $member->avatar,
            'phone'       => $member->phone,
            'email'       => $member->email,
            'client'      => 'web',
            'access_exp'  => 7200,
            'refresh_exp' => 604800,
        ];
        // 使用新的 JwtToken 生成 token
        $jwt = new JwtToken();
        $tokenObj = $jwt->generate((string)$member->id, 'api', $userInfo);
        $token = [
            'access_token' => $tokenObj->accessToken,
            'refresh_token' => $tokenObj->refreshToken,
            'expires_in' => $tokenObj->expiresIn
        ];

        // 获取会员权限码
        $permissions = $this->getMemberPermissions($member);

        return [
            'token'       => $token,
            'user_info'   => [
                'id'       => $member->id,
                'username' => $member->username,
                'nickname' => $member->nickname,
                'avatar'   => $member->avatar,
                'phone'    => $member->phone,
                'email'    => $member->email,
                'points'   => $member->points,
                'balance'  => $member->balance,
            ],
            'permissions' => $permissions,
        ];
    }

    /**
     * 用户退出登录
     */
    public function logout(): array
    {
        $request = \request();
        $token   = $request->header('Authorization');

        if ($token) {
            // 处理 token 前缀
            if (str_starts_with($token, 'Bearer ')) {
                $token = substr($token, 7);
            }
            // 退出登录，将 token 加入黑名单防止被复用
            $loggedOut = (new JwtToken())->logout($token);
            if (!$loggedOut) {
                // logout 内部已记录错误日志，此处不阻塞退出流程
            }
        }

        return [
            'message' => '退出成功',
        ];
    }

    /**
     * 用户注册
     */
    public function register(array $data): array
    {
        // 创建用户
        $memberData = [
            'username' => $data['username'],
            'password' => $data['password'],
            'nickname' => $data['username'],
            'phone'    => $data['mobile'] ?? '',
            'email'    => $data['email'] ?? null,
            'enabled'  => EnabledStatus::ENABLED->value,
        ];
        $member = $this->dao->save($memberData);

        // 更新登录信息
        $this->updateLoginInfo($member);
        $userInfo = [
            'id'          => $member->id,
            'username'    => $member->username,
            'nickname'    => $member->nickname,
            'avatar'      => $member->avatar,
            'phone'       => $member->phone,
            'email'       => $member->email,
            'client'      => 'web',
            'access_exp'  => 7200,
            'refresh_exp' => 604800,
        ];
        // 使用新的 JwtToken 生成 token
        $jwt = new JwtToken();
        $tokenObj = $jwt->generate((string)$member->id, 'api', $userInfo);
        $token = [
            'access_token' => $tokenObj->accessToken,
            'refresh_token' => $tokenObj->refreshToken,
            'expires_in' => $tokenObj->expiresIn
        ];
        // 获取会员权限码（新注册用户权限通常为空）
        $permissions = $this->getMemberPermissions($member);

        return array_merge($token, [
            'user_info'   => [
                'id'       => $member->id,
                'username' => $member->username,
                'nickname' => $member->nickname,
                'avatar'   => $member->avatar,
                'phone'    => $member->phone,
                'email'    => $member->email,
                'points'   => $member->points,
                'balance'  => $member->balance,
            ],
            'permissions' => $permissions,
        ]);
    }

    /**
     * 手机号注册
     */
    public function registerWithMobile(array $data): array
    {
        if (empty($data['mobile']) || empty($data['code']) || empty($data['password'])) {
            throw new \Exception('手机号、验证码和密码不能为空', 400);
        }

        // 验证验证码
        $this->verifySmsCode($data['mobile'], $data['code']);

        // 检查手机号是否已存在
        if ($this->dao->query()->where('phone', $data['mobile'])->exists()) {
            throw new \Exception('手机号已存在', 400);
        }

        // 创建用户
        $memberData = [
            'username' => 'user_' . substr($data['mobile'], -4),
            'password' => $data['password'],
            'nickname' => '用户_' . substr($data['mobile'], -4),
            'phone'    => $data['mobile'],
            'enabled'  => EnabledStatus::ENABLED->value,
        ];

        $member = $this->dao->create($memberData);

        return [
            'user_id' => $member->id,
        ];
    }

    /**
     * 绑定手机号
     */
    public function bindMobile(array $data): array
    {
        if (empty($data['mobile']) || empty($data['code'])) {
            throw new \Exception('手机号和验证码不能为空', 400);
        }

        // 验证验证码
        $this->verifySmsCode($data['mobile'], $data['code']);

        // 获取当前用户
        $memberId = $this->getCurrentMemberId();
        $member   = $this->dao->find($memberId);

        if (!$member) {
            throw new \Exception('用户不存在', 401);
        }

        // 检查手机号是否已被其他用户绑定
        $existingMember = $this->dao->query()
            ->where('phone', $data['mobile'])
            ->where('id', '!=', $memberId)
            ->first();

        if ($existingMember) {
            throw new \Exception('手机号已被其他用户绑定', 400);
        }

        // 更新手机号
        $member->phone = $data['mobile'];
        $member->save();

        return [
            'message' => '绑定成功',
        ];
    }

    /**
     * 更新登录信息
     */
    private function updateLoginInfo(Member $member): void
    {
        $member->last_login_time = time();
        $member->last_login_ip   = \request()->getRealIp();
        $member->login_count     = $member->login_count + 1;
        $member->save();
    }

    /**
     * 生成token
     */
    private function generateToken(int $memberId): string
    {
        $token      = md5(uniqid() . $memberId . microtime(true));
        $expireTime = 7 * 24 * 3600; // 7天过期

        // 存储token到Redis
        Redis::setex('user_token:' . md5($token), $expireTime, json_encode([
            'member_id'  => $memberId,
            'login_time' => time(),
        ]));

        return $token;
    }

    /**
     * 验证短信验证码
     */
    private function verifySmsCode(string $mobile, string $code): void
    {
        $cacheKey   = 'sms_code:' . $mobile;
        $cachedCode = Redis::get($cacheKey);

        if (!$cachedCode || $cachedCode !== $code) {
            throw new \Exception('验证码错误或已过期', 400);
        }

        // 验证成功后删除验证码
        Redis::del($cacheKey);
    }

    /**
     * 验证邮箱和验证码（忘记密码第一步）
     *
     * @throws \Exception
     */
    public function verifyEmail(array $data): array
    {
        // 验证参数
        if (empty($data['email']) || empty($data['code'])) {
            throw new \Exception('邮箱和验证码不能为空', 400);
        }

        // 验证验证码
        if (isset($data['captcha_key']) && isset($data['captcha_code'])) {
            $this->verifyCaptcha($data['captcha_key'], $data['captcha_code']);
        }

        // 检查邮箱是否存在
        $member = $this->dao->query()->where('email', $data['email'])->first();

        // 生成临时token用于第二步密码重置
        $resetToken = $this->generateResetToken($member->id);

        return [
            'reset_token' => $resetToken,
            'email'       => $member->email,
        ];
    }

    /**
     * 重置密码（忘记密码第二步）
     *
     * @throws \Exception
     */
    public function forgetPassword(array $data): void
    {
        // 验证参数
        if (empty($data['email']) || empty($data['code']) || empty($data['new_password'])) {
            throw new \Exception('邮箱、验证码和新密码不能为空', 400);
        }

        // 验证新密码和确认密码是否一致
        if (isset($data['confirm_password']) && $data['new_password'] !== $data['confirm_password']) {
            throw new \Exception('两次输入的密码不一致', 400);
        }

        // 验证重置token（如果使用）
        if (isset($data['reset_token'])) {
            $this->validateResetToken($data['reset_token']);
        }

        // 查找用户
        $member = $this->dao->query()->where('email', $data['email'])->first();
        if (!$member) {
            throw new \Exception('用户不存在', 404);
        }

        // 验证用户状态
        if ($member->enabled !== EnabledStatus::ENABLED->value) {
            throw new \Exception('用户已被禁用', 401);
        }

        // 验证邮箱验证码
        $cacheKey   = 'email_code:' . $data['email'];
        $cachedCode = Redis::get($cacheKey);

        if (!$cachedCode || $cachedCode !== $data['code']) {
            throw new \Exception('邮箱验证码错误或已过期', 400);
        }

        // 更新密码
        $member->password = $data['new_password'];
        $member->save();

        // 清除验证码
        Redis::del($cacheKey);
    }

    /**
     * 验证图片验证码
     *
     * @throws \Exception
     */
    private function verifyCaptcha(string $key, string $code): void
    {
        $cachedCode = Redis::get('captcha:' . $key);

        if (!$cachedCode || strtoupper($cachedCode) !== strtoupper($code)) {
            throw new \Exception('验证码错误', -1);
        }
        // 验证成功后删除验证码
        Redis::del('captcha:' . $key);
    }

    /**
     * 生成密码重置token
     */
    private function generateResetToken(int $memberId): string
    {
        $token = md5(uniqid() . $memberId . microtime(true));

        // 存储token到Redis，有效期30分钟
        Redis::setex('password_reset:' . $token, 1800, json_encode([
            'member_id' => $memberId,
            'expire'    => time() + 1800,
        ]));

        return $token;
    }

    /**
     * 验证密码重置token
     *
     * @throws \Exception
     */
    private function validateResetToken(string $token): void
    {
        $tokenData = Redis::get('password_reset:' . $token);
        if (!$tokenData) {
            throw new \Exception('重置链接已过期，请重新获取', 400);
        }
        $data = json_decode($tokenData, true);
        if ($data['expire'] < time()) {
            Redis::del('password_reset:' . $token);
            throw new \Exception('重置链接已过期，请重新获取', 400);
        }
    }

    /**
     * 发送邮箱验证码
     *
     * @throws \Exception
     */
    public function sendEmailCode(string $email): void
    {
        // 验证邮箱格式
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('邮箱格式不正确', 400);
        }

        // 检查邮箱是否存在
        $member = $this->dao->query()->where('email', $email)->first();
        if (!$member) {
            throw new \Exception('该邮箱未注册', 404);
        }

        // 检查是否频繁发送
        $cacheKey = 'email_code_limit:' . $email;
        $lastSend = Redis::get($cacheKey);

        if ($lastSend && (time() - $lastSend) < 60) {
            throw new \Exception('发送过于频繁，请稍后再试', 400);
        }

        // 生成验证码
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 存储验证码到Redis，有效期5分钟
        Redis::setex('email_code:' . $email, 300, $code);

        // 记录发送时间
        Redis::setex('email_code_limit:' . $email, 60, time());

        // 获取系统配置
        $configService = Container::make(ConfigService::class);
        $siteConfig    = $configService->config('web_site_setting');
        $siteName      = $siteConfig['site_name'] ?? 'madong.tech';

        // 构建邮件主题和内容
        $subject = $siteName . '社区找回密码';
        $content = $this->buildResetPasswordEmailTemplate($code, $member->nickname ?? $member->username);

        // 发送邮件
        /** @var MailService $mailService */
        $mailService = Container::make(MailService::class);
        $mailService->send($email, $subject, $content);
    }

    /**
     * 构建密码重置邮件模板
     *
     * @param string $code     验证码
     * @param string $nickname 用户昵称
     *
     * @return string HTML邮件内容
     */
    private function buildResetPasswordEmailTemplate(string $code, string $nickname): string
    {
        // 获取系统配置
        $configService = Container::make(ConfigService::class);
        $siteConfig    = $configService->config('web_site_setting');
        $siteName      = $siteConfig['site_name'] ?? "madong.tech";
        $year          = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>密码重置验证码</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .email-header {
            background-color: #f8f9fa;
            padding: 30px 40px;
            border-bottom: 1px solid #e9ecef;
        }
        .email-header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }
        .email-body {
            padding: 40px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            color: #333333;
        }
        .message {
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.6;
            color: #555555;
        }
        .code-section {
            background-color: #f8f9fa;
            border-left: 4px solid #4a90e2;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 0 4px 4px 0;
        }
        .code-label {
            font-size: 14px;
            color: #666666;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .code {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }
        .expiry-notice {
            font-size: 12px;
            color: #999999;
            margin-top: 10px;
        }
        .security-tip {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        .security-tip h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }
        .security-tip ul {
            margin: 0;
            padding-left: 20px;
            font-size: 12px;
            color: #666666;
            line-height: 1.5;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 30px 40px;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #999999;
        }
        .site-name {
            font-weight: 600;
            color: #4a90e2;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>密码重置验证</h1>
        </div>
        <div class="email-body">
            <div class="greeting">尊敬的 {$nickname}，</div>
            <div class="message">
                您正在请求重置 <span class="site-name">{$siteName}</span> 账户的密码。请使用以下验证码完成身份验证：
            </div>
            <div class="code-section">
                <div class="code-label">验证码</div>
                <div class="code">{$code}</div>
                <div class="expiry-notice">验证码有效期为 5 分钟</div>
            </div>
            <div class="security-tip">
                <h3>安全提示</h3>
                <ul>
                    <li>请勿将此验证码透露给任何第三方</li>
                    <li>如果您没有发起此请求，请忽略此邮件</li>
                    <li>为了账户安全，建议定期更换密码</li>
                </ul>
            </div>
        </div>
        <div class="email-footer">
            <p>此邮件由 <span class="site-name">{$siteName}</span> 系统自动发送，请勿直接回复</p>
            <p>© {$year} {$siteName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 通过手机号自动注册
     */
    private function autoRegisterWithMobile(string $mobile): Member
    {
        $memberData = [
            'username' => 'user_' . substr($mobile, -4),
            'password' => substr(md5(uniqid()), 0, 8),
            'nickname' => '用户_' . substr($mobile, -4),
            'phone'    => $mobile,
            'enabled'  => EnabledStatus::ENABLED->value,
        ];

        return $this->dao->create($memberData);
    }

    /**
     * 获取当前用户ID
     */
    private function getCurrentMemberId(): int
    {
        try {
            // 使用新的 JwtToken 获取当前用户ID
            $jwt = new JwtToken();
            $mid = $jwt->id();
            return $mid ?? 0;
        } catch (\Exception $e) {
            throw new \Exception('登录已过期', 401);
        }
    }

    /**
     * 获取会员权限码列表（通过标签获取菜单权限码）
     *
     * @param Member $member
     * @return array 权限码数组
     */
    private function getMemberPermissions(Member $member): array
    {
        try {
            $permissions = [];
            $tags = $member->tags()->with('permissions')->where('enabled', 1)->get();
            foreach ($tags as $tag) {
                foreach ($tag->permissions as $permission) {
                    if (!empty($permission->code)) {
                        $permissions[] = $permission->code;
                    }
                }
            }
            return array_unique($permissions);
        } catch (\Exception $e) {
            // 获取权限失败不影响登录
            return [];
        }
    }
}