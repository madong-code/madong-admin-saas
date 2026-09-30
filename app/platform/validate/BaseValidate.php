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
namespace app\platform\validate;

use support\validation\Validator;
use support\validation\ValidationException;
use Webman\Http\Request;
use think\helper\Str;

/**
 * platformapi 基础验证器
 * 基于 webman/validation 封装
 * 支持场景验证、自定义消息、自动从请求获取数据
 */
abstract class BaseValidate
{
    /** @var array 验证规则 */
    protected array $rules = [];

    /** @var array 错误消息 */
    protected array $messages = [];

    /** @var array 验证场景（场景名 => 需要验证的字段） */
    protected array $scenes = [];

    /** @var array 当前场景需验证的字段 */
    protected array $only = [];

    /** @var array 验证通过的有效数据 */
    protected array $validatedData = [];

    /** @var string|null 当前场景名 */
    protected ?string $currentScene = null;

    /** @var string|null 验证失败错误信息 */
    protected ?string $error = null;

    /** @var bool 是否抛出验证异常 */
    protected bool $failException = true;

    /** @var int 业务错误码 */
    protected int $errorCode = 422;

    /**
     * 构造函数
     *
     * @param string|null $scene 验证场景名
     */
    public function __construct(?string $scene = null)
    {
        if ($scene) {
            $this->scene($scene);
        }
    }

    /**
     * 设置验证场景
     *
     * @param string $scene 场景名
     * @return $this
     */
    public function scene(string $scene): self
    {
        $this->currentScene = $scene;
        $this->loadSceneRules($scene);
        return $this;
    }

    /**
     * 加载场景规则
     *
     * @param string $scene 场景名
     */
    protected function loadSceneRules(string $scene): void
    {
        $this->only = [];
        $sceneMethod = Str::studly($scene);

        if (method_exists($this, "scene{$sceneMethod}")) {
            call_user_func([$this, "scene{$sceneMethod}"]);
        } elseif (isset($this->scenes[$scene])) {
            $this->only = $this->scenes[$scene];
        }
    }

    /**
     * 设置是否抛出异常
     *
     * @param bool $flag
     * @return $this
     */
    public function failException(bool $flag): self
    {
        $this->failException = $flag;
        return $this;
    }

    /**
     * 执行验证
     *
     * @param array $data 待验证数据（为空则从请求获取）
     * @return bool
     * @throws ValidationException
     */
    public function check(array $data = []): bool
    {
        $finalRules = $this->getFinalRules();
        $finalData = $this->getFinalData($data);

        $validator = Validator::make($finalData, $finalRules, $this->messages);

        if ($validator->fails()) {
            return $this->handleFailure($validator);
        }

        $this->validatedData = $validator->validated();
        return true;
    }

    /**
     * 执行验证并返回验证后的数据
     *
     * @param array $data 待验证数据
     * @return array
     * @throws ValidationException
     */
    public function validate(array $data = []): array
    {
        $this->check($data);
        return $this->validatedData;
    }

    /**
     * 获取最终验证规则
     *
     * @return array
     */
    protected function getFinalRules(): array
    {
        $finalRules = $this->rules;

        if (!empty($this->only)) {
            $sceneRules = [];
            foreach ($this->only as $field) {
                if (isset($finalRules[$field])) {
                    $sceneRules[$field] = $finalRules[$field];
                }
            }
            $finalRules = $sceneRules;
        }

        return $finalRules;
    }

    /**
     * 获取最终待验证数据
     *
     * @param array $data
     * @return array
     */
    protected function getFinalData(array $data = []): array
    {
        if (!empty($data)) {
            return $data;
        }

        $request = $this->getRequest();
        return $request->all();
    }

    /**
     * 处理验证失败
     *
     * @param Validator $validator
     * @return bool
     * @throws ValidationException
     */
    protected function handleFailure(Validator $validator): bool
    {
        $errorMsg = $validator->errors()->first();
        $this->error = $errorMsg;

        if ($this->failException) {
            throw new ValidationException($errorMsg, $this->errorCode);
        }

        return false;
    }

    /**
     * 获取验证错误信息
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * 获取验证通过的数据
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->validatedData;
    }

    /**
     * 获取请求对象
     *
     * @return Request
     */
    protected function getRequest(): Request
    {
        return request();
    }

    /**
     * 合并自定义消息
     *
     * @param array $message
     * @return $this
     */
    public function setMessage(array $message): self
    {
        $this->messages = array_merge($this->messages, $message);
        return $this;
    }
}
