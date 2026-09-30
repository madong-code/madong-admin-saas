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
namespace app\service\api\system;

use app\model\system\config\Config;
use core\foundation\base\BaseService;

/**
 * 协议服务
 */
class AgreementService extends BaseService
{
    /**
     * 获取协议内容
     */
    public function getAgreementContent(string $key): array
    {
        $agreements = [
            'privacy' => [
                'title' => '隐私政策',
                'content' => $this->getPrivacyPolicyContent()
            ],
            'user' => [
                'title' => '用户协议',
                'content' => $this->getUserAgreementContent()
            ],
            'service' => [
                'title' => '服务条款',
                'content' => $this->getServiceTermsContent()
            ]
        ];

        if (!isset($agreements[$key])) {
            throw new \Exception('协议不存在', 404);
        }

        return [
            'code' => 200,
            'msg' => '获取成功',
            'data' => $agreements[$key]
        ];
    }

    /**
     * 获取隐私政策内容
     */
    private function getPrivacyPolicyContent(): string
    {
        return "# 隐私政策

## 1. 信息收集
我们收集您在使用服务时主动提供的信息，包括但不限于姓名、联系方式等。

## 2. 信息使用
我们使用收集的信息来提供和改进服务，保护用户安全，并遵守法律法规。

## 3. 信息共享
除非获得您的明确同意，否则我们不会与第三方分享您的个人信息。

## 4. 信息安全
我们采取合理的安全措施保护您的个人信息安全。

## 5. 政策变更
我们可能会适时更新本隐私政策，请定期查看最新版本。";
    }

    /**
     * 获取用户协议内容
     */
    private function getUserAgreementContent(): string
    {
        return "# 用户协议

## 1. 接受条款
通过使用我们的服务，您同意遵守本协议的所有条款和条件。

## 2. 用户注册
您需要提供真实、准确、完整的注册信息，并保持信息的及时更新。

## 3. 使用规则
您承诺遵守法律法规，不得利用服务从事任何违法或不正当的活动。

## 4. 服务变更
我们保留随时修改、暂停或终止服务的权利，恕不另行通知。

## 5. 责任限制
在法律允许的范围内，我们对服务的适用性、可靠性不作任何保证。";
    }

    /**
     * 获取服务条款内容
     */
    private function getServiceTermsContent(): string
    {
        return "# 服务条款

## 1. 服务内容
我们提供在线服务，包括但不限于信息展示、用户交互等功能。

## 2. 服务费用
基础服务免费提供，部分增值服务可能需要支付相应费用。

## 3. 服务中断
因系统维护、升级等原因，服务可能会暂时中断，我们将尽力减少影响。

## 4. 用户责任
用户应妥善保管账户信息，对其账户下的所有活动承担全部责任。

## 5. 终止服务
如用户违反协议条款，我们有权终止提供服务。";
    }
}