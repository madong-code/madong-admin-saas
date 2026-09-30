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

class BaseException extends \Exception
{
    /**
     * HTTP Response Status Code.
     *
     * @var int
     */
    public int $statusCode = 400;

    /**
     * HTTP Response Header.
     *
     * @var array
     */
    public array $header = [
        'Access-Control-Allow-Origin'      => '*',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Headers'     => 'Authorization,Content-Type,If-Match,If-Modified-Since,If-None-Match,If-Unmodified-Since,X-Requested-With,Origin',
        'Access-Control-Allow-Methods'     => 'GET,POST,PUT,DELETE,OPTIONS',
    ];

    /**
     * Business Error code.
     *
     * @var int
     */
    public int $errorCode = 0;

    /**
     * Business Error message.
     *
     * @var string
     */
    public string $errorMessage = 'The requested resource is not available or not exists';

    /**
     * Business data.
     *
     * @var array
     */
    public mixed $data = [];

    /**
     * Detail Log Error message.
     *
     * @var string
     */
    public string $error = '';

    /**
     * BaseException constructor.
     *
     * @param string $errorMessage
     * @param array  $params
     * @param string $error
     */
    public function __construct(string $errorMessage = '', array $params = [], string $error = '')
    {
        $errorMessage       = __($errorMessage ?: $this->errorMessage);// 翻译错误信息
        $this->errorMessage = $errorMessage;
        $this->error        = $error;
        parent::__construct($errorMessage);
        if (!empty($params)) {
            $this->statusCode = $params['statusCode'] ?? $this->statusCode;
            $this->header     = array_merge($this->header, $params['header'] ?? []);
            $this->errorCode  = $params['errorCode'] ?? $this->errorCode;
            $this->data       = $params['data'] ?? $this->data;
        }
    }
}
