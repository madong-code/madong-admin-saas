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
namespace core\infrastructure\scheduler;

class Client
{

    /**
     * 向redis 发送请求
     *
     * @param array $param
     *
     * @return bool
     * @throws \Exception
     */
    public static function request(array $param): bool
    {
        $listen = 'tcp://' . config('core.infrastructure.scheduler.listen');
        try{
            $client = stream_socket_client($listen);
            fwrite($client, json_encode($param) . "\n"); // text协议末尾有个换行符"\n"
            $result = fgets($client);
            $arr = json_decode($result,true);
            if ($arr['code'] == 200){
                return true;
            }
            return false;
        }catch (\Throwable $e){
            throw new \Exception("请求任务server失败,请检查防火墙或者配置端口是否一致，{$listen}");
        }
    }

}
