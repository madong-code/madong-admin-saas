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

namespace app\model\tenant;

use core\foundation\base\SystemModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 数据库设置模型
 *
 * @property int    $id          ID
 * @property string $database    数据库名称
 * @property string $host        数据库主机
 * @property int    $port        端口
 * @property string $username    用户名
 * @property string $password    密码
 * @property string $prefix      表前缀
 * @property string $driver      驱动类型 (mysql/pgsql/sqlite)
 * @property int    $enabled     是否启用
 * @property string $description 描述
 * @property int    $create_time 创建时间
 * @property int    $update_time 更新时间
 * @property int    $delete_time 删除时间
 */
class DbSetting extends SystemModel
{
    use SoftDeletes;

    /**
     * 数据库表名
     *
     * @var string
     */
    protected $table = 'saas_db_setting';

    /**
     * 主键自增（与迁移 $table->id() 一致）
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * 主键类型
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * 主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 自动类型转换
     *
     * @var array
     */
    protected $casts = [
        'port'              => 'integer',
        'enabled'           => 'integer',
        'is_default'        => 'boolean',
        'last_test_result'  => 'boolean',
        'extra_config'      => 'array',
        'last_test_time'    => 'integer',
        'sort'              => 'integer',
    ];

    /**
     * 软删除字段名
     *
     * @var string
     */
    const DELETED_AT = 'deleted_at';

    /**
     * 隐藏字段（列表/详情接口不返回）
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    /**
     * 可填充字段
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'database',
        'host',
        'port',
        'username',
        'password',
        'prefix',
        'driver',
        'charset',
        'collation',
        'enabled',
        'is_default',
        'sort',
        'description',
        'extra_config',
    ];

    /**
     * 驱动类型常量
     */
    const DRIVER_MYSQL = 'mysql';
    const DRIVER_PGSQL = 'pgsql';
    const DRIVER_SQLITE = 'sqlite';

    /**
     * 驱动映射
     *
     * @var array
     */
    public static $driverMap = [
        self::DRIVER_MYSQL  => 'MySQL',
        self::DRIVER_PGSQL  => 'PostgreSQL',
        self::DRIVER_SQLITE => 'SQLite',
    ];

    /**
     * 默认端口映射
     *
     * @var array
     */
    public static $defaultPorts = [
        self::DRIVER_MYSQL  => 3306,
        self::DRIVER_PGSQL  => 5432,
        self::DRIVER_SQLITE => 0,
    ];

    /**
     * 获取驱动文本
     *
     * @param string $driver
     *
     * @return string
     */
    public function getDriverText(string $driver): string
    {
        return self::$driverMap[$driver] ?? $driver;
    }

    /**
     * 获取默认端口
     *
     * @param string $driver
     *
     * @return int
     */
    public static function getDefaultPort(string $driver): int
    {
        return self::$defaultPorts[$driver] ?? 3306;
    }

    /**
     * 获取启用的数据源列表
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getEnabledList()
    {
        return self::where('enabled', 1)->get();
    }

    /**
     * 根据数据库名查找
     *
     * @param string $database
     *
     * @return static|null
     */
    public static function findByDatabase(string $database): ?self
    {
        return self::where('database', $database)->first();
    }

    /**
     * 检查连接是否有效
     *
     * @return bool
     */
    public function checkConnection(): bool
    {
        try {
            if ($this->driver === self::DRIVER_SQLITE) {
                return file_exists($this->database);
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->host,
                $this->port,
                $this->database
            );

            $pdo = new \PDO(
                $dsn,
                $this->username,
                $this->password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * 获取配置数组（用于数据库连接）
     *
     * @return array
     */
    public function toConfig(): array
    {
        return [
            'driver'    => $this->driver ?? self::DRIVER_MYSQL,
            'host'      => $this->host ?? '127.0.0.1',
            'port'      => (int)($this->port ?? 3306),
            'database'  => $this->database,
            'username'  => $this->username,
            'password'  => $this->password ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix'    => $this->prefix ?? '',
            'strict'    => true,
            'engine'    => 'InnoDB',
        ];
    }
}
