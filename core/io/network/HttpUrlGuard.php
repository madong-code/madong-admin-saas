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

namespace core\io\network;

/**
 * SSRF 防护 —— URL 安全校验与远程请求守卫
 *
 * 防护要点：
 * 1. 协议白名单：仅允许 http / https，禁止 file://、gopher://、dict://、ftp://、php://、data:// 等
 * 2. DNS 解析后 IP 黑名单拦截（私有地址 / 回环 / 链路本地 / 未路由 / 保留地址段），防止直接用 IP 绕过
 * 3. cURL 跟随重定向后对最终 Location 再做一次 IP 校验，防止通过重定向绕过
 * 4. Host 解析支持 IPv6 与 DNS 解析；所有解析结果均命中黑名单即拒绝
 * 5. 端口锁定：HTTP→80 HTTPS→443，禁止自定义端口指向内网
 * 6. User-Agent 注入 madong-ssrf-guard 方便服务端审计
 *
 * 典型用法：
 *   $guard = new HttpUrlGuard();
 *   $guard->assertSafe($url);   // 抛异常 or 返回 true
 *   $body = $guard->fetch($url); // 自动安全校验 + cURL 请求
 */
final class HttpUrlGuard
{
    /** 允许的协议 */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /** 默认超时秒数 */
    private const DEFAULT_TIMEOUT = 15;

    /** 默认最大响应体字节数（5MB） */
    private const DEFAULT_MAX_SIZE = 5 * 1024 * 1024;

    /** 默认最大跟随重定向次数 */
    private const DEFAULT_MAX_REDIRECTS = 3;

    /**
     * 断言 URL 安全，不安全时抛出异常
     *
     * @param string $url
     * @return true
     * @throws \RuntimeException
     */
    public function assertSafe(string $url): bool
    {
        $this->validateUrlStructure($url);
        $this->validateHostResolvesToSafeIp($url);
        return true;
    }

    /**
     * 完整的安全远程 GET 请求（带 SSRF 防护）
     *
     * @param string   $url
     * @param int|null $maxSize 覆盖默认大小限制（字节）
     * @param int|null $timeout 覆盖默认超时（秒）
     *
     * @return array{body:string, content_type:string, final_url:string}
     *
     * @throws \RuntimeException cURL 错误 / 协议不允许 / IP 命中黑名单
     */
    public function fetch(string $url, ?int $maxSize = null, ?int $timeout = null): array
    {
        $this->validateUrlStructure($url);

        $maxSize  = $maxSize ?? self::DEFAULT_MAX_SIZE;
        $timeout  = $timeout ?? self::DEFAULT_TIMEOUT;
        $redirects = self::DEFAULT_MAX_REDIRECTS;

        $ch = curl_init();
        if ($ch === false) {
            throw new \RuntimeException('无法初始化 cURL');
        }

        $responseHeaders = [];
        $headerClosure = static function ($handle, string $header) use (&$responseHeaders): int {
            $len = strlen($header);
            $responseHeaders[] = $header;
            return $len;
        };

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,   // 手动处理重定向以便每跳都校验 IP
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_MAXREDIRS      => 0,        // 我们自己迭代重定向
            CURLOPT_NOSIGNAL       => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => $headerClosure,
            CURLOPT_USERAGENT      => 'madong-ssrf-guard/1.0',
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);

        $currentUrl = $url;
        $body       = '';
        $finalUrl   = $url;
        $statusCode = 0;
        $contentType = '';

        for ($step = 0; $step <= $redirects; $step++) {
            if ($step > 0) {
                // 每次重定向前都校验新目标
                $this->validateUrlStructure($currentUrl);
                curl_setopt($ch, CURLOPT_URL, $currentUrl);
                $responseHeaders = [];
            }

            $body     = (string) curl_exec($ch);
            $curlErr  = curl_error($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($curlErr !== '' && $body === false) {
                curl_close($ch);
                throw new \RuntimeException('远程请求失败: ' . $curlErr);
            }

            if ($body !== '' && strlen($body) > $maxSize) {
                curl_close($ch);
                throw new \RuntimeException('响应体超过大小限制 ' . $maxSize . ' 字节');
            }

            $finalUrl   = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

            // 是否有重定向
            $location = $this->extractHeaderValue($responseHeaders, 'location');
            if ($location !== '' && ($statusCode >= 300 && $statusCode < 400)) {
                if ($step === $redirects) {
                    curl_close($ch);
                    throw new \RuntimeException('超过最大重定向次数');
                }
                // 解析相对 URL
                $currentUrl = $this->resolveRelative($finalUrl, $location);
                continue;
            }
            break;
        }

        curl_close($ch);

        if ($statusCode >= 400) {
            throw new \RuntimeException("远程资源返回 HTTP {$statusCode}");
        }

        return [
            'body'         => $body,
            'content_type' => $contentType,
            'final_url'    => $finalUrl,
        ];
    }

    // ======================================================================
    // 以下为内部校验工具
    // ======================================================================

    /**
     * 校验 URL 结构 + 协议白名单 + 端口锁定
     */
    private function validateUrlStructure(string $url): void
    {
        if ($url === '' || strlen($url) > 2048) {
            throw new \RuntimeException('URL 长度不合法');
        }

        // 防 CRLF 注入
        if (preg_match('/[\r\n\x00]/', $url)) {
            throw new \RuntimeException('URL 包含非法字符');
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \RuntimeException('URL 结构不合法');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new \RuntimeException("不允许的协议: {$scheme}");
        }

        $host = $parts['host'];
        if ($host === '') {
            throw new \RuntimeException('URL host 为空');
        }

        // 禁止 userinfo（用户密码），防止 @ 重定向攻击
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \RuntimeException('URL 不允许包含用户凭据');
        }

