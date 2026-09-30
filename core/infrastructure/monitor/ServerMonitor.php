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
namespace core\infrastructure\monitor;

/**
 * 系统监控服务类
 *
 * @author Mr.April
 * @since  1.0
 */
class ServerMonitor
{

    /**
     * 获取磁盘信息
     *
     * @return array
     */
    public function getDiskInfo(): array
    {
        $disk = [];
        if (stristr(PHP_OS, 'WIN')) {
            // Windows 系统 - 使用 PHP 原生函数
            foreach (range('A', 'Z') as $letter) {
                $drive = $letter . ':\\';
                if (is_dir($drive)) {
                    $total = @disk_total_space($drive);
                    $free  = @disk_free_space($drive);
                    if ($total !== false && $total > 0) {
                        $used = $total - ($free !== false ? $free : 0);
                        $disk[] = [
                            'filesystem'     => $letter . ':',
                            'size'           => $this->formatBytes($total),
                            'available'      => $this->formatBytes($free !== false ? $free : 0),
                            'used'           => $this->formatBytes($used),
                            'use_percentage' => sprintf('%.2f', $used / $total * 100) . '%',
                            'mounted_on'     => $letter . ':',
                        ];
                    }
                }
            }
        } else {
            // Linux 系统
            $diskInfo = shell_exec('df -h');
            $lines    = explode("\n", trim($diskInfo ?? ''));
            foreach ($lines as $line) {
                if (preg_match('/^\/dev\/\w+/', $line)) {
                    $parts  = preg_split('/\s+/', $line);
                    $disk[] = [
                        'filesystem'     => $parts[0],
                        'size'           => $parts[1],
                        'used'           => $parts[2],
                        'available'      => $parts[3],
                        'use_percentage' => $parts[4],
                        'mounted_on'     => $parts[5],
                    ];
                }
            }
        }
        return $disk;
    }

    /**
     * 获取cpu信息
     *
     * @return array
     */
    public function getCpuInfo(): array
    {
        try {
            if (PHP_OS == 'Linux') {
                $cpu = $this->getCpuUsage();
                preg_match('/(\d+)/', shell_exec('cat /proc/cpuinfo | grep "cache size"') ?? '', $cache);
                if (count($cache) == 0) {
                    // aarch64 有可能是arm架构
                    $cache = trim(shell_exec("lscpu | grep L3 | awk '{print \$NF}'") ?? '');
                    if ($cache == '') {
                        $cache = trim(shell_exec("lscpu | grep L2 | awk '{print \$NF}'") ?? '');
                    }
                    if ($cache != '') {
                        $cache = [0, intval(str_replace(['K', 'B'], '', strtoupper($cache)))];
                    }
                }
            } else {
                // Windows 系统 - 使用 PowerShell 一次性获取所有 CPU 信息
                $psOut  = shell_exec('powershell -NoProfile -Command "Get-CimInstance Win32_Processor | Select-Object Name, LoadPercentage, NumberOfCores, NumberOfLogicalProcessors, L2CacheSize, L3CacheSize | ConvertTo-Json"');
                $cpuRaw = json_decode($psOut ?? '', true);
                // 处理多 CPU 情况（数组）和单 CPU（对象）
                $cpuList = isset($cpuRaw[0]) ? $cpuRaw : [$cpuRaw];
                if (empty($cpuList[0]['Name'])) {
                    throw new \Exception('获取CPU信息失败');
                }
                $cpuName  = $cpuList[0]['Name'];
                $cpuUsage = 0;
                $cores    = 0;
                $logic    = 0;
                $l3       = 0;
                $l2       = 0;
                foreach ($cpuList as $c) {
                    $cpuUsage += intval($c['LoadPercentage'] ?? 0);
                    $cores    += intval($c['NumberOfCores'] ?? 0);
                    $logic    += intval($c['NumberOfLogicalProcessors'] ?? 0);
                    if (empty($l3)) $l3 = intval($c['L3CacheSize'] ?? 0);
                    if (empty($l2)) $l2 = intval($c['L2CacheSize'] ?? 0);
                }
                $count      = count($cpuList);
                $cpu        = round($cpuUsage / $count, 2);
                $cacheSize  = $l3 > 0 ? $l3 : $l2;
                $cache      = $cacheSize > 0 ? [0, $cacheSize * 1024] : '';
                $physCores  = strval($cores);
                $logiCores  = strval($logic);
            }
            return [
                'cpu_name'             => PHP_OS == 'Linux' ? $this->getCpuName() : $cpuName,
                'physical_cores'       => PHP_OS == 'Linux' ? $this->getCpuPhysicsCores() : $physCores,
                'logical_cores'        => PHP_OS == 'Linux' ? $this->getCpuLogicCores() : $logiCores,
                'cache_size_mb'        => $cache[1] ? $cache[1] / 1024 : 0, // 缓存大小（MB）
                'cpu_usage_percentage' => $cpu, // CPU 使用率（%）
                'free_cpu_percentage'  => round(100 - $cpu, 2), // 可用 CPU 百分比（%）
            ];
        } catch (\Exception $e) {
            return [
                'cpu_name'             => '获取失败',
                'physical_cores'       => '获取失败',
                'logical_cores'        => '获取失败',
                'cache_size_mb'        => '获取失败',
                'cpu_usage_percentage' => '获取失败',
                'free_cpu_percentage'  => '获取失败',
            ];
        }
    }

