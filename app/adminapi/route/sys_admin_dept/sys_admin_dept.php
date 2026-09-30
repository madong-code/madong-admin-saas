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
// ============================================================
// SysAdminDept 模块路由
// 由代码生成器自动生成，请勿手动修改
// ============================================================

use app\adminapi\controller\sys_admin_dept\SysAdminDeptController;
use madong\swagger\util\RouteUtil;

// 路由通过 Swagger 注解自动注册
// Controller 类路径用于 OpenAPI 扫描
RouteUtil::registerRoutes(SysAdminDeptController::class);
