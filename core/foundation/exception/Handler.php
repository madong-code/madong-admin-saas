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
namespace core\foundation\exception;

use Illuminate\Validation\ValidationException;
use core\security\jwt\ex\JwtRefreshTokenExpiredException;
use core\security\jwt\ex\JwtTokenExpiredException;
use core\security\jwt\ex\JwtTokenInBlacklistException;
use core\security\jwt\ex\JwtTokenInvalidException;
use FastRoute\BadRouteException;
use core\foundation\exception\handler\BaseException;
use core\foundation\exception\handler\ServerErrorHttpException;
use think\exception\ValidateException;
use Throwable;
use Webman\Exception\ExceptionHandler;
use Webman\Http\Request;
use Webman\Http\Response;

class Handler extends ExceptionHandler
{
    public $dontReport = [];
    protected int $statusCode = 200;
    protected array $header = [];
    protected int $errorCode = -1;
    protected string $errorMessage = 'no error';
    protected array $responseData = [];
    protected array $config = [];
    protected string $error = 'no error';

    public function report(Throwable $exception)
    {
        $this->dontReport = config('core.foundation.exception.handler.dont_report', []);
        parent::report($exception);
    }

    public function render(Request $request, Throwable $exception): Response
    {
        // Apidoc异常处理响应
//        if ($exception instanceof \hg\apidoc\exception\HttpException) {
//            return response(json_encode([
//                "code"    => $exception->getCode(),
//                "message" => $exception->getMessage(),
//            ], JSON_UNESCAPED_UNICODE), $exception->getStatusCode());
//        }

        // 处理Laravel验证异常
        if ($exception instanceof ValidationException) {
            $errors = $exception->errors();
            return json([
                'code'    => 422,
                'message' => 'Validation failed',
                'data'    => $errors,
            ], 422);
        }

        $this->initializeConfig();
        $this->addRequestInfoToResponse($request);
        $this->processException($exception);
        $this->addDebugInfoToResponse($exception);
        $this->triggerEvents($exception);

        return $this->buildResponse();
    }

    protected function initializeConfig(): void
    {
        $this->config = array_merge($this->config, config('core.foundation.exception.handler', []));
    }

    protected function addRequestInfoToResponse(Request $request): void
    {
        $this->responseData = array_merge($this->responseData, [
            'domain'        => $request->host(),
            'method'        => $request->method(),
            'request_url'   => $request->method() . ' ' . $request->uri(),
            'timestamp'     => date('Y-m-d H:i:s'),
            'client_ip'     => $request->getRealIp(),
            'request_param' => $request->all(),
        ]);
    }

    protected function processException(Throwable $e): void
    {
        if ($e instanceof BaseException) {
            $this->handleBaseException($e);
        } else {
            $this->handleOtherExceptions($e);
        }
    }

    protected function handleBaseException(BaseException $e): void
    {
        $this->statusCode   = $e->statusCode;
        $this->header       = $e->header;
        $this->errorCode    = $e->errorCode;
        $this->errorMessage = $e->errorMessage;
        $this->error        = $e->error;

        if (isset($e->data)) {
            $this->responseData = array_merge($this->responseData, $e->data);
        }
    }

    protected function handleOtherExceptions(Throwable $e): void
    {
        $status             = $this->config['status'];
        $this->errorMessage = $e->getMessage();
        switch (true) {
            case $e instanceof BadRouteException:
                $this->statusCode = $status['route'] ?? 404;
                break;
            case $e instanceof ValidateException:
                $this->statusCode = $status['validate'] ?? 400;
                break;
            case $e instanceof JwtTokenInBlacklistException:
            case $e instanceof JwtTokenInvalidException:
                $this->statusCode = $status['jwt_token'] ?? 401;
                break;
            case $e instanceof JwtTokenExpiredException:
                $this->statusCode = $status['jwt_token_expired'] ?? 401;
                break;
            case $e instanceof JwtRefreshTokenExpiredException:
                $this->statusCode = $status['jwt_refresh_token_expired'] ?? 401;
                break;
            case $e instanceof \InvalidArgumentException:
                $this->statusCode   = $status['invalid_argument'] ?? 415;
                $this->errorMessage = '预期参数配置异常：' . $e->getMessage();
                break;
            case $e instanceof ServerErrorHttpException:
                $this->statusCode = 500;
                $this->errorCode=-1;
                break;
            default:
                $this->statusCode   = $status['server_error'] ?? 500;
                $this->errorMessage = 'Internal Server Error';
                $this->error        = $e->getMessage();
                break;
        }
    }

    protected function addDebugInfoToResponse(Throwable $e): void
    {
        if (config('app.debug', false)) {
            $this->responseData['error_message'] = $e->getMessage();
            $this->responseData['error_trace']   = explode("\n", $e->getTraceAsString());
            $this->responseData['file']          = $e->getFile();
            $this->responseData['line']          = $e->getLine();
        }
    }

    protected function triggerEvents(Throwable $e): void
    {
        $this->triggerNotifyEvent($e);
        $this->triggerTraceEvent($e);
    }

    protected function triggerNotifyEvent(Throwable $e): void
    {

    }

    protected function triggerTraceEvent(Throwable $e): void
    {

    }

    protected function buildResponse(): Response
    {
        $bodyKey      = array_keys($this->config['body']);
        $bodyValue    = array_values($this->config['body']);
        
        // 当HTTP状态码为错误状态时，确保code不为0
        $code = $bodyValue[0] ?? 0;
        if ($this->statusCode >= 400 && $code === 0) {
            $code = -1;
        }
        
        $responseBody = [
                $bodyKey[0] ?? 'code' => $code,
                $bodyKey[1] ?? 'msg'  => $this->errorMessage,
                $bodyKey[2] ?? 'data' => $this->responseData,
        ];

        $header = array_merge(['Content-Type' => 'application/json;charset=utf-8'], $this->header);
        return new Response($this->statusCode, $header, json_encode($responseBody));
    }
}
