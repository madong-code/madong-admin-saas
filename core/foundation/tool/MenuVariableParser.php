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
namespace core\foundation\tool;

/**
 * 菜单 variable 扩展字段解析器
 *
 * variable 是菜单的通用扩展字段，后续还要承载个人设置、主题、用户偏好等配置。
 * 约定：根级按「功能域」平铺，每个功能域独占一个 key、其值为该域的对象，
 * 域内字段不扁平到根级，便于按单个 key 独立处理该域的数据集合。
 *
 * ```jsonc
 * {
 *   "authority": ["system:user:list"],            // 历史语义（兼容纯逗号串）
 *   "badge": { "text": "2", "type": "normal", "variants": "primary" },
 *   "setting": {}                                  // 预留：个人设置/主题/用户偏好
 * }
 * ```
 *
 * 旧格式（纯逗号串）解析时视为 authority，向后兼容。
 */
final class MenuVariableParser
{
    /** 历史权限域 key（兼容纯逗号串） */
    private const DOMAIN_AUTHORITY = 'authority';

    /** 徽标功能域 key */
    private const DOMAIN_BADGE = 'badge';

    /**
     * 解析 menu.variable → 结构化数组；兼容纯逗号串（视为 authority）
     *
     * @param string|null $variable
     *
     * @return array
     */
    public static function parse(?string $variable): array
    {
        $raw = trim((string)$variable);
        if ($raw === '') {
            return [];
        }

        if (str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $list = array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));

        return $list === [] ? [] : [self::DOMAIN_AUTHORITY => $list];
    }

    /**
     * 权限码列表（等价旧的 explode(',') 语义）
     *
     * @param string|null $variable
     *
     * @return array
     */
    public static function authority(?string $variable): array
    {
        return (array)(self::parse($variable)[self::DOMAIN_AUTHORITY] ?? []);
    }

    /**
     * 通用读取：取根级某功能域的配置（setting/theme 等未来扩展复用）
     *
     * @param string|null $variable
     * @param string      $key
     * @param mixed       $default
     *
     * @return mixed
     */
    public static function domain(?string $variable, string $key, mixed $default = null): mixed
    {
        $parsed = self::parse($variable);

        return is_array($parsed[$key] ?? null) ? $parsed[$key] : $default;
    }

    /**
     * 提取静态徽标配置（根级 badge 域）；未配置返回 null（不向接口下发徽标字段）
     *
     * @param string|null $variable
     *
     * @return array|null ['badge' => string, 'badge_type' => string, 'badge_variants' => string]
     */
    public static function badge(?string $variable): ?array
    {
        $badge = self::domain($variable, self::DOMAIN_BADGE);
        if (!is_array($badge)) {
            return null;
        }

        $text = trim((string)($badge['text'] ?? ''));
        $type = (string)($badge['type'] ?? 'normal');
        if ($text === '' && $type !== 'dot') {
            return null;
        }

        return [
            'badge'          => $text,
            'badge_type'     => $type === 'dot' ? 'dot' : 'normal',
            'badge_variants' => (string)($badge['variants'] ?? 'default'),
        ];
    }

    /**
     * 通用写回：写入根级某功能域（$value 传 null 表示移除该域），不影响其它域
     *
     * @param string|null $variable
     * @param string      $key
     * @param mixed       $value
     *
     * @return string
     */
    public static function withDomain(?string $variable, string $key, mixed $value): string
    {
        $parsed = self::parse($variable);
        if ($value === null) {
            unset($parsed[$key]);
        } else {
            $parsed[$key] = $value;
        }

        return self::encode($parsed);
    }

    /**
     * 写回徽标配置（根级 badge 域）；$text 为 null，或空串且非 dot 时移除该域
     *
     * @param string|null $variable
     * @param string|null $text
     * @param string      $type
     * @param string      $variants
     *
     * @return string
     */
    public static function withBadge(?string $variable, ?string $text, string $type = 'normal', string $variants = 'default'): string
    {
        if ($text === null || ($text === '' && $type !== 'dot')) {
            return self::withDomain($variable, self::DOMAIN_BADGE, null);
        }

        return self::withDomain($variable, self::DOMAIN_BADGE, [
            'text'     => $text,
            'type'     => $type === 'dot' ? 'dot' : 'normal',
            'variants' => $variants,
        ]);
    }

    /**
     * 编码：仅含 authority 时回退为逗号串（保持与旧数据一致的可读形式），否则输出 JSON
     *
     * @param array $parsed
     *
     * @return string
     */
    private static function encode(array $parsed): string
    {
        if ($parsed === []) {
            return '';
        }

        if (array_keys($parsed) === [self::DOMAIN_AUTHORITY]) {
            return implode(',', (array)$parsed[self::DOMAIN_AUTHORITY]);
        }

        return (string)json_encode($parsed, JSON_UNESCAPED_UNICODE);
    }
}