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

namespace app\command\config;

use app\command\BaseCommand;
use app\model\tenant\PlatformMenu;
use core\io\uuid\Snowflake;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 平台菜单同步
 *
 * 将 resource/data/menu/platform.php 与 saas_template_menu(app='platform') 对齐。
 *
 * 三种模式：
 *   - 增量（默认）：仅补插文件中存在但数据库中缺失的节点，已存在节点跳过（安全、幂等）
 *   - --sync：智能同步 —— 新增缺失节点 + 更新已有节点字段 + 删除文件中不存在的节点
 *   - --full：清空所有 platform 菜单后全量重导（所有 ID 会重新生成）
 *
 * 使用方法：
 *   php webman madong:migrate-platform-menu                       # 增量补插
 *   php webman madong:migrate-platform-menu --sync                 # 智能同步
 *   php webman madong:migrate-platform-menu --full                 # 清空重导
 *   php webman madong:migrate-platform-menu --sync --no-interaction  # CI/CD 免确认
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong:migrate-platform-menu',
    description: '同步 platform.php 菜单（增量/--sync 智能同步/--full 清空重导）',
    hidden: false
)]
class MigratePlatformMenuCommand extends BaseCommand
{
    /**
     * 文件与数据库字段映射：哪些字段可以在 --sync 模式下更新
     */
    private const UPDATABLE_FIELDS = [
        'title',
        'level',
        'type',
        'sort',
        'path',
        'component',
        'redirect',
        'icon',
        'is_show',
        'is_link',
        'link_url',
        'enabled',
        'open_type',
        'is_cache',
        'is_sync',
        'is_affix',
        'is_global',
        'variable',
        'methods',
        'is_frame',
        'pid',
    ];

    protected function configure(): void
    {
        $this->addOption(
            'sync',
            's',
            InputOption::VALUE_NONE,
            '智能同步：新增缺失 + 更新已有 + 删除文件中不存在的节点'
        );
        $this->addOption(
            'full',
            'o',
            InputOption::VALUE_NONE,
            '全量重导：清空所有 platform 菜单后重新导入（--sync 和 --full 互斥，不支持同时使用）'
        );
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $sync = $input->getOption('sync');
        $full = $input->getOption('full');

        $io->title('Platform Menu Migration');

        // --sync 和 --full 互斥
        if ($sync && $full) {
            $io->error('--sync 和 --full 互斥，请只选择其中一种模式。');
            return Command::FAILURE;
        }

        if ($full) {
            return $this->fullMigrate($io, $input);
        }

        if ($sync) {
            return $this->syncMigrate($io, $input);
        }

        // 默认：增量模式
        return $this->incrementalMigrate($io);
    }

    // ========================
    //  模式 1：增量（默认）
    // ========================

    private function incrementalMigrate(SymfonyStyle $io): int
    {
        $menus = $this->loadMenus();

        $inserted = 0;
        $skipped  = 0;

        foreach ($menus as $menu) {
            $this->processNode($menu, 0, $inserted, $skipped, false);
        }

        $io->success(sprintf('增量同步完成：新增 %d 条，已存在跳过 %d 条。', $inserted, $skipped));
        return Command::SUCCESS;
    }

    // ========================
    //  模式 2：智能同步 --sync
    // ========================

    private function syncMigrate(SymfonyStyle $io, InputInterface $input): int
    {
        if ($input->isInteractive()) {
            $io->caution('⚠ --sync 模式将：新增缺失 + 更新已有 + 删除 platform.php 中不存在的节点！');
            if (!$io->confirm('确认执行智能同步？', false)) {
                $io->text('已取消操作。');
                return Command::SUCCESS;
            }
        }

        $menus = $this->loadMenus();

        // Step 1: 收集文件中所有 code，用于后续删除判断
        $fileCodes = [];
        $this->collectCodes($menus, $fileCodes);

        // Step 2: 新增 + 更新
        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach ($menus as $menu) {
            $this->processNode($menu, 0, $inserted, $skipped, true, $updated);
        }

        // Step 3: 删除文件中不存在的节点
        $deleted = 0;
        if (!empty($fileCodes)) {
            $deleted = PlatformMenu::query()
                ->where('app', 'platform')
                ->whereNotIn('code', $fileCodes)
                ->delete();
        }

        $io->success(sprintf(
            '智能同步完成：新增 %d 条，更新 %d 条，跳过 %d 条，删除 %d 条。',
            $inserted,
            $updated,
            $skipped,
            $deleted
        ));

        return Command::SUCCESS;
    }

    // ========================
    //  模式 3：全量重导 --full
    // ========================

    private function fullMigrate(SymfonyStyle $io, InputInterface $input): int
    {
        if ($input->isInteractive()) {
            $io->caution('⚠ --full 模式将清空 saas_template_menu 中所有 app=\'platform\' 的菜单后重新导入！');
            $io->warning('   所有菜单 ID 将重新生成（Snowflake），外部引用的菜单 ID 可能失效。');
            if (!$io->confirm('确认清空并重新全量导入？', false)) {
                $io->text('已取消操作。');
                return Command::SUCCESS;
            }
        }

        // Step 1: 清空
        $deleted = PlatformMenu::query()->where('app', 'platform')->delete();
        $io->text(sprintf('已清空 %d 条 platform 菜单记录。', $deleted));

        // Step 2: 全量导入（清空后不存在冲突，跳过数始终为 0）
        $menus = $this->loadMenus();

        $inserted = 0;
        $skipped  = 0;

        foreach ($menus as $menu) {
            $this->processNode($menu, 0, $inserted, $skipped, false);
        }

        $io->success(sprintf('全量重导完成：清空 %d 条 → 导入 %d 条。', $deleted, $inserted));
        return Command::SUCCESS;
    }

