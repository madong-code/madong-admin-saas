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
use app\enum\system\TenantMode;
use app\model\tenant\WebMenuTemplate;
use core\io\uuid\Snowflake;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 前台菜单模板同步（多租户模式）
 *
 * 将 resource/data/menu/web.php 与 saas_template_web_menu(app='web') 对齐。
 * 多租户模式（FIELD/DB）下，新建租户时会从 saas_template_web_menu 复制菜单树。
 *
 * 三种模式：
 *   - 增量（默认）：仅补插文件中存在但数据库中缺失的节点，已存在节点跳过（安全、幂等）
 *   - --sync：智能同步 —— 新增缺失节点 + 更新已有节点字段 + 删除文件中不存在的节点
 *   - --full：清空所有 web 菜单模板后全量重导（所有 ID 会重新生成）
 *
 * 使用方法：
 *   php webman madong:migrate-web-menu-template                       # 增量补插
 *   php webman madong:migrate-web-menu-template --sync                 # 智能同步
 *   php webman madong:migrate-web-menu-template --full                 # 清空重导
 *   php webman madong:migrate-web-menu-template --sync --no-interaction  # CI/CD 免确认
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong:migrate-web-menu-template',
    description: '多租户模式：同步 web.php 前台菜单到 saas_template_web_menu（增量/--sync 智能同步/--full 清空重导）',
    hidden: false
)]
class MigrateWebMenuTemplateCommand extends BaseCommand
{
    /**
     * 文件与数据库字段映射：哪些字段可以在 --sync 模式下更新
     */
    private const UPDATABLE_FIELDS = [
        'name',
        'url',
        'icon',
        'level',
        'type',
        'sort',
        'target',
        'category',
        'source',
        'is_public',
        'is_no_auth',
        'is_show',
        'enabled',
        'extra',
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
            '全量重导：清空所有 web 菜单模板后重新导入（--sync 和 --full 互斥）'
        );
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $sync = $input->getOption('sync');
        $full = $input->getOption('full');

        $io->title('Web Menu Template Migration (Multi-Tenant)');

        // 仅多租户模式（FIELD/DB）下才需要操作菜单模板表
        $tenantMode = TenantMode::fromConfig(config('tenant.mode', 'single'));
        if (!$tenantMode->isMultiTenant()) {
            $io->error(sprintf(
                '当前为非租户模式（%s），前台菜单请使用对应命令直接同步到 web_menu 表。',
                $tenantMode->label()
            ));
            return Command::FAILURE;
        }

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
            $io->caution('⚠ --sync 模式将：新增缺失 + 更新已有 + 删除 web.php 中不存在的节点！');
            if (!$io->confirm('确认执行智能同步？', false)) {
                $io->text('已取消操作。');
                return Command::SUCCESS;
            }
        }

        $menus = $this->loadMenus();

        // Step 1: 收集文件中所有 code
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
            $deleted = WebMenuTemplate::query()
                ->where('app', 'web')
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
            $io->caution('⚠ --full 模式将清空 saas_template_web_menu 中所有 app=\'web\' 的菜单模板后重新导入！');
            $io->warning('   所有菜单模板 ID 将重新生成（Snowflake），已创建的租户菜单不受影响。');
            if (!$io->confirm('确认清空并重新全量导入？', false)) {
                $io->text('已取消操作。');
                return Command::SUCCESS;
            }
        }

        // Step 1: 清空
        $deleted = WebMenuTemplate::query()->where('app', 'web')->delete();
        $io->text(sprintf('已清空 %d 条 web 菜单模板记录。', $deleted));

        // Step 2: 全量导入
        $menus    = $this->loadMenus();
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
     */
    private function processNode(
        array $menu,
        int|string $pid,
        int &$inserted,
        int &$skipped,
        bool $doUpdate,
        int &$updated = 0
    ): ?string {
        $code = $menu['code'] ?? '';
        $app  = $menu['app'] ?? 'web';

        if (!empty($code)) {
            $existing = WebMenuTemplate::query()
                ->where('app', $app)
                ->where('code', $code)
                ->first();

            if ($existing) {
                if ($doUpdate) {
                    $changed = $this->updateNodeFields($existing, $menu, $pid);
                    $changed ? $updated++ : $skipped++;
                } else {
                    $skipped++;
                }

                $currentId = $existing->id;

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

        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->processNode($child, $currentId, $inserted, $skipped, $doUpdate, $updated);
            }
        }

        return $currentId;
    }

    /**
     * 更新已有节点字段（--sync 模式）
     */
    private function updateNodeFields(WebMenuTemplate $model, array $menu, int|string $pid): bool
    {
        $changed = false;

        if ($pid > 0 && (string) $model->pid !== (string) $pid) {
            $model->pid = $pid;
            $changed    = true;
        }

        foreach (self::UPDATABLE_FIELDS as $field) {
            if ($field === 'pid') {
                continue;
            }

            $fileValue  = $menu[$field] ?? null;
            $modelValue = $model->$field;

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
     * 插入新菜单模板节点
     */
    private function insertNode(array $menu, int|string $pid): string
    {
        $model            = new WebMenuTemplate();
        $model->id        = Snowflake::generate();
        $model->pid       = $pid;
        $model->app       = $menu['app'] ?? 'web';
        $model->category  = $menu['category'] ?? 1;
        $model->source    = $menu['source'] ?? 'system';
        $model->code      = $menu['code'] ?? '';
        $model->is_public = $menu['is_public'] ?? 0;
        $model->is_no_auth = $menu['is_no_auth'] ?? 0;
        $model->name      = $menu['name'] ?? '';
        $model->url       = $menu['url'] ?? '';
        $model->icon      = $menu['icon'] ?? '';
        $model->level     = $menu['level'] ?? 1;
        $model->type      = $menu['type'] ?? 1;
        $model->sort      = $menu['sort'] ?? 0;
        $model->target    = $menu['target'] ?? 1;
        $model->extra     = $menu['extra'] ?? null;
        $model->is_show   = $menu['is_show'] ?? 1;
        $model->enabled   = $menu['enabled'] ?? 1;
        $model->save();

        return $model->id;
    }

    // ========================
    //  辅助方法
    // ========================

    private function loadMenus(): array
    {
        return include base_path('resource/data/menu/web.php');
    }

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

    private function normalize(mixed $value): string
    {
        return (string) ($value ?? '');
    }
}
