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

namespace app\command\install;

use app\command\BaseCommand;
use core\business\install\InstallConfigResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * 下载前端模板资源（按 config/install.php 的模式与模板配置下载）
 *
 * 使用方法：
 *   php webman madong-download:template                         # 按默认模式下载对应模板
 *   php webman madong-download:template --mode=mono              # mono 结构：template/mono + template/web
 *   php webman madong-download:template --mode=separated         # separated 结构：template/admin + platform + web
 *   php webman madong-download:template --admin-variant=aura     # admin 来源选 aura，仍落到 template/admin
 *   php webman madong-download:template --mirror=github          # 切回 GitHub 官方源（默认 Gitee 国内镜像）
 *   php webman madong-download:template --method=git              # 切回 Git 克隆（默认 ZIP 归档下载）
 *   php webman madong-download:template --templates=single       # single/aura 都是 admin 别名，落到 template/admin
 *   php webman madong-download:template -s                       # 额外下载 skills（不在 install.php 模板体系内）
 *   php webman madong-download:template -b develop               # 指定分支
 *   php webman madong-download:template -f                       # 目录已存在时强制更新
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong-download:template',
    description: 'Download frontend templates configured in config/install.php',
    aliases: ['madong-download:template'],
    hidden: false
)]
class DownloadCommand extends BaseCommand
{
    private ?InstallConfigResolver $configResolver = null;

    /** 当前镜像策略下的 git -c 选项（国内镜像 = 绕过代理，海外镜像 = 自动注入检测到的代理） */
    private string $gitConfigOptions = '';

    /** 自动检测到的本机代理 URL（如 http://127.0.0.1:7890），未检测到时为 null */
    private ?string $detectedProxyHost = null;

