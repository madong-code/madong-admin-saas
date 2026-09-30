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
namespace app\middleware\global;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 文件访问权限中间件
 * 用于控制上传文件的访问权限
 */
class FileAccessMiddleware implements MiddlewareInterface
{

    public function process(Request $request, callable $handler): Response
    {
//        $path = $request->path;
//        $path = str_replace('/upload/', '', $path);
//
//        // 公开文件类型，无需权限验证
//        $publicExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'css', 'js', 'woff', 'woff2', 'ttf', 'eot'];
//
//        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
//
//        if (in_array($extension, $publicExtensions)) {
//            return $handler($request);
//        }

//        // 私密文件需要身份验证
//        if (!$request->user) {
//            return json([
//                'code' => 401,
//                'msg' => '请先登录',
//                'data' => null
//            ], 401);
//        }
//
//        // 根据文件路径进行权限控制
//        // 示例：某些敏感文件只能管理员访问
//        if (str_starts_with($path, 'private/')) {
//            $user = $request->user;
//            if (!isset($user['roles']) || !in_array('R_SUPER', $user['roles'] ?? [])) {
//                return json([
//                    'code' => 403,
//                    'msg' => '无权访问',
//                    'data' => null
//                ], 403);
//            }
//        }
//
//        // 用户个人文件访问控制
//        // 例如：/uploads/avatar/user_123.png 只有 user_123 或管理员可以访问
//        if (preg_match('/^(avatar|document|private)\/(\d+)_/', $path, $matches)) {
//            $userId = $matches[2];
//            $user = $request->user;
//
//            // 管理员可访问所有文件
//            if (isset($user['roles']) && in_array('R_SUPER', $user['roles'] ?? [])) {
//                return $handler($request);
//            }
//
//            // 普通用户只能访问自己的文件
//            if ($user['id'] != $userId) {
//                return json([
//                    'code' => 403,
//                    'msg' => '无权访问此文件',
//                    'data' => null
//                ], 403);
//            }
//        }

        return $handler($request);
    }
}
