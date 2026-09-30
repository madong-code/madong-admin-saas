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

namespace core\communication\mcp\server;

use core\communication\mcp\session\RedisSessionStore;
use core\communication\mcp\security\McpUser;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\Log\LoggerInterface;

/**
 * MCP Server 工厂：逐请求构建（构建后一次性 run），把"当前用户/权限"显式注入工具实例
 *
 * 工具以 [class, method] 形式注册，入参按 inputSchema 属性名经 SDK 反射以命名参数注入；
 * 工具实例经 McpInstanceContainer 提供给 SDK，保证身份一致。
 */
final class ServerFactory
{
    /**
     * @param array<int, array{class:string,method:string,name:string,title:string,description:string,inputSchema:array,permission:mixed,source:string}> $tools 已按权限过滤的工具清单
     */
    public function build(array $tools, ?McpUser $user, LoggerInterface $logger): Server
    {
        $config = (array) config('mcp', []);

        $instances = [];
        foreach ($tools as $entry) {
            $class = (string) $entry['class'];
            $instances[$class] = new $class($user);
        }

        $builder = Server::builder()
            ->setServerInfo(
                (string) ($config['server_name'] ?? 'madong'),
                (string) ($config['server_version'] ?? '1.0.0'),
            )
            ->setLogger($logger)
            ->setContainer(new McpInstanceContainer($instances))
            ->setSession($this->createSessionStore($config));

        foreach ($tools as $entry) {
            $builder->addTool(
                [(string) $entry['class'], (string) $entry['method']],
                (string) $entry['name'],
                title: $entry['title'] !== '' ? (string) $entry['title'] : null,
                description: (string) $entry['description'],
                inputSchema: (array) $entry['inputSchema'],
            );
        }

        return $builder->build();
    }

    private function createSessionStore(array $config): SessionStoreInterface
    {
        $session = (array) ($config['session'] ?? []);
        $ttl = (int) ($session['ttl'] ?? 3600);

        if ((string) ($session['store'] ?? 'file') === 'redis') {
            return new RedisSessionStore($ttl);
        }
        return new FileSessionStore(
            (string) ($session['path'] ?? runtime_path('mcp/sessions')),
            $ttl,
        );
    }
}
