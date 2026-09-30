<?php
/**
 * This file is part of webman.
 * Licensed under The MIT License
 * For full copyright and license information, please see MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Request;
use Webman\Route;
use app\middleware\global\CheckInstallMiddleware;
use app\middleware\global\FileAccessMiddleware;

/**
 * 后台模块路由
 */
require_once app_path('adminapi/config/route.php');

/**
 * 平台模块路由
 */
require_once app_path('platform/config/route.php');

/**
 * 前台模块路由
 */
require_once app_path('api/config/route.php');


/**
 * 租户测试平台视图
 * 访问路径: /tenant, /tenant/db-setting, /tenant/column-permission
 */
Route::get('/tenant[/{path}]', function (Request $request, $path = '') {
    $path = $path ?: 'index';
    $file = public_path() . '/tenant/' . $path . '.html';
    if (str_contains($path, '..') || !is_file($file)) {
        return response('<h1>404 Not Found</h1>', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html',
    ]);
});

// 安装页面
Route::get('/install', function () {
    $file = public_path() . '/install/index.html';
    if (!file_exists($file)) {
        return response('File not found', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html',
    ]);
});

// 站点首页
Route::get('/', function () {
    return redirect('/web');
})->middleware(CheckInstallMiddleware::class);

// 后台管理
Route::get('/admin', function () {
    $file = public_path() . '/admin/index.html';
    if (!file_exists($file)) {
        return response('File not found', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html',
    ]);
})->middleware(CheckInstallMiddleware::class);

// 平台管理
Route::get('/platform', function () {
    $file = public_path() . '/platform/index.html';
    if (!file_exists($file)) {
        return response('File not found', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html',
    ]);
})->middleware(CheckInstallMiddleware::class);

/**
 * 后台静态资源路由
 * 访问路径: /admin/*
 * 资源目录: public/admin/
 * 无需权限验证
 */
Route::any('/admin/[{path:.+}]', function (Request $request, $path = '') {
    $admin_path = public_path() . '/admin';
    // 安全检查，避免url里 /../../../password 这样的非法访问
    if (str_contains($path, '..')) {
        return response('<h1>400 Bad Request</h1>', 400);
    }
    // 文件
    $file = "$admin_path/$path";
    if (!is_file($file)) {
        return response('<h1>404 Not Found</h1>', 404);
    }
    return response('')->withFile($file);
});

/**
 * 平台静态资源路由
 * 访问路径: /platform/*
 * 资源目录: public/platform/
 * 无需权限验证
 */
Route::any('/platform/[{path:.+}]', function (Request $request, $path = '') {
    $platform_path = public_path() . '/platform';
    // 安全检查
    if (str_contains($path, '..')) {
        return response('<h1>400 Bad Request</h1>', 400);
    }
    $file = "$platform_path/$path";
    if (!is_file($file)) {
        // SPA 支持：文件不存在返回 index.html 让前端路由处理
        $index_file = "$platform_path/index.html";
        if (is_file($index_file)) {
            return response(file_get_contents($index_file), 200, [
                'Content-Type' => 'text/html',
            ]);
        }
        return response('<h1>404 Not Found</h1>', 404);
    }
    return response('')->withFile($file);
});

Route::any('/install/[{path:.+}]', function (Request $request, $path = '') {
    $install_path = public_path() . '/install';
    // 安全检查，避免url里 /../../../password 这样的非法访问
    if (str_contains($path, '..')) {
        return response('<h1>400 Bad Request</h1>', 400);
    }
    // 文件
    $file = "$install_path/$path";
    if (!is_file($file)) {
        return response('<h1>404 Not Found</h1>', 404);
    }
    return response('')->withFile($file);
});

Route::any('/web/[{path:.+}]', function (Request $request, $path = '') {
    $web_path = public_path() . '/web';
    // 安全检查，避免url里 /../../../password 这样的非法访问
    if (str_contains($path, '..')) {
        return response('<h1>400 Bad Request</h1>', 400);
    }
    // 文件
    $file = "$web_path/$path";
    if (!is_file($file)) {
        // 对于SPA应用，如果文件不存在，返回index.html让前端路由处理
        $index_file = "$web_path/index.html";
        if (is_file($index_file)) {
            return response(file_get_contents($index_file), 200, [
                'Content-Type' => 'text/html',
            ]);
        }
        return response('<h1>404 Not Found</h1>', 404);
    }
    return response('')->withFile($file);
});

// 移动端
Route::get('/mobile', function () {
    $file = public_path() . '/mobile/index.html';
    if (!file_exists($file)) {
        return response('File not found', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html',
    ]);
})->middleware(CheckInstallMiddleware::class);

// PC端
Route::get('/web', function () {
    $file = public_path() . '/web/index.html';
    if (!file_exists($file)) {
        return response('File not found', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html',
    ]);
})->middleware(CheckInstallMiddleware::class);

/**
 * 静态资源路由
 * 访问路径: /static/*
 * 无需权限验证
 */
Route::any('/static/[{path:.+}]', function (Request $request, $path = '') {
    $static_base_path = base_path() . '/static';
    // 安全检查，避免url里 /../../../password 这样的非法访问
    if (str_contains($path, '..')) {
        return response('<h1>400 Bad Request</h1>', 400);
    }
    // 文件
    $file = "$static_base_path/$path";
    if (!is_file($file)) {
        return response('<h1>404 Not Found</h1>', 404);
    }
    return response('')->withFile($file);
});

/**
 * 文件上传读取路由
 * 访问路径: /uploads/*
 * 需要权限验证
 */
Route::any('/upload/[{path:.+}]', function (Request $request, $path = '') {
    // 统一路径分隔符，兼容 Windows / Linux
    $upload_path = rtrim(public_path(), '/\\') . DIRECTORY_SEPARATOR . 'upload';
    $path        = ltrim($path, '/\\');
    // 安全检查，避免url里 /../../../password 这样的非法访问
    if (str_contains($path, '..')) {
        return response('<h1>400 Bad Request</h1>', 400);
    }
    // 文件（将 URL 斜杠转为系统分隔符）
    $file = $upload_path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($file)) {
        return response('<h1>404 Not Found</h1>', 404);
    }
    // 响应文件（自动带正确 Content-Type）
    return response('')->withFile($file);
})->middleware(FileAccessMiddleware::class);


/**
 * 根目录资源文件（如 /logo.png, /favicon.ico）
 * 显式列出已知文件，避免万能匹配导致路由冲突
 */
Route::get('/logo.png', function () {
    $file = public_path() . '/logo.png';
    if (!is_file($file)) {
        return response('', 404);
    }
    return response('')->withFile($file);
});
Route::get('/favicon.ico', function () {
    $file = public_path() . '/favicon.ico';
    if (!is_file($file)) {
        return response('', 404);
    }
    return response('')->withFile($file);
});

/**
 * MCP 端点路由（core/communication 域）
 * 必须在 Route::disableDefaultRoute() 之前注册；固定路径 /mcp，不挂常用中间件分组
 */
require_once base_path('core/communication/mcp/route.php');

/**
 * 关闭默认路由
 */
Route::disableDefaultRoute();
