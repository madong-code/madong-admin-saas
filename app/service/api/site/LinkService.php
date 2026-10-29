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
 * Official Website: https://madong.tech
 */

namespace app\service\api\site;

use app\dao\site\LinkDao;
use core\foundation\base\BaseService;

/**
 * 友情链接服务
 */
class LinkService extends BaseService
{
    public function __construct(LinkDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据类型获取链接
     *
     * @param string $type 链接类型
     *
     * @return array|null
     * @throws \Exception
     */
    public function getLinksByType(string $type): ?array
    {
        $map = [
            ['category', 'eq', $type],
            ['enabled', 'eq', 1],
        ];
        return $this->dao->selectList($map, ['*'], 0, 0, 'sort asc')->toArray();
    }

    /**
     * 获取所有链接
     *
     * @return array
     * @throws \Exception
     */
    public function getAllLinks(): array
    {
        $map = [
            ['enabled', '=', 1],
        ];

        return $this->dao->selectList($map, ['*'], 0, 0, 'category asc, sort asc')->toArray();
    }

    /**
     * 页脚申请友链（落库为待审核 enabled=0）
     *
     * 多租户场景：web_link 走 TenantScope，落库自动归属当前租户；
     * 同主机去重也只在当前租户范围内生效，避免跨租户泄露他人友链。
     *
     * @throws \Exception 校验失败 / 重复申请
     */
    public function apply(array $data): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 64) {
            throw new \Exception('站点名称不能为空且不能超过64字');
        }

        $url = $this->normalizeUrl((string) ($data['url'] ?? ''));
        if ($url === '') {
            throw new \Exception('站点链接格式不正确，需以 http:// 或 https:// 开头');
        }

        // 同主机去重（含待审核/停用项），避免同一站点重复刷库
        if ($this->hostExists($url)) {
            throw new \Exception('该站点已提交或已收录，请勿重复申请');
        }

        $optional = static fn (mixed $value, int $max): ?string => $value === null || trim((string) $value) === ''
            ? null
            : mb_substr(trim((string) $value), 0, $max);

        $this->dao->save([
            'name'        => $name,
            'url'         => $url,
            'logo'        => $optional($data['logo'] ?? null, 255),
            'description' => $optional($data['description'] ?? null, 255),
            'contact'     => $optional($data['contact'] ?? null, 128),
            'category'    => 'footer',
            'target'      => '_blank',
            'enabled'     => 0, // 待审核：后台「网站设置→友情链接」启用后前台展示
            'sort'        => 0,
        ]);
    }

    /**
     * 归一化站点链接：补协议、仅允许 http(s)、校验主机合法性
     */
    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 512) {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            // 已带非 http(s) 协议（ftp:/javascript:/data: 等）直接拒绝；host:port 不视为协议
            if (preg_match('#^[a-z][a-z0-9+.-]*:(?!\d)#i', $url)) {
                return '';
            }
            $url = 'https://' . ltrim($url, '/');
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        return $url;
    }

    /**
     * 判断同主机（含子域名互判）是否已有页脚友链记录
     */
    private function hostExists(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            return false;
        }

        $rows = $this->dao->query()->where('category', 'footer')->get(['url']);
        foreach ($rows as $row) {
            $rowHost = strtolower((string) (parse_url((string) $row->url, PHP_URL_HOST) ?: ''));
            if ($rowHost === '') {
                continue;
            }
            if ($rowHost === $host || str_ends_with($rowHost, '.' . $host) || str_ends_with($host, '.' . $rowHost)) {
                return true;
            }
        }
        return false;
    }
}
