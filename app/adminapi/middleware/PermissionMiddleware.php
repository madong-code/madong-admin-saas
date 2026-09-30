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

namespace app\adminapi\middleware;

use app\adminapi\middleware\helper\SseHelper;
use app\adminapi\CurrentUser;
use madong\swagger\attribute\Permission;
use core\foundation\exception\handler\UnauthorizedHttpException;
use core\security\jwt\JwtToken;
use core\foundation\tool\Json;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\helper\AnnotationHelper;
use support\Container;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 方法级权限校验中间件
 * 功能：
 * 1. 支持 SkipAuth 注解跳过权限校验
 * 2. 校验类/方法级的 Permission 注解
 * 3. 处理 AND/OR 操作类型的权限逻辑
 */
#\[\Attribute\]
final class PermissionMiddleware implements MiddlewareInterface
{
    /**
     * Webman 中间件入口
     *
     * @throws \core\exception\handler\ForbiddenHttpException|\core\exception\handler\UnauthorizedHttpException
     */
    public function process(Request $request, callable $handler): Response
    {

        $controllerClass = $request->controller;
        $action          = $request->action;
        $allowAnonymous        = AnnotationHelper::getMethodAnnotation($controllerClass, $action, AllowAnonymous::class);
        if ($allowAnonymous && !$allowAnonymous->requirePermission) {
            return $handler($request);
        }
        try {
            $userId = (new JwtToken())->id();
            if (empty($userId)) {
                throw new UnauthorizedHttpException();
            }
        } catch (\Exception $e) {
            // 检查是否为SSE请求
            if (SseHelper::isSseRequest($request)) {
                return SseHelper::sendSseErrorViaConnection($request, $e->getMessage());
            }
            return Json::fail($e->getMessage(), [], 401);
        }
        // 使用CurrentUser进行权限验证
        $currentUser = Container::make(CurrentUser::class);
        
        // 顶级管理员直接跳过权限验证
        if ($currentUser->isSuperAdmin()) {
            return $handler($request);
        }

        try {
            //权限验证
            $permissions = $this->resolvePermissions($controllerClass, $action);
            $this->validatePermissions($currentUser, $permissions);
        } catch (\Exception $e) {
            // 检查是否为SSE请求
            if (SseHelper::isSseRequest($request)) {
                return SseHelper::sendSseErrorViaConnection($request, $e->getMessage());
            }
            throw $e;
        }

        return $handler($request);
    }

    /**
     * 解析类/方法级的 Permission 注解
     *
     * @return array<Permission> 合并后的权限注解列表
     */
    private function resolvePermissions(string $controllerClass, string $action): array
    {
        $permissions = [];

        // a. 类级别注解（作用于整个控制器）
        $classAnnotation = AnnotationHelper::getClassAnnotation($controllerClass, Permission::class);
        if ($classAnnotation) {
            $permissions[] = $classAnnotation;
        }

        // b. 方法级别注解（覆盖/补充类级别规则）
        $methodAnnotation = AnnotationHelper::getMethodAnnotation($controllerClass, $action, Permission::class);
        if ($methodAnnotation) {
            $permissions[] = $methodAnnotation;
        }

        return $permissions;
    }

    /**
     * 校验用户权限是否符合注解规则
     *
     * @throws \core\exception\handler\ForbiddenHttpException
     */
    private function validatePermissions(CurrentUser $currentUser, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->handleSinglePermission($currentUser, $permission);
        }
    }

    /**
     * 处理 Permission 注解的权限校验逻辑
     *
     * @param CurrentUser $currentUser 当前用户
     * @param Permission  $permission  权限注解
     *
     * @throws \core\exception\handler\ForbiddenHttpException
     */
    private function handleSinglePermission(
        CurrentUser $currentUser,
        Permission  $permission
    ): void
    {
        // 权限码数组
        $codes = $permission->getCodes();
        // 操作类型
        $operation = $permission->getOperation();
        
        // 使用CurrentUser的checkPermission方法进行权限验证
        $currentUser->checkPermission($codes, $operation);
    }
}