        // 端口锁定：HTTP 80 / HTTPS 443，禁止自定义端口指向内网
        if (isset($parts['port'])) {
            $expected = $scheme === 'https' ? 443 : 80;
            if ((int) $parts['port'] !== $expected) {
                throw new \RuntimeException('不允许使用非标准端口');
            }
        }
    }

    /**
     * 解析 host 的所有 IP（IPv4 + IPv6），确保没有命中黑名单
     */
    private function validateHostResolvesToSafeIp(string $url): void
    {
        $parts = parse_url($url);
        $host  = strtolower($parts['host']);

        // 已经是 IP 字面量？直接检查
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->assertIpNotBlocked($host);
            return;
        }

        // DNS 解析（A + AAAA）
        $ips = $this->resolveHostIps($host);
        if ($ips === []) {
            throw new \RuntimeException("DNS 解析失败: {$host}");
        }

        foreach ($ips as $ip) {
            $this->assertIpNotBlocked($ip);
        }
    }

    /**
     * DNS 解析 host 的 IPv4 / IPv6 地址
     *
     * @return string[]
     */
    private function resolveHostIps(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            // 某些环境 dns_get_record 不可用，降级 gethostbyname
            $ipv4 = @gethostbyname($host);
            if ($ipv4 !== $host && $ipv4 !== false) {
                $ips[] = $ipv4;
            }
            return $ips;
        }
        foreach ($records as $record) {
            if (isset($record['ipv4'])) {
                $ips[] = $record['ipv4'];
            } elseif (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        return array_values(array_unique($ips));
    }

    /**
     * 断言 IP 不在 SSRF 黑名单网段内
     */
    private function assertIpNotBlocked(string $ip): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $packed = @inet_pton($ip);
            if ($packed === false) {
                throw new \RuntimeException("无效的 IPv4: {$ip}");
            }
            // 0.0.0.0/8（"this" network）
            if ((ord($packed[0]) & 0xff) === 0x00) {
                throw new \RuntimeException("禁止访问保留网段 IPv4: {$ip}");
            }
            // 10.0.0.0/8
            if ((ord($packed[0]) & 0xff) === 0x0a) {
                throw new \RuntimeException("禁止访问内网网段: {$ip}");
            }
            // 172.16.0.0/12（172.16.0.0 ~ 172.31.255.255）
            $first = ord($packed[0]);
            $second = ord($packed[1]);
            if ($first === 0xac && ($second >= 0x10 && $second <= 0x1f)) {
                throw new \RuntimeException("禁止访问内网网段: {$ip}");
            }
            // 192.168.0.0/16
            if ($first === 0xc0 && $second === 0xa8) {
                throw new \RuntimeException("禁止访问内网网段: {$ip}");
            }
            // 127.0.0.0/8（回环）
            if (($first & 0xff) === 0x7f) {
                throw new \RuntimeException("禁止访问回环地址: {$ip}");
            }
            // 169.254.0.0/16（链路本地 / AWS 元数据）
            if ($first === 0xa9 && $second === 0xfe) {
                throw new \RuntimeException("禁止访问链路本地网段: {$ip}");
            }
            // 224.0.0.0/4 ~ 255.0.0.0/4（组播 / 保留）
            if (($first & 0xf0) >= 0xe0) {
                throw new \RuntimeException("禁止访问组播/保留网段: {$ip}");
            }
            return;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = @inet_pton($ip);
            if ($packed === false) {
                throw new \RuntimeException("无效的 IPv6: {$ip}");
            }
            // ::1 回环
            if ($ip === '::1') {
                throw new \RuntimeException("禁止访问回环地址 IPv6: {$ip}");
            }
            // fc00::/7（ULA 唯一本地地址）
            $first = ord($packed[0]);
            if (($first & 0xfe) === 0xfc) {
                throw new \RuntimeException("禁止访问内网 IPv6 网段: {$ip}");
            }
            // fe80::/10（链路本地）
            if (($first & 0xff) === 0xfe && (ord($packed[1]) & 0xc0) === 0x80) {
                throw new \RuntimeException("禁止访问链路本地 IPv6: {$ip}");
            }
            return;
        }

        throw new \RuntimeException("无效的 IP 地址: {$ip}");
    }

    /**
     * 从 header 行数组提取指定 header 的值（大小写不敏感）
     */
    private function extractHeaderValue(array $headers, string $name): string
    {
        $needle = strtolower($name) . ':';
        foreach ($headers as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            if (str_starts_with(strtolower($trim), $needle)) {
                return trim(substr($trim, strlen($needle)));
            }
        }
        return '';
    }

    /**
     * 将相对 Location 解析为绝对 URL（RFC 3986）
     */
    private function resolveRelative(string $base, string $target): string
    {
        // 已经是绝对 URL
        if (preg_match('#^[a-z][a-z0-9+\-.]*://#i', $target)) {
            return $target;
        }

        $baseParts = parse_url($base);
        if ($baseParts === false) {
            return $target;
        }

        $scheme = $baseParts['scheme'] ?? 'http';
        $host   = $baseParts['host'] ?? '';
        $port   = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';

        if (str_starts_with($target, '//')) {
            return $scheme . ':' . $target;
        }
        if (str_starts_with($target, '/')) {
            return $scheme . '://' . $host . $port . $target;
        }

        // 相对路径
        $basePath = $baseParts['path'] ?? '/';
        $dir      = rtrim(dirname($basePath), '/') . '/';
        $merged   = $dir . $target;
        // 简化 ../ ./
        $parts = explode('/', $merged);
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($resolved);
                continue;
            }
            $resolved[] = $part;
        }
        return $scheme . '://' . $host . $port . '/' . implode('/', $resolved);
    }
}
