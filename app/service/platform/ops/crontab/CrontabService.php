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
 * Official Website: https://core.tech
 */

namespace app\service\platform\ops\crontab;

use app\dao\ops\crontab\CrontabDao;
use app\dao\ops\crontab\CrontabLogDao;
use app\enum\system\OperationResult;
use app\enum\system\TaskScheduleCycle;
use app\service\admin\ops\crontab\CrontabLogService;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use core\infrastructure\scheduler\Client;
use Illuminate\Support\Collection;
use madong\helper\Arr;
use support\Container;

/**
 * 平台定时任务服务（超管视角）
 *
 * 平台作为超管视角管理全部定时任务（全局任务 + 各租户任务），并关联租户表：
 * - tenant_id 为 NULL 表示「全局」任务；
 * - tenant_id 有值表示归属于对应租户的任务（附带 tenant_name）。
 *
 * 调度器 getTaskAll 加载全部启用任务，其中 tenant_id 为 NULL 的全局任务由全局调度器接管执行。
 *
 * @author Mr.April
 * @since  1.0
 */
class CrontabService extends BaseService
{
    protected CrontabLogDao $crontabLogDao;

    public function __construct(CrontabDao $dao, CrontabLogDao $crontabLogDao)
    {
        $this->dao              = $dao;
        $this->crontabLogDao    = $crontabLogDao;
    }

