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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webman\MiddlewareInterface;

/**
 * 完整性检查命令
 * 检查所有中间件、控制器、服务的一致性问题
 * 
 * 使用方法：
 * - 检查所有问题：php webman madong:check:integrity
 * - 只检查中间件：php webman madong:check:integrity --type=middleware
 * - 只检查控制器：php webman madong:check:integrity --type=controller
 * - 只检查命名空间：php webman madong:check:integrity --type=namespace
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong:check:integrity',
    description: 'Check middleware, controller, and service integrity',
    hidden: false
)]
class CheckIntegrityCommand extends BaseCommand
{
    /**
     * 检查类型
     */
    protected string $checkType = 'all';

    /**
     * 执行的命令
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Madong SaaS Integrity Checker');

        $issues = [];

        // 检查中间件
        if (in_array($this->checkType, ['all', 'middleware'])) {
            $io->section('Checking Middlewares...');
            $middlewareIssues = $this->checkMiddlewares($io);
            $issues = array_merge($issues, $middlewareIssues);
        }

        // 检查控制器
        if (in_array($this->checkType, ['all', 'controller'])) {
            $io->section('Checking Controllers...');
            $controllerIssues = $this->checkControllers($io);
            $issues = array_merge($issues, $controllerIssues);
        }

        // 检查命名空间
        if (in_array($this->checkType, ['all', 'namespace'])) {
            $io->section('Checking Namespace Consistency...');
            $namespaceIssues = $this->checkNamespaces($io);
            $issues = array_merge($issues, $namespaceIssues);
        }

        // 输出结果
        $io->newLine();
        if (!empty($issues)) {
            $io->error('Issues Found:');
            $io->table(
                ['Type', 'File', 'Line', 'Issue'],
                array_map(function ($issue) {
                    return [
                        $issue['type'],
                        $issue['file'],
                        $issue['line'] ?? 'N/A',
                        $issue['message'],
                    ];
                }, $issues)
            );
            
            $io->warning('Total issues: ' . count($issues));
            return Command::FAILURE;
        }

        $io->success('All integrity checks passed!');
        return Command::SUCCESS;
    }

    /**
     * 检查所有中间件
     */
    protected function checkMiddlewares(SymfonyStyle $io): array
    {
        $issues = [];
        $basePath = app_path();
        $middlewarePath = $basePath . DIRECTORY_SEPARATOR . 'middleware';

        if (!is_dir($middlewarePath)) {
            $io->warning('Middleware directory not found.');
            return $issues;
        }

        $files = $this->globRecursive($middlewarePath, '*.php');
        
        foreach ($files as $file) {
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file);

            // 跳过 Trait 文件（非独立中间件类）
            if (str_contains($relativePath, 'traits' . DIRECTORY_SEPARATOR) 
                || str_ends_with(basename($file, '.php'), 'Trait')) {
                continue;
            }

            $className = $this->pathToClassName($relativePath);
            
            if (!class_exists($className)) {
                require_once $file;
            }

            if (!class_exists($className)) {
                $issues[] = [
                    'type' => 'middleware',
                    'file' => $relativePath,
                    'line' => 1,
                    'message' => 'Class not found or cannot be loaded',
                ];
                $io->text("  ✗ {$relativePath}: Class not found");
                continue;
            }

            $reflection = new \ReflectionClass($className);
            
            // 检查是否实现了 MiddlewareInterface
            if (!$reflection->implementsInterface(MiddlewareInterface::class)) {
                $issues[] = [
                    'type' => 'middleware',
                    'file' => $relativePath,
                    'line' => $reflection->getStartLine(),
                    'message' => 'Does not implement MiddlewareInterface',
                ];
                $io->text("  ✗ {$relativePath}: Missing MiddlewareInterface");
                continue;
            }

            // 检查是否有 process 方法
            if (!$reflection->hasMethod('process')) {
                $issues[] = [
                    'type' => 'middleware',
                    'file' => $relativePath,
                    'line' => $reflection->getStartLine(),
                    'message' => 'Missing process() method',
                ];
                $io->text("  ✗ {$relativePath}: Missing process() method");
                continue;
            }

            $method = $reflection->getMethod('process');
            $params = $method->getParameters();
            
            // 检查参数数量
            if (count($params) < 2) {
                $issues[] = [
                    'type' => 'middleware',
                    'file' => $relativePath,
                    'line' => $method->getStartLine(),
                    'message' => 'process() method must have 2 parameters (request, handler)',
                ];
                $io->text("  ✗ {$relativePath}: process() must have 2 parameters");
            }

            $io->text("  ✓ {$relativePath}: OK");
        }

        return $issues;
    }

    /**
     * 检查所有控制器
     */
    protected function checkControllers(SymfonyStyle $io): array
    {
        $issues = [];
        $basePath = app_path();

        // 遍历所有控制器目录
        $controllerDirs = [
            'platformapi/controller',
            'adminapi/controller',
            'api/controller',
        ];

        foreach ($controllerDirs as $dir) {
            $controllerPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
            
            if (!is_dir($controllerPath)) {
                continue;
            }

            $files = $this->globRecursive($controllerPath, '*.php');
            
            foreach ($files as $file) {
                $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file);
                $className = $this->pathToClassName($relativePath);
                
                if (!class_exists($className)) {
                    require_once $file;
                }

                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new \ReflectionClass($className);
                $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

                foreach ($methods as $method) {
                    if ($method->getDeclaringClass()->getName() !== $className) {
                        continue;
                    }

                    $methodName = $method->getName();
                    if (strpos($methodName, '__') === 0) {
                        continue;
                    }

                    $params = $method->getParameters();
                    $paramNames = array_map(function ($p) {
                        return $p->getName();
                    }, $params);

                    // 读取方法体
                    $startLine = $method->getStartLine();
                    $endLine = $method->getEndLine();
                    $lines = file($file);
                    $methodBody = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

                    // 检查是否使用了 $request 但没有声明
                    if (preg_match('/\$request\b/', $methodBody) && !in_array('request', $paramNames)) {
                        $issues[] = [
                            'type' => 'controller',
                            'file' => $relativePath,
                            'line' => $startLine,
                            'message' => "Method '{$methodName}()' uses \$request but doesn't have it as parameter",
                        ];
                        $io->text("  ✗ {$relativePath}::{$methodName}(): Missing \$request parameter");
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * 检查命名空间一致性
     */
    protected function checkNamespaces(SymfonyStyle $io): array
    {
        $issues = [];
        $basePath = app_path();

        // 检查 service vs services 目录问题
        $serviceDir = $basePath . DIRECTORY_SEPARATOR . 'service';
        $servicesDir = $basePath . DIRECTORY_SEPARATOR . 'services';

        if (is_dir($servicesDir) && is_dir($serviceDir)) {
            $serviceFiles = $this->globRecursive($servicesDir, '*.php');
            foreach ($serviceFiles as $file) {
                $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file);
                $issues[] = [
                    'type' => 'namespace',
                    'file' => $relativePath,
                    'line' => 1,
                    'message' => 'Duplicate service directory found (app\services should be merged into app\service)',
                ];
                $io->text("  ⚠ {$relativePath}: Should be in app/service not app/services");
            }
        }

        // 检查 service 目录中的命名空间是否正确
        if (is_dir($serviceDir)) {
            $files = $this->globRecursive($serviceDir, '*.php');
            foreach ($files as $file) {
                $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file);
                $content = file_get_contents($file);
                
                // 检查命名空间
                if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+);/', $content, $matches)) {
                    $namespace = $matches[1];
                    $expectedNamespace = 'app' . str_replace([$basePath, 'service', DIRECTORY_SEPARATOR], 
                        ['', 'service', '\\'], 
                        pathinfo($file, PATHINFO_DIRNAME));
                    
                    if ($namespace !== $expectedNamespace && strpos($namespace, 'app\\service') === false) {
                        $issues[] = [
                            'type' => 'namespace',
                            'file' => $relativePath,
                            'line' => 1,
                            'message' => "Incorrect namespace '{$namespace}' (expected '{$expectedNamespace}')",
                        ];
                        $io->text("  ✗ {$relativePath}: Wrong namespace '{$namespace}'");
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * 递归搜索文件
     */
    protected function globRecursive(string $dir, string $pattern): array
    {
        $files = glob($dir . DIRECTORY_SEPARATOR . $pattern);
        $subdirs = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        
        foreach ($subdirs as $subdir) {
            if (strpos(basename($subdir), '.') !== 0) { // 跳过隐藏目录
                $files = array_merge($files, $this->globRecursive($subdir, $pattern));
            }
        }
        
        return $files;
    }

    /**
     * 路径转换为类名（自动补全 app\ 命名空间）
     */
    protected function pathToClassName(string $path): string
    {
        $path = str_replace(['.php', '/'], ['', '\\'], $path);
        $className = 'app\\' . ltrim($path, '\\');
        
        return $className;
    }
}
