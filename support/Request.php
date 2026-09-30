<?php
/**
 * This file is part of webman.
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace support;

use app\service\core\ValidationService;
use core\base\BaseValidate;
use Spatie\Macroable\Macroable;

/**
 * Class Request
 *
 * @property mixed $tenantAcme
 * @package support
 */
class Request extends \Webman\Http\Request
{

    use Macroable;

    public string|null|int $tenantAcme;

    /**
     * 不过滤变量名
     *
     * @var array
     */
    protected array $except = ['menu_path', 'api_url', 'unique_auth', 'description', 'custom_form', 'content'];

    /**
     * 获取请求的数据
     *
     * @param array $params
     * @param bool  $suffix
     * @param bool  $filter
     *
     * @return array
     */
    public function more(array $params, bool $suffix = false, bool $filter = true): array
    {
        $p = [];
        $i = 0;
        foreach ($params as $param) {
            if (!is_array($param)) {
                $p[$suffix ? $i++ : $param] = $this->filterWord(is_string($this->input($param)) ? trim($this->input($param)) : $this->input($param), $filter && !in_array($param, $this->except));
            } else {
                if (!isset($param[1])) $param[1] = null;
                if (!isset($param[2])) $param[2] = '';
                if (is_array($param[0])) {
                    $name    = is_array($param[1]) ? $param[0][0] . '/a' : $param[0][0] . '/' . $param[0][1];
                    $keyName = $param[0][0];
                } else {
                    $name    = is_array($param[1]) ? $param[0] . '/a' : $param[0];
                    $keyName = $param[0];
                }
                $p[$suffix ? $i++ : ($param[3] ?? $keyName)] = $this->filterWord(is_string($this->input($name, $param[1], $param[2])) ? trim($this->input($name, $param[1], $param[2])) : $this->input($name, $param[1], $param[2]), $filter && !in_array($keyName, $this->except));
            }
        }
        return $p;
    }

    /**
     * 过滤接受的参数
     *
     * @param      $str
     * @param bool $filter
     *
     * @return array|mixed|string|string[]
     */
    public function filterWord($str, bool $filter = true): mixed
    {
        if (!$str || !$filter) return $str;
        // 把数据过滤
        $farr = [
            "/<(\\/?)(script|i?frame|style|html|body|title|link|meta|object|\\?|\\%)([^>]*?)>/isU",
            "/(<[^>]*)on[a-zA-Z]+\s*=([^>]*>)/isU",
            '/phar/is',
            "/select|join|where|drop|like|modify|rename|insert|update|table|database|alter|truncate|\'|\/\*|\.\.\/|\.\/|union|into|load_file|outfile/is",
        ];
        if (is_array($str)) {
            foreach ($str as &$v) {
                if (is_array($v)) {
                    foreach ($v as &$vv) {
                        if (!is_array($vv)) $vv = preg_replace($farr, '', $vv);
                    }
                } else {
                    $v = preg_replace($farr, '', $v);
                }
            }
        } else {
            $str = preg_replace($farr, '', $str);
        }
        return $str;
    }

    /**
     * 获取get参数
     *
     * @param array $params
     * @param bool  $suffix
     * @param bool  $filter
     *
     * @return array
     */
    public function getMore(array $params, bool $suffix = false, bool $filter = true): array
    {
        return $this->more($params, $suffix, $filter);
    }

    /**
     * 获取post参数
     *
     * @param array $params
     * @param bool  $suffix
     * @param bool  $filter
     *
     * @return array
     */
    public function postMore(array $params, bool $suffix = false, bool $filter = true): array
    {
        return $this->more($params, $suffix, $filter);
    }