    /**
     * 添加全局定时任务（tenant_id 恒为 NULL）
     *
     * @param array $data
     *
     * @return mixed
     * @throws \Throwable
     */
    public function save(array $data): mixed
    {
        try {
            $model = $this->transaction(function () use ($data) {
                $title      = $data['title'] ?? '';
                $type       = $data['type'] ?? '';
                $target     = $data['target'] ?? '';
                $status     = $data['enabled'] ?? 1;
                $singleton  = $data['singleton'] ?? 1;
                $task_cycle = TaskScheduleCycle::from($data['task_cycle'] ?? 1);
                $month      = $data['month'] ?? '';
                $week       = $data['week'] ?? '';
                $day        = $data['day'] ?? '';
                $hour       = $data['hour'] ?? '';
                $minute     = $data['minute'] ?? '';
                $second     = $data['second'] ?? '';
                $remark     = $data['remark'] ?? '';
                // 全局任务：tenant_id 恒为 NULL，不接受任何租户归属
                // 平台（超管）创建的定时任务默认归为「系统内置」（system）
                $source = $data['source'] ?? 'system';
                if (!empty($data['plugin_code'])) {
                    $source = 'plugin:' . $data['plugin_code'];
                }

                $this->validateTaskData($task_cycle, $minute, $hour, $day, $week, $month, $second);
                $rule = $this->generateCronRule($task_cycle, $minute, $hour, $day, $week, $month, $second);

                $insertData = [
                    'title'          => $title,
                    'type'           => $type,
                    'rule'           => $rule,
                    'target'         => $target,
                    'parameter'      => $data['parameter'] ?? null,
                    'enabled'        => $status,
                    'singleton'      => $singleton,
                    'task_cycle'     => $task_cycle,
                    'cycle_rule'     => [
                        'month'  => $month,
                        'week'   => $week,
                        'day'    => $day,
                        'hour'   => $hour,
                        'minute' => $minute,
                        'second' => $second,
                    ],
                    'remark'         => $remark,
                    'first_started'  => 0,
                    'source'         => $source,
                    'tenant_id'      => null,
                ];
                return $this->dao->save($insertData);
            });

            if (!empty($model)) {
                $pk = $model->getPk();
                $this->requestData($model->getData($pk));
            }
            return $model;
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 更新全局定时任务（tenant_id 强制保持 NULL）
     *
     * @param       $id
     * @param array $data
     *
     * @throws \Throwable
     */
    public function update($id, array $data): void
    {
        try {
            $this->transaction(function () use ($id, $data) {
                $title      = $data['title'] ?? '';
                $type       = $data['type'] ?? '';
                $target     = $data['target'] ?? '';
                $status     = $data['enabled'] ?? 1;
                $singleton  = $data['singleton'] ?? 1;
                $task_cycle = TaskScheduleCycle::from($data['task_cycle'] ?? 1);
                $month      = $data['month'] ?? '';
                $week       = $data['week'] ?? '';
                $day        = $data['day'] ?? '';
                $hour       = $data['hour'] ?? '';
                $minute     = $data['minute'] ?? '';
                $second     = $data['second'] ?? '';
                $remark     = $data['remark'] ?? '';
                // 平台编辑任务时保留原 tenant_id，避免把租户任务误改为全局任务
                $original   = $this->dao->get($id);
                $tenantId   = $original ? $original->getAttribute('tenant_id') : null;
                // 编辑时保留原来源，避免把 system/plugin:{插件名} 误覆盖为 custom；无原值兜底 system
                $source = $data['source'] ?? ($original ? $original->getAttribute('source') : 'system');
                if (!empty($data['plugin_code'])) {
                    $source = 'plugin:' . $data['plugin_code'];
                }

                $this->validateTaskData($task_cycle, $minute, $hour, $day, $week, $month, $second);
                $rule = $this->generateCronRule($task_cycle, $minute, $hour, $day, $week, $month, $second);

                $updateData = [
                    'title'          => $title,
                    'type'           => $type,
                    'rule'           => $rule,
                    'target'         => $target,
                    'parameter'      => $data['parameter'] ?? null,
                    'enabled'        => $status,
                    'singleton'      => $singleton,
                    'task_cycle'     => $task_cycle->value,
                    'cycle_rule'     => [
                        'month'  => $month,
                        'week'   => $week,
                        'day'    => $day,
                        'hour'   => $hour,
                        'minute' => $minute,
                        'second' => $second,
                    ],
                    'remark'         => $remark,
                    'source'         => $source,
                    'tenant_id'      => $tenantId,
                ];
                $this->dao->update($id, $updateData);
            });
            $this->requestData($id);
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 全局定时任务删除
     *
     * @param array|int|string $id
     *
     * @return bool
     * @throws \Throwable
     */
    public function destroy(array|int|string $id): bool
    {
        try {
            // 先关闭再删除,避免删了后直接连不上服务的情况出现
            $this->dao->update([['id', 'in', $id]], ['enabled' => 0]);
            $this->requestData($id);
            return $this->transaction(function () use ($id) {
                $this->dao->destroy($id);
                $systemCrontabLogService = Container::make(CrontabLogService::class);
                $systemCrontabLogService->deleteByCrontabId($id);
                return true;
            });
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 恢复任务
     *
     * @param string|int|array $data
     *
     * @throws \Throwable
     */
    public function resumeCrontab(string|int|array $data): void
    {
        try {
            $this->transaction(function () use ($data) {
                $this->query()->whereIn('id', Arr::normalize($data))->update(['enabled' => 1]);
                $result = $this->requestData($data);
                if (!$result) {
                    throw new AdminException('恢复失败');
                }
            });
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 暂停任务
     *
     * @param string|int|array $data
     *
     * @throws \Throwable
     */
    public function pauseCrontab(string|int|array $data): void
    {
        try {
            $this->transaction(function () use ($data) {
                $this->query()->whereIn('id', Arr::normalize($data))->update(['enabled' => 0]);
                $result = $this->requestData($data);
                if (!$result) {
                    throw new AdminException('重启失败');
                }
            });
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 获取所有任务（调度器使用：全量启用任务）
     *
     * @param string $field
     *
     * @return array
     * @throws \Exception
     */
    public function getTaskAll(string $field = '*'): array
    {
        return $this->dao->selectList(['enabled' => 1], $field, 0, 0, '', [], true)->toArray();
    }

    /**
     * 列表查询（平台超管视角：全局任务 + 所有租户任务，Eloquent with 关联租户）
     *
     * 平台作为超管视角展示全部定时任务，并通过 with 关联租户：
     * - tenant_id 为 NULL 表示「全局」任务，关联 tenant 为 null；
     * - tenant_id 有值表示归属于对应租户的任务，前端可渲染 tenant.name。
     *
     * @param array           $where
     * @param string|array    $field
     * @param int             $page
     * @param int             $limit
     * @param string          $order
     * @param array           $with
     * @param bool            $search
     *
     * @return \Illuminate\Database\Eloquent\Collection|null
     */
    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false): ?Collection
    {
        // 平台视角：展示全部任务（全局 + 各租户），不施加 tenant_id 限制。
        // Crud::index 硬编码 $with=[]，此处强制预加载租户关联供前端渲染名称（tenant?.name）。
        // 仅取 id/name 并排除 dbSetting，避免数据库配置等敏感信息泄露。
        $with['tenant'] = function ($query) {
            $query->without('dbSetting')->select('id', 'name');
        };
        return $this->dao->selectList($where, $field, $page, $limit, $order, $with, $search);
    }

    /**
     * 列表计数（平台全部任务口径）
     *
     * @param array $where
     *
     * @return int
     */
    public function getCount(array $where): int
    {
        // 平台视角：统计全部任务，不施加 tenant_id 限制
        return $this->dao->getCount($where);
    }

    /**
     * 传入ID 获取指定的一条任务
     *
     * @param $id
     *
     * @return mixed
     * @throws \Exception
     */
    public function getTask($id): mixed
    {
        return $this->dao->get($id);
    }

    /**
     * 运行单个任务
     *
     * @param $id
     *
     * @return array
     * @throws \Exception
     */
    public function runOneTask($id): array
    {
        /**  @var $task_handle EventBootstrap[] */
        $task_handle = config('core.infrastructure.scheduler.task_handle', []);
        $crontab     = $this->dao->get($id);
        $start_time  = microtime(true);
        try {
            if (empty($crontab)) {
                throw new \Exception('执行任务失败, 任务不存在');
            }

            if (!isset($task_handle[$crontab->getAttribute('type')])) {
                throw new \Exception('执行任务失败, 任务类型错误: ');
            }

            $result_data = $task_handle[$crontab['type']]::parse($crontab);
            // 记录执行信息
            $crontab->last_running_time = $start_time;

            $crontab->increment('running_times');
            if ($crontab->singleton == 0) {
                // 单次任务 直接停用
                $crontab->enabled = 0;
            }
            if (!$crontab->save()) {
                throw new \Exception('记录保存失败');
            }

            $end_time = microtime(true);
            $log      = $result_data['log'] ?? '';
            if (strlen($log) > 300) {
                $result_data['log'] = mb_substr($result_data['log'], 0, 300) . '...';
            }
            // 写入执行日志（全局任务 tenant_id 为 NULL）
            $installData       = [
                'crontab_id'   => $crontab['id'] ?? '',
                'target'       => $crontab['target'] ?? '',
                'log'          => $result_data['log'] ?? '--',
                'return_code'  => $result_data['code'] ?? OperationResult::FAILURE->value,
                'running_time' => round($end_time - $start_time, 6),
                'created_at'   => $start_time,
                'tenant_id'    => null,
                'source'       => $crontab['source'] ?? null,
            ];
            $crontabLogService = Container::make(CrontabLogService::class);
            $crontabLogModel   = $crontabLogService->saveLog($installData);
            if (empty($crontabLogModel)) {
                throw new \Exception('日志记录保存失败');
            }
        } catch (\Exception $e) {
            return ['code' => 1, 'log' => $e->getMessage() . '---任务id: ' . $id];
        }
        return [
            'code'       => $installData['return_code'],
            'log'        => $installData['log'],
            'crontab_id' => $installData['crontab_id'],
        ];
    }

    /**
     * 重启任务
     *
     * @param int|string|array $id_str int|string 需要重启的任务id,多个id用，拼接，例：1,2,3,4,5
     *
     * @return bool
     * @throws \Exception
     */
    public function requestData(int|string|array $id_str): bool
    {
        $ids = is_array($id_str) ? implode(',', $id_str) : $id_str;
        $param = ['method' => 'crontabReload', 'args' => ['id' => $ids]];
        return Client::request($param);
    }

    /**
     * 验证器
     *
     * @param \app\enum\system\TaskScheduleCycle $task_cycle
     * @param                                   $minute
     * @param                                   $hour
     * @param                                   $day
     * @param                                   $week
     * @param                                   $month
     * @param                                   $second
     *
     * @throws \Exception
     */
    private function validateTaskData(TaskScheduleCycle $task_cycle, $minute, $hour, $day, $week, $month, $second): void
    {
        switch ($task_cycle) {
            case TaskScheduleCycle::DAILY:
                $this->validateInteger($hour, "请输入执行小时", 23);
                $this->validateInteger($minute, "请输入执行分钟", 59);
                break;
            case TaskScheduleCycle::HOURLY:
                $this->validateInteger($minute, "请输入执行分钟", 59);
                break;
            case TaskScheduleCycle::WEEKLY:
                $this->validateInteger($week, "请输入星期几执行", 6);
                $this->validateInteger($hour, "请输入执行小时", 23);
                $this->validateInteger($minute, "请输入执行分钟", 59);
                break;
            case TaskScheduleCycle::MONTHLY:
                $this->validateInteger($day, "请输入执行天数", 31);
                $this->validateInteger($hour, "请输入执行小时", 23);
                $this->validateInteger($minute, "请输入执行分钟", 59);
                break;
            case TaskScheduleCycle::YEARLY:
                $this->validateInteger($month, "请输入执行月份", 12);
                $this->validateInteger($day, "请输入执行天数", 31);
                $this->validateInteger($hour, "请输入执行小时", 23);
                $this->validateInteger($minute, "请输入执行分钟", 59);
                break;
            case TaskScheduleCycle::N_HOURS:
                $this->validateInteger($hour, "请输入N小时", 23);
                break;
            case TaskScheduleCycle::N_MINUTES:
                $this->validateInteger($minute, "请输入N分钟", 59);
                break;
            case TaskScheduleCycle::N_SECONDS:
                $this->validateInteger($second, "请输入N秒数", 59);
                if (60 % (int)$second !== 0) {
                    throw new \Exception('秒级任务必须是60的因数');
                }
                break;
            default:
                throw new \Exception("任务周期不正确");
        }
    }

    /**
     * 定义验证规则器
     *
     * @throws \Exception
     */
    private function validateInteger($value, $message, $max): void
    {
        if (!is_numeric($value) || (int)$value < 0 || (int)$value > $max) {
            throw new \Exception($message);
        }
    }

    /**
     * 生成任务表达式
     *
     * @param \app\enum\system\TaskScheduleCycle $task_cycle
     * @param                                   $minute
     * @param                                   $hour
     * @param                                   $day
     * @param                                   $week
     * @param                                   $month
     * @param                                   $second
     *
     * @return string
     */
    private function generateCronRule(TaskScheduleCycle $task_cycle, $minute, $hour, $day, $week, $month, $second): string
    {
        return match ($task_cycle) {
            TaskScheduleCycle::DAILY => "0 {$minute} {$hour} * * *",
            TaskScheduleCycle::HOURLY => "0 {$minute} * * * *",
            TaskScheduleCycle::WEEKLY => "0 {$minute} {$hour} * * {$week}",
            TaskScheduleCycle::MONTHLY => "0 {$minute} {$hour} {$day} * *",
            TaskScheduleCycle::YEARLY => "0 {$minute} {$hour} {$day} {$month} *",
            TaskScheduleCycle::N_HOURS => "0 {$minute} */{$hour} * * *",
            TaskScheduleCycle::N_MINUTES => "0 */{$minute} * * * *",
            TaskScheduleCycle::N_SECONDS => "*/{$second} * * * * *",
        };
    }
}
