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

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * @author Mr.April
 * @since  1.0
 */
class Lang implements MiddlewareInterface
{
    private const LANG_MAP = [
        'en'    => ['en', 'en_US', 'en_AU', 'en_CA', 'en_GB', 'en-US', 'en-AU', 'en-CA', 'en-GB'],
        'zh_CN' => ['zh_CN', 'zh_HK', 'zh_TW', 'zh_SG', 'zh-CN', 'zh-HK', 'zh-TW', 'zh-SG'],
    ];

    /**
     * @throws \Exception
     */
    public function process(Request $request, callable $handler): Response
    {
        $lang       = $request->header('Accept-Language', config('app.lang'));
        $langPrefix = substr($lang, 0, 2);
        if (array_key_exists($langPrefix, self::LANG_MAP)) {
            $locale = self::LANG_MAP[$langPrefix][0]; // 默认选择第一个匹配的语言
        } else {
            $locale = config('app.lang');
        }

        session('lang', $locale);
        locale($locale);
        return $handler($request);
    }

}
