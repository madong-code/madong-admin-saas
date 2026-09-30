<?php
/**
 * This file is part of webman.
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use app\middleware\global\RateLimiterMiddleware;
use app\middleware\admin\TenantMiddleware;
use app\middleware\admin\SubscriptionMiddleware;

return [
    // 平台管理端中间件
    'platformapi' => [
        RateLimiterMiddleware::class,//限流中间件
    ],
    //应用中间件
    'adminapi' => [
        RateLimiterMiddleware::class,//限流中间件
        TenantMiddleware::class,//租户上下文中间件
        SubscriptionMiddleware::class,//订阅验证中间件（可选）
    ],
    // 超全局中间件-覆盖插件
    '@'        => [
        \app\middleware\global\CheckInstallMiddleware::class,//安装检查中间件（最外层）
        \app\middleware\global\CleanupMiddleware::class,//请求级兜底清理（最外层，cleanup 在最终阶段执行）
        \app\middleware\global\AllowCrossOriginMiddleware::class,//跨域中间件
        \app\middleware\global\Lang::class,//多语言切换中间件
        \app\middleware\global\PlatformSwitchMiddleware::class,//平台级站点开关中间件
    ],
    // 全局中间件-主项目有效
    ''         => [

    ],

    // 用户端 API 中间件
    'api'      => [
        \app\middleware\api\TenantIdentifyMiddleware::class,    //前端租户识别中间件（不强制登录，纯识别租户上下文）
        \app\middleware\api\SiteSwitchMiddleware::class,        //单体级站点开关中间件（仅单体模式生效）
        \app\middleware\api\TenantSiteSwitchMiddleware::class,  //租户级站点开关中间件（仅多租户模式生效）
    ],
];
