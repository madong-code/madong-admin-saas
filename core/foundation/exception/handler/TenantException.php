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
namespace core\foundation\exception\handler;

use Exception;

/**
 * 租户异常
 * 
 * 用于处理租户相关的业务异常
 * 
 * 文档位置: docs/saas/02-核心组件.md
 */
class TenantException extends Exception
{
    /**
     * 错误码
     * @var string
     */
    protected $errorCode;
    
    /**
     * 额外数据
     * @var array
     */
    protected $data = [];
    
    /**
     * 构造函数
     * 
     * @param string|array $message 错误信息
     * @param array $data 额外数据
     * @param int $code 错误码
     * @param Exception|null $previous
     */
    public function __construct($message = '', $data = [], $code = 0, ?Exception $previous = null)
    {
        if (is_array($message)) {
            $this->errorCode = $message['code'] ?? 'tenant_error';
            $message = $message['msg'] ?? $this->errorCode;
            $data = $message['data'] ?? $data;
        }
        
        parent::__construct($message, $code, $previous);
        
        $this->errorCode = is_string($code) ? $code : ($this->errorCode ?? 'tenant_error');
        $this->data = is_array($data) ? $data : [];
    }
    
    /**
     * 获取错误码
     * 
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode ?? 'tenant_error';
    }
    
    /**
     * 获取额外数据
     * 
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
    
    /**
     * 转换为数组
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode ?? 'tenant_error',
            'message' => $this->getMessage(),
            'data' => $this->data,
        ];
    }
}