    /**
     * 获取请求的数据
     * 获取额外参数和值；
     * 　　type为 1、2 表示 索引数组（1为 Value 的一维数组，2为key、Value的二维数组），0表示 关联数组
     *     每条数据组成：
     *       数组/字符串
     *     如果是数组：
     *       $param[0]：要获取的Key
     *         可以为数组：0为key，1为变量修饰符
     *         $param[1]：默认值；!!!注意，如果默认值为数组，则这个项的值也会转为数组（索引）!!!
     *         $param[2]：过滤函数
     *         $param[3]：替换的新key
     *     已修改为：如果没有 $param 且没有缺省值，则跳过这个；
     *     感觉还可以修改 变量修饰符 那部分（去掉 增加变量修饰）
     *     返回：
     *       索引：二维数组：[[key, 值], [key, 值]。。。]，适用于where
     *        一维数组[值, 值, 。。。]
     *       关联：一维关联数组
     *
     * @param array $params
     * @param int   $type
     *
     * @return array
     */
    public function getParams(array $params, int $type = 0): array
    {
        $p = [];
        $i = 0;
        foreach ($params as $param) {
            if (!is_array($param))    //如果不是数组
            {
                //原来的索引数组没有key
                //$p[$suffix == true ? $i++ : $param] = $this->input($param);
                if ($type == 0)
                    $p[$param] = $this->input($param);
                else if ($type == 1)
                    $p[$i++] = $this->input($param);
                else {
                    //修改为 [key, 值]
                    $p[$i++] = [$param, $this->input($param)];
                }
            } else {
                //if (!isset($param[1])) $param[1] = null;
                if (!isset($param[2])) $param[2] = '';
                if (is_array($param[0]))    //如果是数组
                {
                    if (!array_key_exists('1', $param))    //如果没有默认值
                    {
                        if (!$this->has($param[0][0]))    //如果request中没有
                            continue;

                        $param[1] = null;
                    }

                    //如果默认值为数组，则给param加上变量修饰符（/a）；否则手动连接修饰符
                    $name    = is_array($param[1]) ? $param[0][0] . '/a' : $param[0][0] . '/' . $param[0][1];
                    $keyName = $param[0][0];
                } else {
                    if (!array_key_exists('1', $param))    //如果没有默认值
                    {
                        if (!$this->has($param[0]))    //如果request中没有
                            continue;

                        $param[1] = null;
                    }

                    //如果默认值为数组，则给param加上变量修饰符（/a）
                    $name    = is_array($param[1]) ? $param[0] . '/a' : $param[0];
                    $keyName = $param[0];
                }

                //原来的索引数组没有key
                //$p[$suffix == true ? $i++ : (isset($param[3]) ? $param[3] : $keyName)] = $this->input($name, $param[1], $param[2]);
                if ($type == 0)
                    $p[($param[3] ?? $keyName)] = $this->input($name, $param[1], $param[2]);
                else if ($type == 1)
                    $p[$i++] = $this->input($name, $param[1], $param[2]);
                else {
                    //修改为 [key, 值]
                    $p[$i++] = [$param[3] ?? $keyName, $this->input($name, $param[1], $param[2])];
                }
            }
        }
        return $p;
    }

    /**
     * 获取用户访问端
     *
     * @return array|string|null
     */
    public function getFromType(): array|string|null
    {
        return $this->header('Form-type', '');
    }

    /**
     * 当前访问端
     *
     * @param string $terminal
     *
     * @return bool
     */
    public function isTerminal(string $terminal): bool
    {
        return strtolower($this->getFromType()) === $terminal;
    }

    /**
     * 是否是H5端
     *
     * @return bool
     */
    public function isH5(): bool
    {
        return $this->isTerminal('h5');
    }

    /**
     * 是否是微信端
     *
     * @return bool
     */
    public function isWechat(): bool
    {
        return $this->isTerminal('wechat');
    }

    /**
     * 是否是小程序端
     *
     * @return bool
     */
    public function isRoutine(): bool
    {
        return $this->isTerminal('routine');
    }

    /**
     * 是否是app端
     *
     * @return bool
     */
    public function isApp(): bool
    {
        return $this->isTerminal('app');
    }

    /**
     * 是否是app端
     *
     * @return bool
     */
    public function isPc(): bool
    {
        return $this->isTerminal('pc');
    }

    /**
     * host 支持 x-forwarded
     *
     * @inheritDoc
     */
    public function host(bool $withoutPort = false): string
    {
        if ($host = $this->header('x-forwarded-host')) {
            if (str_contains($host, ',')) {
                $host = explode(',', $host)[0];
            }
            if ($withoutPort) {
                return $host;
            }
            if ($port = $this->header('x-forwarded-port')) {
                if (str_contains($port, ',')) {
                    $port = explode(',', $port)[0];
                }
                if (in_array($port, ['80', '443'])) {
                    return $host;
                }
                return $host . ':' . $port;
            }
            return $host;
        }
        return parent::host($withoutPort);
    }

    /**
     * path 前缀
     *
     * @return string
     */
    public function pathPrefix(): string
    {
        return $this->header('x-forwarded-prefix', '');
    }

    /**
     * 获取传输协议
     *
     * @return string http/https
     */
    public function getProto(): string
    {
        $proto = $this->header('x-forwarded-proto', 'http');
        if (str_contains($proto, ',')) {
            $proto = explode(',', $proto)[0];
        }
        return $proto;
    }

    /**
     * @inheritDoc
     */
    public function url(): string
    {
        return $this->getProto() . '://' . $this->host() . $this->pathPrefix() . $this->path();
    }

    /**
     * @inheritDoc
     */
    public function fullUrl(): string
    {
        return $this->getProto() . '://' . $this->host() . $this->pathPrefix() . $this->uri();
    }

    /**
     * 验证数据
     *
     * @param string|\core\base\BaseValidate $validatorClass
     * @param string|null                    $scene
     * @param array                          $extend
     *
     * @return array
     */
     public function validated(string|BaseValidate $validatorClass, ?string $scene = null, array $extend = []): array
     {
         $data = $this->all();
         return Container::make(ValidationService::class)->validate($validatorClass, $data, $scene, $extend);
     }




}
