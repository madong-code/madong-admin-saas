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
namespace app\platform\middleware;

use app\platform\PlatformCurrentUser;
use app\platform\event\system\OperationLogEvent;
use madong\helper\Arr;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\helper\AnnotationHelper;
use support\Container;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

#[\Attribute]
final class OperationMiddleware implements MiddlewareInterface
{

    private PlatformCurrentUser $currentUser;

    public function __construct()
    {
        $this->currentUser = Container::make(PlatformCurrentUser::class);
    }

    public function process(Request $request, callable $handler): Response
    {
        $controllerClass = $request->controller;
        $action          = $request->action;

        $oaAnnotations = AnnotationHelper::getMethodOaAnnotations($controllerClass, $action);
        $oaData        = $this->extractOaData($oaAnnotations);
        $firstOa       = Arr::first($oaData);

        $response = $handler($request);

        // 检查是否需要记录操作日志（跳过匿名访问）
        $allowAnonymous = AnnotationHelper::getMethodAnnotation($controllerClass, $action, AllowAnonymous::class);
        if (!$allowAnonymous || $allowAnonymous->requirePermission) {
            $logData = $this->buildLogData($request, $response, $firstOa);
            $event = new OperationLogEvent(
                $logData['name'],
                $logData['oa_tags'],
                $logData['oa_description'],
                $logData['app'],
                $logData['ip'],
                $logData['ip_location'],
                $logData['browser'],
                $logData['os'],
                $logData['url'],
                $logData['class_name'],
                $logData['action'],
                $logData['method'],
                $logData['param'],
                $logData['result'],
                $logData['user_name'],
                $logData['user_id']
            );
            $event->dispatch();
        }

        return $response;
    }

    private function extractOaData(array $oaAnnotations): array
    {
        return array_map(function ($annotation) {
            return [
                'type'        => $annotation::class,
                'summary'     => $annotation->summary,
                'tags'        => $annotation->tags,
                'description' => $annotation->description ?? '',
            ];
        }, $oaAnnotations);
    }

    private function buildLogData(Request $request, Response $response, ?array $firstOa): array
    {
        $adminInfo = $this->currentUser->admin();
        return [
            'name'           => $firstOa['summary'] ?? '未知操作',
            'oa_tags'        => $firstOa['tags'] ?? [],
            'oa_description' => $firstOa['description'] ?? '',
            'app'            => $request->app,
            'ip'             => $request->getRealIp(),
            'ip_location'    => $this->getIpLocation($request->getRealIp()),
            'browser'        => $this->getBrowser($request->header('user-agent')),
            'os'             => $this->getOs($request->header('user-agent')),
            'url'            => trim($request->path()),
            'class_name'     => $request->controller,
            'action'         => $request->action,
            'method'         => $request->method(),
            'param'          => $this->filterParams($request->all()),
            'result'         => $this->formatResponse($response),
            'user_name'      => $adminInfo['user_name'] ?? '匿名用户',
            'user_id'        => $adminInfo['user_id'] ?? 0,
        ];
    }

    private function getIpLocation(string $ip): string
    {
        return '未知';
    }

    private function getBrowser($user_agent): string
    {
        $br = 'Unknown';
        if (preg_match('/MSIE/i', $user_agent)) {
            $br = 'MSIE';
        } elseif (preg_match('/Firefox/i', $user_agent)) {
            $br = 'Firefox';
        } elseif (preg_match('/Chrome/i', $user_agent)) {
            $br = 'Chrome';
        } elseif (preg_match('/Safari/i', $user_agent)) {
            $br = 'Safari';
        } elseif (preg_match('/Opera/i', $user_agent)) {
            $br = 'Opera';
        } else {
            $br = 'Other';
        }
        return $br;
    }

    private function getOs($user_agent): string
    {
        $os = 'Unknown';
        if (preg_match('/win/i', $user_agent)) {
            $os = 'Windows';
        } elseif (preg_match('/mac/i', $user_agent)) {
            $os = 'Mac';
        } elseif (preg_match('/linux/i', $user_agent)) {
            $os = 'Linux';
        } else {
            $os = 'Other';
        }
        return $os;
    }

    private function filterParams($params): string
    {
        $blackList = ['password', 'old_password', 'new_password', 'content'];
        foreach ($params as $key => $value) {
            if (in_array($key, $blackList)) {
                $params[$key] = '******';
            }
        }
        return json_encode($params, JSON_UNESCAPED_UNICODE);
    }

    private function formatResponse(Response $response): array|string
    {
        $rawBody = $response->rawBody();
        if (str_contains($rawBody, 'json')) {
            return json_decode($rawBody, true) ?? $rawBody;
        }
        return $rawBody;
    }
}
