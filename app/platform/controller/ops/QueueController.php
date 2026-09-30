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
namespace app\platform\controller\ops;

use app\platform\controller\Base;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use Webman\RedisQueue\Client;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class QueueController extends Base
{
    protected array $availableQueues = [
        ['name' => 'remove-excel-file', 'desc' => '删除Excel文件队列'],
        ['name' => 'admin-announcement-push', 'desc' => '管理员公告推送队列'],
    ];

    #[OA\Get(
        path: '/queues',
        summary: '获取队列列表',
        tags: ['队列管理']
    )]
    #[SimpleResponse]
    public function index(Request $request): \support\Response
    {
        try {
            return Json::success('ok', $this->availableQueues);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/queues/{name}/stats',
        summary: '获取队列状态',
        tags: ['队列管理'],
        parameters: [
            new OA\Parameter(name: 'name', description: '队列名称', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[SimpleResponse]
    public function stats(Request $request): \support\Response
    {
        try {
            $queueName = $request->route->param('name');
            
            if (empty($queueName)) {
                return Json::fail('队列名称不能为空', [], 400);
            }

            $redis = redis();
            $pendingKey = 'redis-queue:pending:' . $queueName;
            $reservedKey = 'redis-queue:reserved:' . $queueName;
            
            $pending = $redis->llen($pendingKey);
            $reserved = $redis->llen($reservedKey);
            
            return Json::success('ok', [
                'queue' => $queueName,
                'pending' => $pending,
                'reserved' => $reserved,
                'total' => $pending + $reserved,
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/queues/{name}/messages',
        summary: '发送消息到队列',
        tags: ['队列管理'],
        parameters: [
            new OA\Parameter(name: 'name', description: '队列名称', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(property: 'data', description: '消息数据', type: 'object'),
                ]
            )
        )
    )]
    #[SimpleResponse]
    public function send(Request $request): \support\Response
    {
        try {
            $queueName = $request->route->param('name');
            $data = $request->post('data', []);
            
            if (empty($queueName)) {
                return Json::fail('队列名称不能为空', [], 400);
            }

            $client = new Client();
            $client->send($queueName, $data);
            
            return Json::success('消息已发送到队列', [
                'queue' => $queueName,
                'data' => $data,
                'time' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/queues/{name}/messages',
        summary: '清空队列消息',
        tags: ['队列管理'],
        parameters: [
            new OA\Parameter(name: 'name', description: '队列名称', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[SimpleResponse]
    public function clear(Request $request): \support\Response
    {
        try {
            $queueName = $request->route->param('name');
            
            if (empty($queueName)) {
                return Json::fail('队列名称不能为空', [], 400);
            }

            $redis = redis();
            $redis->del('redis-queue:pending:' . $queueName);
            $redis->del('redis-queue:reserved:' . $queueName);
            
            return Json::success('队列已清空', ['queue' => $queueName]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/queues/{name}/messages',
        summary: '获取队列待处理消息',
        tags: ['队列管理'],
        parameters: [
            new OA\Parameter(name: 'name', description: '队列名称', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', description: '获取数量', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ]
    )]
    #[SimpleResponse]
    public function messages(Request $request): \support\Response
    {
        try {
            $queueName = $request->route->param('name');
            $limit = (int)$request->input('limit', 10);
            
            if (empty($queueName)) {
                return Json::fail('队列名称不能为空', [], 400);
            }

            $redis = redis();
            $pendingKey = 'redis-queue:pending:' . $queueName;
            $messages = $redis->lrange($pendingKey, 0, $limit - 1);
            
            $decodedMessages = [];
            foreach ($messages as $message) {
                try {
                    $decodedMessages[] = json_decode($message, true);
                } catch (\Throwable $e) {
                    $decodedMessages[] = $message;
                }
            }
            
            return Json::success('ok', [
                'queue' => $queueName,
                'count' => count($decodedMessages),
                'messages' => $decodedMessages,
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
