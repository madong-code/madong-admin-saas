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
 * 订阅异常
 * 
 * 用于处理订阅相关的业务异常
 * 
 * 文档位置: docs/saas/07-功能订阅.md
 */
class SubscriptionException extends TenantException
{
    /**
     * 订阅错误码定义
     */
    const SUBSCRIPTION_EXPIRED = 'subscription_expired';
    const SUBSCRIPTION_SUSPENDED = 'subscription_suspended';
    const FEATURE_NOT_SUBSCRIBED = 'feature_not_subscribed';
    const FIELD_NOT_ALLOWED = 'field_not_allowed';
    const PLAN_NOT_FOUND = 'plan_not_found';
    const INVALID_PLAN = 'invalid_plan';
    
    /**
     * 错误码对应的中文消息
     * @var array
     */
    protected static $errorMessages = [
        'subscription_expired' => '订阅已过期',
        'subscription_suspended' => '订阅已暂停',
        'feature_not_subscribed' => '未订阅该功能模块',
        'field_not_allowed' => '无权访问该字段',
        'plan_not_found' => '订阅套餐不存在',
        'invalid_plan' => '无效的订阅套餐',
    ];
    
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
        if (is_string($message) && isset(self::$errorMessages[$message])) {
            $message = [
                'code' => $message,
                'msg' => self::$errorMessages[$message],
                'data' => $data,
            ];
        }
        
        parent::__construct($message, $data, $code, $previous);
        
        $this->errorCode = is_string($code) ? $code : $this->errorCode;
    }
    
    /**
     * 创建订阅过期异常
     * 
     * @param string|null $expireTime
     * @return static
     */
    public static function expired(?string $expireTime = null): self
    {
        return new static([
            'code' => self::SUBSCRIPTION_EXPIRED,
            'msg' => '订阅已过期',
            'data' => ['expire_time' => $expireTime],
        ]);
    }
    
    /**
     * 创建订阅暂停异常
     * 
     * @param string|null $reason
     * @return static
     */
    public static function suspended(?string $reason = null): self
    {
        return new static([
            'code' => self::SUBSCRIPTION_SUSPENDED,
            'msg' => '订阅已暂停',
            'data' => ['reason' => $reason],
        ]);
    }
    
    /**
     * 创建功能未订阅异常
     * 
     * @param string $module
     * @return static
     */
    public static function featureNotSubscribed(string $module): self
    {
        return new static([
            'code' => self::FEATURE_NOT_SUBSCRIBED,
            'msg' => '未订阅该功能模块: ' . $module,
            'data' => ['module' => $module],
        ]);
    }
    
    /**
     * 创建字段无权访问异常
     * 
     * @param string $module
     * @param string $field
     * @return static
     */
    public static function fieldNotAllowed(string $module, string $field): self
    {
        return new static([
            'code' => self::FIELD_NOT_ALLOWED,
            'msg' => '无权访问该字段: ' . $field,
            'data' => [
                'module' => $module,
                'field' => $field,
            ],
        ]);
    }
    
    /**
     * 获取友好的错误消息
     * 
     * @return string
     */
    public function getFriendlyMessage(): string
    {
        $message = parent::getMessage();
        
        $data = $this->getData();
        
        // 根据错误码提供更友好的消息
        switch ($this->errorCode) {
            case self::SUBSCRIPTION_EXPIRED:
                return '您的订阅已过期，请续费后继续使用。';
                
            case self::SUBSCRIPTION_SUSPENDED:
                $reason = $data['reason'] ?? '未知原因';
                return '您的订阅已暂停，原因: ' . $reason . '。请联系客服了解详情。';
                
            case self::FEATURE_NOT_SUBSCRIBED:
                $module = $data['module'] ?? '';
                return '您尚未订阅该功能模块(' . $module . ')，请升级您的订阅套餐。';
                
            case self::FIELD_NOT_ALLOWED:
                $field = $data['field'] ?? '';
                return '您当前的订阅套餐不支持该功能(' . $field . ')。';
                
            default:
                return $message;
        }
    }
}
