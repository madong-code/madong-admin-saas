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

namespace app\command\check;

use app\command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use ReflectionClass;
use ReflectionMethod;

/**
 * 检查控制器完整性（支持多模块）
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong:check:controller',
    description: 'Check controller integrity (method signatures, dependencies) across modules',
    hidden: false
)]
class CheckControllerCommand extends BaseCommand
{
    protected const SUPPORTED_MODULES = ['platformapi', 'adminapi', 'api'];

    protected function configure(): void
    {
        $this->addOption(
            'module',
            'm',
            InputOption::VALUE_OPTIONAL,
            'Module to check: platformapi, adminapi, api, or all',
            'all'
        );
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $module = $input->getOption('module');

        $modules = $module === 'all' ? self::SUPPORTED_MODULES : [$module];
        foreach ($modules as $mod) {
            if (!in_array($mod, self::SUPPORTED_MODULES, true)) {
                $io->error("Unsupported module '{$mod}'. Supported: " . implode(', ', self::SUPPORTED_MODULES));
                return Command::FAILURE;
            }
        }

        $io->title('Controller Integrity Checker');

        // ===== 阶段一：收集 =====
        $allModuleData  = [];
        $totalFiles     = 0;
        $totalIssues    = 0;
        $totalWarnings  = 0;

        foreach ($modules as $mod) {
            $data = $this->collectModuleData($mod);
            $allModuleData[$mod] = $data;
            $totalFiles    += $data['count'];
            $totalIssues   += count($data['issues']);
            $totalWarnings += count($data['warnings']);
        }

        // ===== 阶段二：逐模块明细 =====
        $this->renderDetail($allModuleData, $io);

        // ===== 阶段三：汇总表 =====
        $io->section('SUMMARY');
        $this->renderSummaryTable($modules, $allModuleData, $io, $totalFiles, $totalIssues, $totalWarnings);

        // ===== 最终状态 =====
        if ($totalIssues === 0 && $totalWarnings === 0) {
            $io->success('All checks passed!');
        } elseif ($totalIssues > 0) {
            $io->error("{$totalIssues} critical issue(s) - action required!");
        } else {
            $io->warning("{$totalWarnings} warning(s) - please review.");
        }

        return $totalIssues === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * 收集模块数据（静默，不输出）
     */
    protected function collectModuleData(string $module): array
    {
        $basePath       = app_path();
        $controllerPath = $basePath . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'controller';

        if (!is_dir($controllerPath)) {
            return ['count' => 0, 'rows' => [], 'issues' => [], 'warnings' => [], 'exists' => false];
        }

        $files = $this->globRecursive($controllerPath, '*.php');
        if (empty($files)) {
            return ['count' => 0, 'rows' => [], 'issues' => [], 'warnings' => [], 'exists' => true];
        }

        $rows     = [];
        $issues   = [];
        $warnings = [];
        $basePath = app_path() . DIRECTORY_SEPARATOR;

        foreach ($files as $file) {
            $className = $this->pathToClass($file);

            if (!class_exists($className)) {
                require_once $file;
            }

            if (!class_exists($className)) {
                $issues[] = [
                    'controller' => $this->relativeClassName($file, $module), 'line' => 1,
                    'file' => $file, 'message' => 'Class not loadable',
                ];
                $rows[] = ['<error>✗ ' . $this->relativeClassName($file, $module) . '</error>', str_replace($basePath, '', $file), '<error>Class not loadable</error>'];
                continue;
            }

            $shortName   = $this->relativeClassName($file, $module);
            $auditResult = $this->auditController($className);

            $cntI = count($auditResult['issues']);
            $cntW = count($auditResult['warnings']);

            // 收集 issue/warning 用于汇总
            foreach ($auditResult['issues'] as $i) {
                $i['controller'] = $shortName;
                $i['file']       = $file;
                $issues[] = $i;
            }
            foreach ($auditResult['warnings'] as $w) {
                $w['controller'] = $shortName;
                $w['file']       = $file;
                $warnings[] = $w;
            }

            // 构建明细行：单文件一条，合并所有问题
            if ($cntI > 0 || $cntW > 0) {
                $tag   = $cntI > 0 ? 'error' : 'comment';
                $icon  = $cntI > 0 ? '✗' : '⚠';
                $detail = [];
                foreach ($auditResult['issues'] as $i) {
                    $detail[] = "<error>✗ :{$i['line']}</error>  {$i['message']}";
                }
                foreach ($auditResult['warnings'] as $w) {
                    $detail[] = "<comment>⚠ :{$w['line']}</comment>  {$w['message']}";
                }
                $rows[] = [
                    "<{$tag}>{$icon} {$shortName}</{$tag}>",
                    str_replace($basePath, '', $file),
                    implode("\n", $detail),
                ];
            }
        }

        return [
            'count'    => count($files),
            'rows'     => $rows,
            'issues'   => $issues,
            'warnings' => $warnings,
            'exists'   => true,
        ];
    }

    protected function renderDetail(array $allData, SymfonyStyle $io): void
    {
        // 判断是否有任何问题
        $hasAnyProblem = false;
        $allRows       = [];
        foreach ($allData as $module => $data) {
            if (!empty($data['rows'])) {
                $hasAnyProblem = true;
                foreach ($data['rows'] as $row) {
                    $modIssues = count($data['issues']);
                    $modIcon   = $modIssues > 0 ? '✗' : '⚠';
                    $modTag    = $modIssues > 0 ? 'error' : 'comment';
                    array_unshift($row, "<{$modTag}>{$modIcon} {$module}</{$modTag}>");
                    $allRows[] = $row;
                }
            }
        }

        $io->section('DETAIL');

        if (!$hasAnyProblem) {
            $io->writeln("  <info>✓ All controllers OK</info>");
            $io->newLine();
            return;
        }

        // 先输出各模块状态
        foreach ($allData as $module => $data) {
            $io->writeln(" ── <info>{$module}</info> ──");

            if (($data['exists'] ?? true) === false) {
                $io->writeln("  <comment>!</comment> controller directory not found");
                continue;
            }

            $clean = $data['count'] - count($data['rows']);
            $bad   = count($data['rows']);

            if ($data['count'] === 0) {
                $io->writeln("  <info>✓</info> no controllers");
                continue;
            }

            if ($clean > 0 && $bad === 0) {
                $io->writeln("  <info>✓ {$clean} controllers OK</info>");
                continue;
            }

            if ($clean > 0) {
                $io->writeln("  <info>✓ {$clean} clean</info>");
            }
        }

        // 一个统一表格
        $io->table(
            ['Module', 'Controller', 'File', 'Problem'],
            $allRows
        );

        $io->newLine();
    }

    /**
     * 渲染汇总表：Module | Controllers | Issues | Warnings
     */
    protected function renderSummaryTable(
        array $modules,
        array $results,
        SymfonyStyle $io,
        int $totalFiles,
        int $totalIssues,
        int $totalWarnings
    ): void {
        $rows = [];
        foreach ($modules as $mod) {
            $r = $results[$mod] ?? ['count' => 0, 'issues' => [], 'warnings' => [], 'exists' => false];
            $cntI   = count($r['issues']);
            $cntW   = count($r['warnings']);
            $exists = $r['exists'] ?? true;

            if ($cntI > 0) {
                $icon = '<error>✗</error>';
            } elseif ($cntW > 0) {
                $icon = '<comment>⚠</comment>';
            } elseif (!$exists) {
                $icon = '<fg=gray>-</>';
            } else {
                $icon = '<info>✓</info>';
            }

            $rows[] = [
                $icon . ' ' . $mod,
                $r['count'],
                $cntI > 0 ? '<error>' . $cntI . '</error>' : $cntI,
                $cntW > 0 ? '<comment>' . $cntW . '</comment>' : $cntW,
            ];
        }

        $totalIcon = $totalIssues > 0 ? '<error>✗</error>' : ($totalWarnings > 0 ? '<comment>⚠</comment>' : '<info>✓</info>');
        $rows[] = [
            '<options=bold>' . $totalIcon . ' TOTAL</>',
            '<options=bold>' . $totalFiles . '</>',
            $totalIssues > 0 ? '<error><options=bold>' . $totalIssues . '</></error>' : $totalIssues,
            $totalWarnings > 0 ? '<comment><options=bold>' . $totalWarnings . '</></comment>' : $totalWarnings,
        ];

        $io->table(
            ['Module', 'Controllers', 'Issues', 'Warnings'],
            $rows
        );
    }

    // ======================== 审查 ========================

    protected function auditController(string $class): array
    {
        $issues   = [];
        $warnings = [];

        $reflection = new ReflectionClass($class);
        $methods    = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $methodName = $method->getName();
            if (str_starts_with($methodName, '__')) {
                continue;
            }

            $params     = $method->getParameters();
            $paramNames = array_map(fn($p) => $p->getName(), $params);
            $startLine  = $method->getStartLine();
            $endLine    = $method->getEndLine();
            $fileName   = $method->getFileName();

            if ($fileName && file_exists($fileName)) {
                $lines      = file($fileName);
                $methodBody = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

                if (str_contains($methodBody, '$request') && !in_array('request', $paramNames, true)) {
                    $issues[] = [
                        'line'    => $startLine,
                        'message' => "{$methodName}() uses \$request but parameter not declared",
                    ];
                }

                if (!$method->getReturnType()) {
                    $warnings[] = [
                        'line'    => $startLine,
                        'message' => "{$methodName}() has no return type hint",
                    ];
                }
            }
        }

        $constructor = $reflection->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();
                if ($type && !$type->isBuiltin()) {
                    $typeName = $type->getName();
                    if (!class_exists($typeName) && !interface_exists($typeName)) {
                        $issues[] = [
                            'line'    => $constructor->getStartLine(),
                            'message' => "Dependency '{$typeName}' not found",
                        ];
                    }
                }
            }
        }

        return ['issues' => $issues, 'warnings' => $warnings];
    }

    // ======================== 辅助 ========================

    protected function pathToClass(string $file): string
    {
        $basePath   = app_path();
        $relative   = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file);
        $withoutExt = str_replace('.php', '', $relative);
        return 'app\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $withoutExt);
    }

    protected function relativeClassName(string $file, string $module): string
    {
        $basePath = app_path() . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR;
        $relative = str_replace($basePath, '', $file);
        return str_replace(['.php', DIRECTORY_SEPARATOR], ['', '\\'], $relative);
    }

    protected function globRecursive(string $dir, string $pattern): array
    {
        $files   = glob($dir . DIRECTORY_SEPARATOR . $pattern);
        $subdirs = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        foreach ($subdirs as $subdir) {
            if (!str_starts_with(basename($subdir), '.')) {
                $files = array_merge($files, $this->globRecursive($subdir, $pattern));
            }
        }

        return $files;
    }
}