    /**
     * 获取内存信息
     *
     * @return array
     */
    public function getMemoryInfo(): array
    {
        $result = [];
        if (stristr(PHP_OS, 'WIN')) {
            // Windows 系统 - 使用 PowerShell
            $psOut = shell_exec('powershell -NoProfile -Command "Get-CimInstance Win32_OperatingSystem | Select-Object TotalVisibleMemorySize, FreePhysicalMemory | ConvertTo-Json"');
            $memData = json_decode($psOut ?? '', true);
            if (is_array($memData) && isset($memData['TotalVisibleMemorySize'])) {
                $totalMemKb                    = intval($memData['TotalVisibleMemorySize']);
                $freeMemKb                     = intval($memData['FreePhysicalMemory']);
                $result['total_memory']        = round($totalMemKb / 1024 / 1024, 2); // KB -> GB
                $result['available_memory']    = round($freeMemKb / 1024, 2); // KB -> MB（与前兼容）
                $result['used_memory']         = round($result['total_memory'] - $freeMemKb / 1024 / 1024, 2);
            } else {
                $result['total_memory']     = 0;
                $result['available_memory'] = 0;
                $result['used_memory']      = 0;
            }
            $result['php_memory_usage'] = round(memory_get_usage(true) / 1024 / 1024, 2); // PHP 使用内存（MB）
        } else {
            // Linux 系统
            $memInfo      = shell_exec('cat /proc/meminfo');
            $memInfoArray = array_filter(array_map('trim', explode("\n", $memInfo ?? '')));

            $total = $available = 0;

            foreach ($memInfoArray as $line) {
                if (str_starts_with($line, 'MemTotal:')) {
                    preg_match('/(\d+)/', $line, $matches);
                    $total = intval($matches[1]);
                }
                if (str_starts_with($line, 'MemAvailable:')) {
                    preg_match('/(\d+)/', $line, $matches);
                    $available = intval($matches[1]);
                }
            }
            $result['total_memory']     = sprintf('%.2f', $total / 1024 / 1024); // 总内存（GB）
            $result['available_memory'] = sprintf('%.2f', $available / 1024 / 1024); // 可用内存（GB）
            $result['used_memory']      = sprintf('%.2f', ($total - $available) / 1024 / 1024); // 使用内存（GB）
            $result['php_memory_usage'] = round(memory_get_usage(true) / 1024 / 1024, 2); // PHP 使用内存（MB）
        }
        // 计算内存使用率
        $result['memory_usage_rate'] = $result['total_memory'] > 0
            ? sprintf('%.2f', ($result['used_memory'] / $result['total_memory']) * 100)
            : '0.00';

        return $result;
    }

    /**
     * 获取Redis详情
     *
     * @return array
     * @throws \RedisException
     */
    public function getRedisInfo(): array
    {
        $config = config('redis.default');
        $redis  = new \Redis();
        $redis->connect($config['host'], (int) $config['port']);
        if (!empty($config['password'])) {
            $redis->auth($config['password']);
        }
        $info = $redis->info(); // 获取 Redis 监控信息

        return [
            'uptime_in_seconds'          => $info['uptime_in_seconds'], // 运行时间（秒）
            'connected_clients'          => $info['connected_clients'], // 连接的客户端数量
            'used_memory'                => $this->formatBytes($info['used_memory']), // 使用的内存
            'memory_fragmentation_ratio' => $info['mem_fragmentation_ratio'], // 内存碎片比率
            'total_commands_processed'   => $info['total_commands_processed'], // 处理的命令总数
            'total_connections_received' => $info['total_connections_received'], // 接收的连接总数
            'keyspace_hits'              => $info['keyspace_hits'], // 键空间命中次数
            'keyspace_misses'            => $info['keyspace_misses'], // 键空间未命中次数
            'hit_rate'                   => $this->calculateHitRate($info), // 命中率
            'variable'                   => $info ?? [],
            'data'                       => $this->getRedisKeysAndValues($redis),
        ];
    }