    // ========================
    //  递归处理节点
    // ========================

    /**
     * 递归处理单个菜单节点
     *
     * @param array      $menu     菜单节点数据
     * @param int|string $pid      父节点 ID
     * @param int        &$inserted 累计新增计数
     * @param int        &$skipped  累计跳过计数
     * @param bool       $doUpdate 是否更新已有节点（--sync 模式为 true）
     * @param int        &$updated  累计更新计数（仅 --sync 模式使用）
     * @return string|null 当前节点 ID
     */
    private function processNode(array $menu, int|string $pid, int &$inserted, int &$skipped, bool $doUpdate, int &$updated = 0): ?string
    {
        $code = $menu['code'] ?? '';
        $app  = $menu['app'] ?? 'platform';

        if (!empty($code)) {
            $existing = PlatformMenu::query()
                ->where('app', $app)
                ->where('code', $code)
                ->where('pid', (string) $pid)
                ->first();

            if ($existing) {
                // --sync 模式：更新已有节点字段
                if ($doUpdate) {
                    $changed = $this->updateNodeFields($existing, $menu, $pid);
                    $changed ? $updated++ : $skipped++;
                } else {
                    $skipped++;
                }

                $currentId = $existing->id;

                // 递归处理子节点
                if (!empty($menu['children'])) {
                    foreach ($menu['children'] as $child) {
                        $this->processNode($child, $currentId, $inserted, $skipped, $doUpdate, $updated);
                    }
                }

                return $currentId;
            }
        }

        // 不存在 → 新增
        $currentId = $this->insertNode($menu, $pid);
        $inserted++;

        // 递归处理子节点
        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->processNode($child, $currentId, $inserted, $skipped, $doUpdate, $updated);
            }
        }

        return $currentId;
    }

    /**
     * 更新已有节点的字段（--sync 模式），返回是否有字段变化
     */
    private function updateNodeFields(PlatformMenu $model, array $menu, int|string $pid): bool
    {
        $changed = false;

        // 更新 pid（父节点可能变了）
        if ($pid > 0 && (string) $model->pid !== (string) $pid) {
            $model->pid = $pid;
            $changed    = true;
        }

        foreach (self::UPDATABLE_FIELDS as $field) {
            if ($field === 'pid') {
                continue; // 已在上面单独处理
            }

            $fileValue  = $menu[$field] ?? null;
            $modelValue = $model->$field;

            // 统一类型比较，null 和 0、'' 需要区分处理
            if ($fileValue !== null && $this->normalize($modelValue) !== $this->normalize($fileValue)) {
                $model->$field = $fileValue;
                $changed       = true;
            }
        }

        if ($changed) {
            $model->save();
        }

        return $changed;
    }

    /**
     * 插入新菜单节点
     */
    private function insertNode(array $menu, int|string $pid): string
    {
        $model            = new PlatformMenu();
        $model->id        = Snowflake::generate();
        $model->pid       = $pid;
        $model->app       = $menu['app'] ?? 'platform';
        $model->title     = $menu['title'] ?? '';
        $model->code      = $menu['code'] ?? '';
        $model->level     = $menu['level'] ?? null;
        $model->type      = $menu['type'] ?? 1;
        $model->sort      = $menu['sort'] ?? 0;
        $model->path      = $menu['path'] ?? '';
        $model->component = $menu['component'] ?? '';
        $model->redirect  = $menu['redirect'] ?? '';
        $model->icon      = $menu['icon'] ?? '';
        $model->is_show   = $menu['is_show'] ?? 1;
        $model->is_link   = $menu['is_link'] ?? 0;
        $model->link_url  = $menu['link_url'] ?? null;
        $model->enabled   = $menu['enabled'] ?? 1;
        $model->open_type = $menu['open_type'] ?? 0;
        $model->is_cache  = $menu['is_cache'] ?? 0;
        $model->is_sync   = $menu['is_sync'] ?? 1;
        $model->is_affix  = $menu['is_affix'] ?? 0;
        $model->is_global = $menu['is_global'] ?? 0;
        $model->variable  = $menu['variable'] ?? '';
        $model->methods   = $menu['methods'] ?? 'GET';
        $model->is_frame  = $menu['is_frame'] ?? 0;
        $model->save();

        return $model->id;
    }

    // ========================
    //  辅助方法
    // ========================

    private function loadMenus(): array
    {
        return include base_path('resource/data/menu/platform.php');
    }

    /**
     * 递归收集所有菜单的 code，用于 --sync 模式下判断哪些数据库记录需要删除
     */
    private function collectCodes(array $menus, array &$codes): void
    {
        foreach ($menus as $menu) {
            if (!empty($menu['code'])) {
                $codes[] = $menu['code'];
            }
            if (!empty($menu['children'])) {
                $this->collectCodes($menu['children'], $codes);
            }
        }
    }

    /**
     * 值归一化，用于比较（null 视为 ''）
     */
    private function normalize(mixed $value): string
    {
        return (string) ($value ?? '');
    }
}
