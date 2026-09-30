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
namespace app\middleware\global;

use app\service\admin\ops\gateway\RetaLimiterService;
use app\service\admin\ops\gateway\RetaRestrictionsService;
use core\infrastructure\cache\CacheService;
use support\Container;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use Webman\RateLimiter\Limiter;
use Webman\RateLimiter\RateLimitException;

/**
 * 限访限流中间件
 *
 * 功能特性：
 * 1. IP-based 黑名单访问限制
 * 2. 基于路径的限流控制
 * 3. 支持多种匹配模式：精确匹配、通配符匹配、正则表达式匹配
 * 4. 支持多种限流类型：IP限流、用户限流
 * 5. 配置化限流规则（作为数据库规则的 fallback）
 * 6. 缓存优化（1小时缓存过期）
 *
 * 配置示例（config/rate_limiter.php）：
 * [
 *     [
 *         'path' => '/api/*',
 *         'methods' => '*',
 *         'match_type' => 'wildcard',
 *         'limit_type' => 'ip',
 *         'limit_value' => 100,
 *         'ttl' => 60,
 *         'message' => '请求过于频繁，请稍后再试'
 *     ]
 * ]
 *
 * @author Mr.April
 * @since  1.0
 */
class RateLimiterMiddleware implements MiddlewareInterface
{

    /**
     * process
     *
     * @param \Webman\Http\Request $request
     * @param callable             $handler
     *
     * @return \Webman\Http\Response
     */
    public function process(Request $request, callable $handler): Response
    {
        $path     = $request->path();
        //跳过对应的安装接口
        if (in_array($path, ['/'])) {
            return $handler($request);
        }
        $ip     = $request->getRealIp();
        $method = $request->method();
        $userId = $this->getUserId($request);

        //限访处理
        $rateRestrictions = $this->getRateRestrictions($ip, $path, $method);
        if (!empty($rateRestrictions)) {
            throw new RateLimitException($rateRestrictions['message'] ?? '拒绝访问');
        }

        $rateRule = $this->getRateLimit($path, $method);
        //限流处理
        if (!empty($rateRule)) {
            $key = $this->generateLimitKey($rateRule, $ip, $userId, $method, $path);
            Limiter::check(md5($key), $rateRule['limit_value'], $rateRule['ttl'] ?? 1, $rateRule['message']);
            //可以实现更多自定义的限流规则
        }

        return $handler($request);
    }

    /**
     * 获取用户ID
     *
     * @param Request $request
     *
     * @return int|null
     */
    protected function getUserId(Request $request): ?int
    {
        return null;
    }

    /**
     * 生成限流键
     *
     * @param array  $rule
     * @param string $ip
     * @param int|null $userId
     * @param string $method
     * @param string $path
     *
     * @return string
     */
    protected function generateLimitKey(array $rule, string $ip, ?int $userId, string $method, string $path): string
    {
        $limitType = $rule['limit_type'] ?? 'ip';
        switch ($limitType) {
            case 'user':
                if ($userId) {
                    return $userId . $method . $path;
                }
                // 如果没有用户ID，回退到IP
                return $ip . $method . $path;
            case 'ip':
            default:
                return $ip . $method . $path;
        }
    }

    /**
     * 获取匹配的限流规则（缓存优先）
     *
     * @param string $path
     * @param string $methods
     *
     * @return array|null
     */
    protected function getRateLimit(string $path, string $methods = 'GET'): ?array
    {
        $cache = Container::make(CacheService::class);
        $service = Container::make(RetaLimiterService::class);
        $rules = $cache->remember(RetaLimiterService::CACHE_KEY, function () use ($service) {
            return $service->selectList(['enabled' => 1], '*', 0, 0, 'priority desc')->toArray();
        });
        if (empty($rules)) {
            return $this->getConfigBasedLimit($path, $methods);
        }
        foreach ($rules as $rule) {
            if ($this->matchPath($rule['path'], $path, $rule['match_type'] ?? 'exact') && 
                ($rule['methods'] === '*' || $rule['methods'] === $methods)) {
                return $rule;
            }
        }
        return $this->getConfigBasedLimit($path, $methods);
    }

    /**
     * 获取配置-based限流规则
     *
     * @param string $path
     * @param string $methods
     *
     * @return array|null
     */
    protected function getConfigBasedLimit(string $path, string $methods = 'GET'): ?array
    {
        $config = config('rate_limiter', []);
        if (empty($config)) {
            return null;
        }
        
        foreach ($config as $rule) {
            if (isset($rule['path'], $rule['limit_value']) && 
                $this->matchPath($rule['path'], $path, $rule['match_type'] ?? 'exact') && 
                (($rule['methods'] ?? '*') === '*' || ($rule['methods'] ?? 'GET') === $methods)) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * 获取限访规则
     *
     * @param string $ip
     * @param string $path
     * @param string $methods
     *
     * @return array|null
     */
    protected function getRateRestrictions(string $ip, string $path, string $methods = 'GET'): ?array
    {
        $currentTime = time();
        $cache       = Container::make(CacheService::class);
        $service     = Container::make(RetaRestrictionsService::class);
        $rules       = $cache->remember(RetaRestrictionsService::CACHE_KEY,function () use ($service) {
            return $service->selectList(['enabled' => 1], '*', 0, 0, 'priority desc')->toArray();
        });

        if (empty($rules)) {
            return null;
        }

        foreach ($rules as $rule) {
            // IP匹配
            if (!empty($rule['ip']) && $rule['ip'] !== $ip) {
                continue;
            }
            
            // 路径匹配
            if (!$this->matchPath($rule['path'], $path, $rule['match_type'] ?? 'exact')) {
                continue;
            }
            
            // 方法匹配
            if ($rule['methods'] !== '*' && $rule['methods'] !== $methods) {
                continue;
            }
            
            $hasStartTime = isset($rule['start_time']) && $rule['start_time'] > 0;
            $hasEndTime   = isset($rule['end_time']) && $rule['end_time'] > 0;

            // 规则未生效（未来时间）或已过期
            if (($hasStartTime && $currentTime < $rule['start_time']) ||
                ($hasEndTime && $currentTime > $rule['end_time'])) {
                continue;
            }

            return $rule;
        }
        return null;
    }

    /**
     * 路径匹配
     *
     * @param string $rulePath
     * @param string $requestPath
     * @param string $matchType
     *
     * @return bool
     */
    protected function matchPath(string $rulePath, string $requestPath, string $matchType = 'exact'): bool
    {
        switch ($matchType) {
            case 'exact':
                return $rulePath === $requestPath;
            case 'wildcard':
                $pattern = str_replace('*', '.*', preg_quote($rulePath, '/'));
                return preg_match('/^' . $pattern . '$/', $requestPath) === 1;
            case 'regex':
                return preg_match('/^' . $rulePath . '$/', $requestPath) === 1;
            default:
                return $rulePath === $requestPath;
        }
    }

}
