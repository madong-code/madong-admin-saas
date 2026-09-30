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
namespace core\foundation\base;

use core\foundation\exception\handler\ValidationException;
use support\validation\Validator;
use think\helper\Str;
use Webman\Http\Request;

abstract class BaseValidate
{
    /** @var bool 是否实例化时自动验证 */
    public bool $authValidate = false;

    /** @var array 验证规则 */
    protected array $rules = [];

    /** @var array 错误消息 */
    protected array $messages = [];

    /** @var array 验证场景（场景名 => 需要验证的字段） */
    protected array $scenes = [];

    /** @var array 当前场景需验证的字段 */
    protected array $only = [];

    /** @var array 验证通过的有效数据 */
    protected array $data = [];

    /** @var string|null 当前场景名 */
    protected string|null $currentScene;

    /** @var string|null 验证失败错误信息 */
    protected ?string $error = null;

    /** @var bool 是否抛出验证异常（默认是） */
    protected bool $failException = true;

    /** @var int 业务错误码 */
    protected int $code = -1;

    /** @var int HTTP响应状态码 */
    protected int $statusCode = 200;

    /**
     * 构造函数：初始化场景与自动验证
     *
     * @param string|null $scene 验证场景名
     *
     * @throws ValidationException
     */
    public function __construct(?string $scene = null)
    {
        // 设置场景
        if ($scene) {
            $this->scene($scene);
        }

        // 自动验证（若开启）
        if ($this->authValidate) {
            $this->check();
        }
    }

    /**
     * 魔术方法：代理Webman请求对象的方法（如input、method）
     *
     * @param string $name      方法名
     * @param array  $arguments 参数
     *
     * @return mixed
     * @throws ValidationException
     */
    public function __call(string $name, array $arguments)
    {
        $request = $this->request();
        if (method_exists($request, $name)) {
            return $request->{$name}(...$arguments);
        }
        throw new ValidationException(__('方法名不能为空', ['class' => get_class($request), 'method' => $name]));
    }

    /**
     * 合并自定义错误消息
     *
     * @param array $message 自定义消息
     *
     * @return $this
     */
    public function setMessage(array $message): self
    {
        $this->messages = array_merge($this->messages, $message);
        return $this;
    }

    /**
     * 核心验证方法：执行规则校验（保持原功能，优化内部逻辑）
     *
     * @param array $data  待验证数据（为空则从请求取）
     * @param array $rules 自定义规则（为空则用类内规则）
     *
     * @return bool 验证通过返回true
     * @throws ValidationException
     * @throws \Exception
     */
    public function check(array $data = [], array $rules = []): bool
    {
        // 1. 初始化场景（若有）
        if ($this->currentScene) {
            $this->loadSceneRules($this->currentScene);
        }
        // 2. 获取最终验证规则（支持方法和属性两种方式，属性优先）
        if (empty($rules)) {
            // 优先使用属性定义（如果属性不为空）
            if (!empty($this->rules)) {
                $finalRules = $this->rules;
            } 
            // 其次使用方法定义（如果方法返回非空数组）
            elseif (method_exists($this, 'rules')) {
                $methodRules = $this->rules();
                if (!empty($methodRules)) {
                    $finalRules = $methodRules;
                } else {
                    $finalRules = [];
                }
            } else {
                $finalRules = [];
            }
        } else {
            $finalRules = $rules;
        }

        // 3. 获取错误消息（优先类内messages方法）
        if (empty($this->messages) && method_exists($this, 'messages')) {
            $this->messages = $this->messages();
        }

        // 4. 处理场景字段过滤（only）
        if (!empty($this->only)) {
            $sceneRules = [];
            $method     = strtolower($this->request()->method());
            $method     = $method === 'post' ? 'post' : 'get';
            foreach ($this->only as $field) {
                if (isset($finalRules[$field])) {
                    $sceneRules[$field] = $finalRules[$field];
                    // 若数据为空，从请求中获取对应字段值
                    if (empty($data)) {
                        $data[$field] = $this->request()->{$method}($field);
                    }
                }
            }
            $finalRules = $sceneRules;
        } else {
            // 若未指定only，从请求中获取全部数据（若数据为空）
            if (empty($data)) {
                $data = $this->request()->all();
            }
        }

        // 5. 构建验证器
        $validator = Validator::make($data, $finalRules, $this->messages);

        // 5.1 注册子类自定义验证规则（方法名与规则名匹配）
        foreach ($finalRules as $field => $ruleStr) {
            $rules = is_string($ruleStr) ? explode('|', $ruleStr) : (is_array($ruleStr) ? $ruleStr : [$ruleStr]);
            foreach ($rules as $rule) {
                $ruleName = is_string($rule) ? explode(':', $rule)[0] : '';
                if ($ruleName && method_exists($this, $ruleName)) {
                    $validator->addExtension($ruleName, function ($attribute, $value, $parameters, $validator) use ($ruleName) {
                        return $this->{$ruleName}($value, $parameters, $validator->getData());
                    });
                }
            }
        }

        // 6. 处理验证结果
        $this->currentScene = null;

        if ($validator->fails()) {
            return $this->handleValidationFailure($validator);
        }

        // 验证通过：保存有效数据
        $this->data = $validator->validated();
        return true;
    }