    protected function configure(): void
    {
        $this
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, '前端模板组织模式：mono/separated；默认取 config/install.php 的 default_mode')
            ->addOption('method', null, InputOption::VALUE_REQUIRED, '下载方式：zip（默认，归档下载）/git（克隆）')
            ->addOption('mirror', null, InputOption::VALUE_REQUIRED, '下载源：gitee（国内镜像，默认）/github（官方源）')
            ->addOption('admin-variant', null, InputOption::VALUE_REQUIRED, 'admin 的 UI 来源：single/aura；两者都下载到 template/admin')
            ->addOption('templates', null, InputOption::VALUE_REQUIRED, '模板名列表（逗号分隔，如 admin/platform/web；single/aura 为 admin 别名）；默认取模式配置')
            ->addOption('skills', 's', InputOption::VALUE_NONE, '额外下载 skills 资源')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '目录已存在时强制更新')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Git 分支；默认取 config/install.php 的 download.default_branch');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Template Downloader');

        $downloadConfig = $this->downloadConfig();
        $method = $this->stringOption($input, 'method')
            ?? $this->stringValue($downloadConfig['method'] ?? null)
            ?? 'zip';
        if ($method === 'git' && !$this->isGitAvailable()) {
            $io->error('Git is not available. Please install Git first.');
            $io->note('Download from: https://git-scm.com/downloads');
            return Command::FAILURE;
        }
        if ($method === 'zip' && !class_exists(\ZipArchive::class)) {
            $io->error('PHP zip extension is required for ZIP download.');
            return Command::FAILURE;
        }

        $resolver = $this->resolver();
        $requestedMode = $this->stringOption($input, 'mode');
        $mode = $resolver->resolveMode($requestedMode);
        if ($requestedMode !== null && $requestedMode !== $mode) {
            $io->warning("Invalid mode '{$requestedMode}', fallback to '{$mode}'");
        }

        $requestedVariant = $this->stringOption($input, 'admin-variant');
        $variant = $resolver->resolveAdminVariant($requestedVariant);
        if ($requestedVariant !== null && $requestedVariant !== $variant) {
            $io->warning("Invalid admin variant '{$requestedVariant}', fallback to '{$variant}'");
        }
        if ($mode === 'mono' && $requestedVariant !== null) {
            $io->note('mono mode uses the single mono repository; admin-variant is ignored');
        }

        $templates = $resolver->resolveTemplates($mode, $this->parseTemplates($input));
        if ($templates === []) {
            $io->error('No template resolved from config/install.php');
            return Command::FAILURE;
        }

        $mirrors = is_array($downloadConfig['mirrors'] ?? null) ? $downloadConfig['mirrors'] : [];
        $mirrorName = $this->stringOption($input, 'mirror')
            ?? $this->stringValue($downloadConfig['mirror'] ?? null)
            ?? 'gitee';
        if (!isset($mirrors[$mirrorName])) {
            $io->warning("Unknown mirror '{$mirrorName}', fallback to github");
            $mirrorName = 'github';
        }
        $mirrorConfig = is_array($mirrors[$mirrorName] ?? null) ? $mirrors[$mirrorName] : [];

        // 自动检测系统代理（Clash / V2Ray / git 全局配置 / 环境变量）
        $this->detectedProxyHost = $this->detectSystemProxy();

        if (!empty($mirrorConfig['no_proxy'])) {
            // 国内镜像：强制绕过系统代理直连（gitee/gitcode 走直连通常比走代理更快更稳定）
            $this->gitConfigOptions = '-c http.proxy= -c https.proxy= ';
        } elseif ($this->detectedProxyHost !== null) {
            // 海外镜像（github）：自动注入检测到的代理
            $this->gitConfigOptions = sprintf(
                '-c http.proxy=%s -c https.proxy=%s ',
                $this->detectedProxyHost,
                $this->detectedProxyHost
            );
        } else {
            // 未检测到代理：让 git 使用自身全局配置（若用户手动设置过 http.proxy 会自动生效）
            $this->gitConfigOptions = '';
        }

        // 按镜像优先级合并仓库表：主镜像优先；fallback_enabled 关闭（默认）时不回退到其他仓库
        $mirrorChain = [$mirrorName];
        if (!empty($downloadConfig['fallback_enabled'])) {
            $mirrorChain = array_values(array_unique(array_merge(
                [$mirrorName],
                is_array($downloadConfig['fallback_mirrors'] ?? null) ? $downloadConfig['fallback_mirrors'] : []
            )));
        }
        $repositories = [];
        foreach (array_reverse($mirrorChain) as $candidateName) {
            $candidate = is_array($mirrors[$candidateName] ?? null) ? $mirrors[$candidateName] : [];
            $candidateRepos = is_array($candidate['repositories'] ?? null) ? $candidate['repositories'] : [];
            $repositories = array_merge($repositories, $candidateRepos);
        }

        $branch = $this->stringOption($input, 'branch')
            ?: ($this->stringValue($downloadConfig['default_branch'] ?? null) ?? 'main');
        $projectRoot = dirname(base_path());

        $modeLabel = $this->stringValue($resolver->modes()[$mode]['label'] ?? null) ?? $mode;
        $variantConfig = $resolver->adminVariants()[$variant] ?? [];
        $variantLabel = $this->stringValue($variantConfig['label'] ?? null) ?? $variant;
        $mirrorLabel = $this->stringValue($mirrorConfig['label'] ?? null) ?? $mirrorName;
        $io->text(sprintf('Template mode: <info>%s</info> (%s)', $mode, $modeLabel));
        $io->text('Method: ' . strtoupper($method));
        $io->text("Source: {$mirrorLabel} ({$mirrorName})");
        $io->text("Admin UI: {$variantLabel} ({$variant}) -> template/admin");
        $io->text('Templates: ' . implode(', ', $templates));
        $io->text('Branch: ' . $branch);

        $targets = $this->buildTargets(
            $projectRoot,
            $mode,
            $templates,
            $downloadConfig,
            $repositories,
            $mirrorConfig,
            (bool) $input->getOption('skills'),
            $resolver,
            $variant,
            $io
        );

        if ($targets === []) {
            $io->error('No download target available, check download.repositories in config/install.php');
            return Command::FAILURE;
        }

        $force = (bool) $input->getOption('force');
        $success = 0;
        foreach ($targets as $target) {
            $io->section("Downloading: {$target['name']}");
            $targetDir = $target['dir'];

            if (is_dir($targetDir)) {
                if (!$force && !$io->confirm("'{$target['relative']}' already exists. Update it?", true)) {
                    $io->note("Skipped {$target['relative']}");
                    continue;
                }
            } else {
                $parentDir = dirname($targetDir);
                if (!is_dir($parentDir) && !mkdir($parentDir, 0755, true) && !is_dir($parentDir)) {
                    $io->error("Cannot create directory: {$parentDir}");
                    continue;
                }
            }

            if ($method === 'zip') {
                $archiveUrl = $this->archiveUrl($target['url'], $mirrorName, $branch, $downloadConfig);
                if ($archiveUrl === null) {
                    $io->error("No archive template configured for mirror '{$mirrorName}'");
                    continue;
                }
                $io->info("Downloading ZIP: {$archiveUrl}");
                if ($this->downloadAndExtractZip($archiveUrl, $targetDir, $io, $target['subdir'] ?? null, $target['repo_key'] ?? null)) {
                    $io->success("Extracted to {$target['relative']}");
                    $success++;
                }
                continue;
            }

            // 有效 git 仓库 → pull 双通道更新
            if (is_dir($targetDir) && $this->isUsableRepo($targetDir)) {
                $pullCommand = "cd \"{$targetDir}\" && git %spull origin {$branch}";
                // 直连存在间歇性 reset：先按镜像策略（国内镜像绕过代理直连），失败再用 git 全局代理重试
                if ($this->execGit(sprintf($pullCommand, $this->gitConfigOptions), $io)
                    || ($this->gitConfigOptions !== '' && $this->execGit(sprintf($pullCommand, ''), $io))) {
                    $io->success("Updated {$target['relative']}");
                    $success++;
                    continue;
                }
                $io->error([
                    "Git pull failed for {$target['relative']} (direct and proxy both failed).",
                    "Options: start your proxy client and retry, run with --method=zip to switch to ZIP archive download,",
                    "or manually execute in {$targetDir}: git pull origin {$branch}",
                ]);
                continue;
            }

            // 目标不存在，或为 zip 产物/失败 clone 残留等非仓库目录 → 临时区 clone 后替换
            if ($this->downloadViaClone($target, $projectRoot, $branch, $io)) {
                $success++;
            }
        }

        $total = count($targets);
        $io->section('Summary');
        if ($success === $total) {
            $io->success("All {$total} template(s) downloaded successfully!");
            return Command::SUCCESS;
        }
        if ($success > 0) {
            $io->warning("Downloaded {$success} / {$total} template(s)");
            return Command::FAILURE;
        }
        $io->error('Failed to download any template');
        return Command::FAILURE;
    }

    private function resolver(): InstallConfigResolver
    {
        return $this->configResolver ??= new InstallConfigResolver();
    }

    private function downloadConfig(): array
    {
        $config = config('install.download', []);
        return is_array($config) ? $config : [];
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        return $this->stringValue($input->getOption($name));
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function parseTemplates(InputInterface $input): ?array
    {
        $value = $this->stringOption($input, 'templates');
        if ($value === null) {
            return null;
        }
        $templates = array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn ($item) => $item !== ''
        ));
        return $templates !== [] ? $templates : null;
    }

    /**
     * @return array<int, array{name:string,url:string,dir:string,relative:string}>
     */
    private function buildTargets(
        string $projectRoot,
        string $mode,
        array $templates,
        array $downloadConfig,
        array $repositories,
        array $mirrorConfig,
        bool $withSkills,
        InstallConfigResolver $resolver,
        string $variant,
        SymfonyStyle $io
    ): array {
        $modeRepositories = is_array($downloadConfig['mode_repositories'] ?? null) ? $downloadConfig['mode_repositories'] : [];
        $archiveSubdirs = is_array($downloadConfig['archive_subdirs'] ?? null) ? $downloadConfig['archive_subdirs'] : [];
        $modes = $resolver->modes();
        $modeConfig = is_array($modes[$mode] ?? null) ? $modes[$mode] : [];
        $modeTemplateNames = is_array($modeConfig['templates'] ?? null) ? array_keys($modeConfig['templates']) : [];

        $targets = [];
        $modeRepoRelative = null;

        if (isset($modeRepositories[$mode]) && is_array($modeRepositories[$mode])) {
            $repoConfig = $modeRepositories[$mode];
            $repoKey = $this->stringValue($repoConfig['repo'] ?? null) ?? $mode;
            $url = $this->stringValue($repositories[$repoKey] ?? null)
                ?? $this->stringValue($repoConfig['repo'] ?? null);
            $path = $this->stringValue($repoConfig['path'] ?? null) ?? $mode;

            if ($url !== null && array_intersect($templates, $modeTemplateNames) !== []) {
                $modeRepoRelative = 'template/' . trim($path, '/');
                $targets[$modeRepoRelative] = [
                    'name' => ($this->stringValue($modeConfig['label'] ?? null) ?? $mode) . ' 前端仓库',
                    'url' => $url,
                    'dir' => $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $modeRepoRelative),
                    'relative' => $modeRepoRelative,
                    'subdir' => null,
                    'repo_key' => $repoKey,
                ];
            }
        }

        foreach ($templates as $template) {
            if ($modeRepoRelative !== null && in_array($template, $modeTemplateNames, true)) {
                continue;
            }

            $path = $resolver->templatePath($template, $mode);
            if ($path === '') {
                $io->warning("No path configured for template '{$template}', skipped");
                continue;
            }

            $canonical = $resolver->canonicalTemplate($template);
            $meta = $resolver->templateMeta($canonical);
            $name = $this->stringValue($meta['label'] ?? null) ?? $canonical;
            if ($canonical === 'admin' && $mode === 'separated') {
                $variantConfig = $resolver->adminVariants()[$variant] ?? [];
                $repoKey = $this->stringValue($variantConfig['repo'] ?? null) ?? 'single';
                $url = $this->stringValue($repositories[$repoKey] ?? null)
                    ?? $this->stringValue($variantConfig['repo'] ?? null)
                    ?? $this->stringValue($repositories['single'] ?? null);
                $name = $this->stringValue($variantConfig['label'] ?? null) ?? $name;
            } else {
                $repoKey = $canonical;
                $url = $this->stringValue($repositories[$repoKey] ?? null);
            }
            if ($url === null) {
                $io->warning("No repository configured for template '{$template}', skipped");
                continue;
            }

            $relative = $this->relativePath($projectRoot, $path);
            $targets[$relative] = [
                'name' => $name,
                'url' => $url,
                'dir' => $path,
                'relative' => $relative,
                'subdir' => $this->stringValue($archiveSubdirs[$repoKey] ?? null),
                'repo_key' => $repoKey,
            ];
        }

        if ($withSkills) {
            $skillsUrl = $this->stringValue($repositories['skills'] ?? null);
            $skillsRelative = $this->stringValue($downloadConfig['extra']['skills'] ?? null) ?? 'skills';
            if ($skillsUrl !== null) {
                $targets[$skillsRelative] = [
                    'name' => 'Skills',
                    'url' => $skillsUrl,
                    'dir' => $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $skillsRelative),
                    'relative' => $skillsRelative,
                    'subdir' => null,
                    'repo_key' => 'skills',
                ];
            } else {
                $io->warning('No repository configured for skills, skipped');
            }
        }

        return array_values($targets);
    }

    private function relativePath(string $root, string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($root !== '' && str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }
        return ltrim($path, '/');
    }

    /**
     * 根据镜像源生成仓库归档 ZIP 地址
     */
    private function archiveUrl(string $repoUrl, string $mirrorName, string $branch, array $downloadConfig): ?string
    {
        $templates = $downloadConfig['archive_templates'] ?? [];
        $host = parse_url($repoUrl, PHP_URL_HOST);
        $template = is_array($templates) && is_string($host) ? ($templates[$host] ?? null) : null;
        if (!is_string($template) || $template === '') {
            return null;
        }

        $path = preg_replace('#^https?://[^/]+/+#', '', $repoUrl) ?? '';
        $path = preg_replace('#\.git$#', '', $path) ?? '';
        if ($path === '') {
            return null;
        }

        return str_replace(['{path}', '{branch}'], [$path, $branch], $template);
    }

    /**
     * 下载 ZIP 归档并解压到目标目录（自动去掉归档顶层目录，可只提取子目录）
     */
    private function downloadAndExtractZip(string $url, string $targetDir, SymfonyStyle $io, ?string $subdir = null, ?string $repoKey = null): bool
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'madong_zip_');
        if ($tmpFile === false) {
            $io->error('Cannot create temporary ZIP file');
            return false;
        }

        // 优先用 curl.exe：PHP 流包装器在部分 Windows 环境下无法访问 HTTPS
        $curlOutput = [];
        $curlCode = 1;
        $curlCommand = 'curl.exe -L --fail --silent --show-error --max-time 300 -o '
            . escapeshellarg($tmpFile) . ' ' . escapeshellarg($url) . ' 2>&1';
        exec($curlCommand, $curlOutput, $curlCode);
        if ($curlCode !== 0 || !is_file($tmpFile) || filesize($tmpFile) === 0) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 300,
                    'header' => "User-Agent: madong-download\r\n",
                ],
            ]);
            $data = @file_get_contents($url, false, $context);
            if ($data === false || file_put_contents($tmpFile, $data) === false) {
                @unlink($tmpFile);
                $io->error("Failed to download ZIP: {$url}");
                if ($curlOutput !== []) {
                    $io->error(implode("\n", $curlOutput));
                }
                return false;
            }
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            $io->error("Failed to open ZIP: {$url}");
            return false;
        }
        $prefix = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name)) {
                continue;
            }
            $pos = strpos($name, '/');
            if ($pos !== false && $pos > 0) {
                $prefix = substr($name, 0, $pos + 1);
                break;
            }
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            $zip->close();
            @unlink($tmpFile);
            $io->error("Cannot create directory: {$targetDir}");
            return false;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || $name === '') {
                continue;
            }
            if ($prefix !== null && str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
            }
            if ($subdir !== null && $subdir !== '') {
                $subdirPrefix = trim($subdir, '/') . '/';
                if (!str_starts_with($name, $subdirPrefix)) {
                    continue;
                }
                $name = substr($name, strlen($subdirPrefix));
            }
            if ($name === '' || preg_match('#(^|/)\.\.(/|$)#', $name) === 1) {
                continue;
            }

            $dest = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (str_ends_with($name, '/')) {
                if (!is_dir($dest)) {
                    mkdir($dest, 0755, true);
                }
                continue;
            }

            $parent = dirname($dest);
            if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }
            file_put_contents($dest, $content);
        }

        $zip->close();
        @unlink($tmpFile);
        $this->patchDownloadedTemplate($targetDir, $repoKey);
        return true;
    }

    /**
     * 修正上游模板归档中的已知构建问题
     */
    private function patchDownloadedTemplate(string $targetDir, ?string $repoKey): void
    {
        if (!in_array($repoKey, ['mono', 'single', 'aura', 'platform', 'web'], true)) {
            return;
        }

        if ($repoKey === 'web') {
            $this->patchWebTemplate($targetDir);
            return;
        }
        if ($repoKey === 'mono') {
            $this->patchMonoTemplate($targetDir);
        }

        foreach (['tooling/node-utils/package.json', 'internal/node-utils/package.json'] as $relativePath) {
            $nodeUtilsPackage = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (!is_file($nodeUtilsPackage)) {
                continue;
            }
            $content = file_get_contents($nodeUtilsPackage);
            if (is_string($content) && str_contains($content, 'node ./scripts/build.mjs')) {
                file_put_contents($nodeUtilsPackage, str_replace(
                    'node ./scripts/build.mjs',
                    'pnpm exec tsdown',
                    $content
                ));
            }
        }

        $rootPackage = $targetDir . DIRECTORY_SEPARATOR . 'package.json';
        if (is_file($rootPackage)) {
            $content = file_get_contents($rootPackage);
            if (is_string($content) && str_contains($content, 'is-ci || pnpm exec lefthook install')) {
                file_put_contents($rootPackage, str_replace(
                    'is-ci || pnpm exec lefthook install',
                    'is-ci || pnpm exec lefthook install || exit 0',
                    $content
                ));
            }
        }
    }

    /**
     * web 模板改为静态生成，并补出 index.html 供同域部署
     */
    private function patchWebTemplate(string $targetDir): void
    {
        $rootPackage = $targetDir . DIRECTORY_SEPARATOR . 'package.json';
        if (is_file($rootPackage)) {
            $content = file_get_contents($rootPackage);
            if (is_string($content) && str_contains($content, 'nuxi build && node scripts/materialize-output.mjs')) {
                file_put_contents($rootPackage, str_replace(
                    'nuxi build && node scripts/materialize-output.mjs',
                    'nuxi generate && node scripts/ensure-index.mjs',
                    $content
                ));
            }
        }

        $scriptDir = $targetDir . DIRECTORY_SEPARATOR . 'scripts';
        if (!is_dir($scriptDir) && !mkdir($scriptDir, 0755, true) && !is_dir($scriptDir)) {
            return;
        }
        file_put_contents($scriptDir . DIRECTORY_SEPARATOR . 'ensure-index.mjs', <<<'JS'
import { access, copyFile } from 'node:fs/promises'
import { join } from 'node:path'

const publicDir = join(process.cwd(), '.output', 'public')
const indexFile = join(publicDir, 'index.html')
const fallbackFile = join(publicDir, '200.html')

try {
  await access(indexFile)
} catch {
  await copyFile(fallbackFile, indexFile)
  console.log('[ensure-index] created index.html from 200.html')
}
JS);
    }

    /**
     * mono 的 lib 位于 packages/lib，补齐 admin 的 #lib 映射并直接复制 admin/lib 资源
     */
    private function patchMonoTemplate(string $targetDir): void
    {
        $filesystem = new Filesystem();

        $demoDir = $targetDir . DIRECTORY_SEPARATOR . 'apps/admin/src/plugin/demo';
        if (is_dir($demoDir)) {
            $filesystem->remove($demoDir);
        }

        $adminPackage = $targetDir . DIRECTORY_SEPARATOR . 'apps/admin/package.json';
        if (is_file($adminPackage)) {
            $content = file_get_contents($adminPackage);
            if (is_string($content) && str_contains($content, '"#/*": "./src/*"')) {
                file_put_contents($adminPackage, str_replace(
                    '"#/*": "./src/*"',
                    "\"#/*\": \"./src/*\",\n    \"#lib/*\": \"../../packages/lib/*\"",
                    $content
                ));
            }
        }

        $sourceLibDir = dirname($targetDir) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'lib';
        $targetLibDir = $targetDir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'lib';
        if (is_dir($sourceLibDir)) {
            $filesystem->mirror($sourceLibDir, $targetLibDir, null, ['override' => true]);
        }
    }

    /**
     * 执行 Git 命令并输出结果
     */
    /**
     * 通过 clone 下载：先克隆到项目根下的临时区，成功后替换到目标目录
     *
     * 直接 clone 到目标目录存在两个问题：失败的 clone 会留下半成品残骸阻断重试，
     * 且本机安全软件对刚写入的目录存在扫描锁定窗口（期间目录不可访问、无法删除）。
     * 因此 clone 落到临时区、等待锁定窗口结束后再替换，失败残骸留在临时区由 cleanup 重试清理。
     */
    private function downloadViaClone(array $target, string $projectRoot, string $branch, SymfonyStyle $io): bool
    {
        $targetDir = $target['dir'];
        // 临时区放项目根（同盘可 rename）：系统 TEMP 下安全软件对新 clone 目录扫描严格（期间完全不可访问），
        // 项目根下仅存在短暂锁定窗口（可读但删除受限），配合 waitForDirectory 等待窗口结束后替换并清理
        $tmpRoot = $projectRoot . DIRECTORY_SEPARATOR . '.template-tmp';
        if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0755, true) && !is_dir($tmpRoot)) {
            $io->error("Cannot create temp directory: {$tmpRoot}");
            return false;
        }
        $tmpName = basename($target['relative']) . '-' . uniqid();
        $tmpDir = $tmpRoot . DIRECTORY_SEPARATOR . $tmpName;

        $io->info("Cloning {$target['name']}...");
        $cloneCommand = "cd \"{$tmpRoot}\" && git %sclone -b {$branch} {$target['url']} {$tmpName}";
        if (!$this->execGit(sprintf($cloneCommand, $this->gitConfigOptions), $io)) {
            // 直连失败：清理半成品（临时区删除可靠）后用 git 全局代理重试
            $this->cleanupFailedClone($tmpDir);
            if ($this->gitConfigOptions === '' || !$this->execGit(sprintf($cloneCommand, ''), $io)) {
                $this->cleanupFailedClone($tmpDir);
                $io->error([
                    "Git clone failed for {$target['relative']} ({$target['url']}) via direct and proxy.",
                    "Options: start your proxy client and retry, run with --method=zip to switch to ZIP archive download,",
                    "or manually execute: git clone -b {$branch} {$target['url']} {$target['relative']}",
                ]);
                return false;
            }
        }

        // clone 成功后目录可能处于安全软件扫描锁定窗口（is_dir 短暂返回 false），等待其可访问后再替换
        if (!$this->waitForDirectory($tmpDir, $io)) {
            $io->error([
                "Cloned repository '{$tmpDir}' is not accessible (locked by antivirus scan?)",
                "Options: wait a moment and re-run, or manually execute: git clone -b {$branch} {$target['url']} {$target['relative']}",
            ]);
            return false;
        }

        // 替换目标目录（zip 产物、空残骸目录正常可删）
        $filesystem = new Filesystem();
        if (is_dir($targetDir)) {
            try {
                $filesystem->remove($targetDir);
            } catch (\Throwable $e) {
                $io->error([
                    "Clone succeeded but replacing '{$target['relative']}' failed: {$e->getMessage()}",
                    "Please delete '{$targetDir}' manually (use an elevated terminal if access is denied), then re-run.",
                ]);
                return false;
            }
        }
        if (!@rename($tmpDir, $targetDir)) {
            // 跨盘 rename 失败兜底：镜像复制后清理临时目录
            try {
                $filesystem->mirror($tmpDir, $targetDir);
            } catch (\Throwable $e) {
                $io->error("Failed to move cloned repository into place: {$e->getMessage()}");
                return false;
            }
            $this->cleanupFailedClone($tmpDir);
        }
        $io->success("Cloned to {$target['relative']}");
        return true;
    }

    /**
     * 判断目录是否为有效的 git 工作区（用于识别失败 clone 留下的半成品仓库）
     */
    private function isUsableRepo(string $targetDir): bool
    {
        $output = [];
        $returnVar = 0;
        exec('git -C "' . $targetDir . '" rev-parse --is-inside-work-tree', $output, $returnVar);
        return $returnVar === 0 && trim(implode('', $output)) === 'true';
    }

    /**
     * 等待目录变为可访问（安全软件对新写入目录存在扫描锁定窗口，期间 is_dir 返回 false）
     */
    private function waitForDirectory(string $dir, SymfonyStyle $io, int $maxSeconds = 120): bool
    {
        if (is_dir($dir)) {
            return true;
        }
        $io->text('Waiting for cloned repository to become accessible (antivirus scan lock)...');
        $deadline = time() + $maxSeconds;
        while (time() < $deadline) {
            sleep(2);
            if (is_dir($dir)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 清理 clone 失败残留的半成品目录（git clone 中断会留下非空目录，阻断重试 clone）
     *
     * 安全软件对新写入目录的扫描锁定会导致单次删除失败，带间隔重试直至窗口结束
     */
    private function cleanupFailedClone(string $targetDir): bool
    {
        if (!is_dir($targetDir)) {
            return true;
        }
        $filesystem = new Filesystem();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $filesystem->remove($targetDir);
            } catch (\Throwable) {
                // 锁定窗口内删除失败，等待后重试
            }
            if (!is_dir($targetDir)) {
                return true;
            }
            sleep(3);
        }
        return false;
    }

    /**
     * 执行 Git 命令并输出结果
     */
    private function execGit(string $command, SymfonyStyle $io): bool
    {
        // gitConfigOptions 已由调用方内嵌到 git 命令本身（cd && git <options> clone/pull），
        // 不能前置到整条 shell 命令开头，否则 cmd 会把 -c 当作可执行程序名
        $io->text("> {$command}");
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        foreach ($output as $line) {
            $io->text($line);
        }
        return $returnVar === 0;
    }

    private function isGitAvailable(): bool
    {
        $output = [];
        $returnVar = 0;
        exec('git --version', $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * 自动检测本机可用的 HTTP/HTTPS 代理
     *
     * 检测顺序：git 全局配置 → 环境变量 → 本地监听端口（Clash/V2Ray/mihomo 等）
     * 返回标准格式 "http://host:port"，未检测到返回 null
     */
    private function detectSystemProxy(): ?string
    {
        // 1) git 全局 http.proxy / https.proxy
        $output = [];
        $returnVar = 0;
        exec('git config --global --get http.proxy 2>NUL', $output, $returnVar);
        $value = trim(implode('', $output));
        if ($returnVar === 0 && $value !== '') {
            return $value;
        }

        // 2) 环境变量 HTTP_PROXY / HTTPS_PROXY / ALL_PROXY
        foreach (['HTTPS_PROXY', 'https_proxy', 'HTTP_PROXY', 'http_proxy', 'ALL_PROXY', 'all_proxy'] as $envKey) {
            $envVal = getenv($envKey);
            if ($envVal !== false && $envVal !== '') {
                return $envVal;
            }
        }

        // 3) 扫描本地常见代理端口（Clash 默认 7890、V2Ray 默认 1080/10808/10809、mihomo 20170、通用 3128/8080）
        $commonPorts = [7890, 7891, 10808, 10809, 1080, 20170, 3128, 8080, 1087];
        $isWindows = PHP_OS_FAMILY === 'Windows';
        foreach ($commonPorts as $port) {
            $listening = false;
            if ($isWindows) {
                // PowerShell: netstat -ano | findstr 127.0.0.1:PORT | findstr LISTENING
                $cmd = sprintf('netstat -ano 2>NUL | findstr "127.0.0.1:%d" | findstr LISTENING', $port);
                $out = [];
                exec($cmd, $out, $ret);
                $listening = $ret === 0;
            } else {
                $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
                if ($fp) {
                    fclose($fp);
                    $listening = true;
                }
            }
            if ($listening) {
                return "http://127.0.0.1:{$port}";
            }
        }

        return null;
    }
}