    /**
     * 获取PHP环境信息
     *
     * @return array
     */
    public function getPhpInfo(): array
    {
        return [
            'php_version'         => PHP_VERSION,
            'os'                  => PHP_OS,
            'project_path'        => BASE_PATH,
            'memory_limit'        => ini_get('memory_limit'),
            'max_execution_time'  => ini_get('max_execution_time'),
            'error_reporting'     => ini_get('error_reporting'),
            'display_errors'      => ini_get('display_errors'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size'       => ini_get('post_max_size'),
            'extension_dir'       => ini_get('extension_dir'),
            'loaded_extensions'   => implode(', ', get_loaded_extensions()),
        ];
    }

    /**
     * 扫描redis数据
     *
     * @param $redis
     *
     * @return array
     */
    private function getRedisKeysAndValues($redis): array
    {

        $keyValues = [];
        $iterator  = null;
        do {
            $keys = $redis->scan($iterator);
            foreach ($keys as $key) {
                $keyValues[$key] = $redis->get($key);
            }
        } while ($iterator > 0);
        return $keyValues;
    }

    /**
     * 获取CPU名称
     *
     * @return string
     */
    private function getCpuName(): string
    {
        if (PHP_OS == 'Linux') {
            preg_match('/^\s+\d\s+(.+)/', shell_exec('cat /proc/cpuinfo | grep name | cut -f2 -d: | uniq -c') ?? '', $matches);
            if (count($matches) == 0) {
                // aarch64 有可能是arm架构
                $name = trim(shell_exec("lscpu| grep Architecture | awk '{print $2}'") ?? '');
                if ($name != '') {
                    $mfMhz = trim(shell_exec("lscpu| grep 'MHz' | awk '{print \$NF}' | head -n1") ?? '');
                    $mfGhz = trim(shell_exec("lscpu| grep 'GHz' | awk '{print \$NF}' | head -n1") ?? '');
                    if ($mfMhz == '' && $mfGhz == '') {
                        return $name;
                    } else if ($mfGhz != '') {
                        return $name . ' @ ' . $mfGhz . 'GHz';
                    } else if ($mfMhz != '') {
                        return $name . ' @ ' . round(intval($mfMhz) / 1000, 2) . 'GHz';
                    }
                } else {
                    return '未知';
                }
            }
            return $matches[1] ?? "未知";
        } else {
            $psOut = shell_exec('powershell -NoProfile -Command "Get-CimInstance Win32_Processor | Select-Object Name | ConvertTo-Json"');
            $data  = json_decode($psOut ?? '', true);
            $list  = isset($data[0]) ? $data : [$data];
            return trim($list[0]['Name'] ?? '未知');
        }
    }

    /**
     * 获取cpu物理核心数
     *
     * @return string
     */
    private function getCpuPhysicsCores(): string
    {
        if (PHP_OS == 'Linux') {
            $num = str_replace("\n", '', shell_exec('cat /proc/cpuinfo |grep "physical id"|sort |uniq|wc -l') ?? '');
            return intval($num) == 0 ? '1' : $num;
        } else {
            $num = shell_exec('powershell -NoProfile -Command "Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfCores -Sum | Select-Object -ExpandProperty Sum"');
            return trim($num ?? '1') ?: '1';
        }
    }

    /**
     * 获取cpu逻辑核心数
     *
     * @return string
     */
    private function getCpuLogicCores(): string
    {
        if (PHP_OS == 'Linux') {
            return str_replace("\n", '', shell_exec('cat /proc/cpuinfo |grep "processor"|wc -l') ?? '');
        } else {
            $num = shell_exec('powershell -NoProfile -Command "Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfLogicalProcessors -Sum | Select-Object -ExpandProperty Sum"');
            return trim($num ?? '1') ?: '1';
        }
    }

    /**
     * 获取CPU使用率
     *
     * @return string
     */
    private function getCpuUsage(): string
    {
        $start = $this->calculationCpu();
        sleep(1);
        $end = $this->calculationCpu();

        $totalStart = $start['total'];
        $totalEnd   = $end['total'];

        $timeStart = $start['time'];
        $timeEnd   = $end['time'];

        return sprintf('%.2f', ($timeEnd - $timeStart) / ($totalEnd - $totalStart) * 100);
    }

    /**
     * 计算CPU
     *
     * @return array
     */
    private function calculationCpu(): array
    {
        $mode   = '/(cpu)[\s]+([0-9]+)[\s]+([0-9]+)[\s]+([0-9]+)[\s]+([0-9]+)[\s]+([0-9]+)[\s]+([0-9]+)[\s]+([0-9]+)[\s]+([0-9]+)/';
        $string = shell_exec('cat /proc/stat | grep cpu');
        preg_match_all($mode, $string ?? '', $matches);

        $total = $matches[2][0] + $matches[3][0] + $matches[4][0] + $matches[5][0] + $matches[6][0] + $matches[7][0] + $matches[8][0] + $matches[9][0];
        $time  = $matches[2][0] + $matches[3][0] + $matches[4][0] + $matches[6][0] + $matches[7][0] + $matches[8][0] + $matches[9][0];

        return ['total' => $total, 'time' => $time];
    }

    /**
     * 格式化字节可读格式
     *
     * @param $bytes
     * @param $decimals
     *
     * @return string
     */
    private function formatBytes($bytes, $decimals = 2): string
    {
        $units  = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$units[$factor];
    }

    /**
     * 计算Redis命中率
     *
     * @param array $info
     *
     * @return float
     */
    private function calculateHitRate(array $info): float
    {
        if ($info['keyspace_hits'] + $info['keyspace_misses'] == 0) {
            return 0; // 避免除以零
        }
        return round(($info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses'])) * 100, 2); // 命中率（%）
    }

}