    /**
     * 加载场景规则
     *
     * @param string $scene 场景名
     */
    protected function loadSceneRules(string $scene): void
    {
        $this->only  = [];
        $sceneAction = Str::studly($scene);

        // 优先调用sceneXxx方法（如sceneAdd）
        if (method_exists($this, $sceneAction)) {
            call_user_func([$this, $sceneAction]);
        } // 其次读取scene数组配置
        elseif (isset($this->scenes[$scene])) {
            $this->only = $this->scenes[$scene];
        }
    }

    /**
     * 处理验证失败：修正异常抛出逻辑
     *
     * @param Validator $validator 验证器实例
     *
     * @return bool
     * @throws \Exception
     */
    protected function handleValidationFailure(Validator $validator): bool
    {
        $errorMsg = $validator->errors()->first();
        if ($this->failException) {
            // 抛出正确的ValidationException（传递状态码）
            throw new ValidationException($errorMsg, [
                    'statusCode' => $this->statusCode,
                    'errorCode'  => $this->code,
                    'headers'    => [],
                ]
            );
        }
        // 不抛异常：保存错误信息
        $this->error = $errorMsg;
        return false;
    }

    /**
     * 执行验证并返回验证通过的数据
     *
     * @param array $data          待验证数据（为空则从请求取）
     * @param array $rules         自定义规则（为空则用类内规则）
     * @param array $message       自定义错误消息（为空则用类内消息）
     * @param bool  $returnAllData 是否返回全部数据（包括未验证字段）
     *
     * @return array 验证通过的数据
     * @throws \Exception
     */
    public function validate(array $data = [], array $rules = [], array $message = [], bool $returnAllData = false): array
    {
        // 保存原始消息以便验证后恢复
        $originalMessages = $this->messages;
        try {
            if (!empty($message)) {
                $this->setMessage($message);
            }
            // 执行验证
            $this->check($data, $rules);

            // 根据参数决定返回验证数据还是全部数据
            if ($returnAllData && !empty($data)) {
                return array_merge($data, $this->data);
            }
            return $this->data;
        } finally {
            $this->messages = $originalMessages;
        }
    }

    /**
     * 获取验证通过的有效数据
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 获取验证失败错误信息
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * 设置当前验证场景
     *
     * @param string $scene 场景名
     *
     * @return $this
     */
    public function scene(string $scene): self
    {
        $this->currentScene = $scene;
        return $this;
    }

    /**
     * 设置是否抛出验证异常
     *
     * @param bool $flag 是否抛出
     *
     * @return $this
     */
    public function failException(bool $flag): self
    {
        $this->failException = $flag;
        return $this;
    }

    /**
     * 获取Webman请求对象
     *
     * @return Request
     */
    protected function request(): Request
    {
        return request();
    }

    /**
     * 定义验证规则（子类重写）
     *
     * @return array
     */
    protected function rules(): array
    {
        return [];
    }
}