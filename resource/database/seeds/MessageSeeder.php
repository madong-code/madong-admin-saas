<?php

/**
 * 消息中心测试数据种子
 * 使用方法: php think seed:run --seed=MessageSeeder
 * 或直接通过数据库工具导入 SQL
 */

declare(strict_types=1);

namespace resource\database\seeds;

use app\model\content\message\Message;
use app\model\content\message\Subscribe;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        // 多租户模式（非 single）跳过租户业务数据
        if (config('tenant.enable', false)) {
            return;
        }
        return;

        // 获取第一个管理员用户作为消息收发人
        $adminId = '1';

        echo ">>> 消息中心测试数据导入开始...\n";

        // ==================== 1. 清理旧数据 ====================
        Message::where('receiver_id', $adminId)->delete();
        Subscribe::where('user_id', $adminId)->delete();

        // ==================== 2. 插入订阅配置 ====================
        $subscribeItems = [
            // 分类级订阅（module_key=null 表示整类订阅）
            ['user_id' => $adminId, 'category_key' => 'daily',      'module_key' => null,                'is_subscribed' => 1],
            ['user_id' => $adminId, 'category_key' => 'attendance', 'module_key' => null,                'is_subscribed' => 1],
            ['user_id' => $adminId, 'category_key' => 'approval',   'module_key' => null,                'is_subscribed' => 1],
            ['user_id' => $adminId, 'category_key' => 'system',     'module_key' => null,                'is_subscribed' => 1],
            // 模块级订阅
            ['user_id' => $adminId, 'category_key' => 'daily',      'module_key' => 'daily_notice',       'is_subscribed' => 1],
            ['user_id' => $adminId, 'category_key' => 'daily',      'module_key' => 'daily_news',         'is_subscribed' => 1],
            ['user_id' => $adminId, 'category_key' => 'attendance', 'module_key' => 'attendance_remind',   'is_subscribed' => 1],
            ['user_id' => $adminId, 'category_key' => 'attendance', 'module_key' => 'attendance_leave',    'is_subscribed' => 1],
            ['user_id' => $adminId, 'category_key' => 'approval',   'module_key' => 'approval_wait',      'is_subscribed' => 1],
            ['user_id' => $adminId, 'category_key' => 'approval',   'module_key' => 'approval_result',    'is_subscribed' => 1],
            ['user_id' => $adminId, 'category_key' => 'system',     'module_key' => 'system_upgrade',     'is_subscribed' => 1],
            ['user_id' => $adminId, 'category_key' => 'system',     'module_key' => 'system_maintenance', 'is_subscribed' => 1],
        ];
        foreach ($subscribeItems as $item) {
            $item['created_at'] = time();
            $item['updated_at'] = time();
            Subscribe::create($item);
        }
        echo '  订阅配置: ' . count($subscribeItems) . " 条\n";

        // ==================== 3. 插入消息 ====================
        $now = time();
        $messages = [
            // ---- 日常通知 (3 未读 + 1 已读) ----
            ['type'=>'notify','category_key'=>'daily','module_key'=>'daily_notice',  'title'=>'收到了 14 份新周报',          'content'=>'收到了 14 份新周报，请及时查收',                    'status'=>'unread','priority'=>1,'created_at'=>$now - 10800],
            ['type'=>'notify','category_key'=>'daily','module_key'=>'daily_news',    'title'=>'朱偏右 回复了你',            'content'=>'朱偏右 在项目讨论中回复了你',                        'status'=>'unread','priority'=>1,'created_at'=>$now - 3600],
            ['type'=>'notify','category_key'=>'daily','module_key'=>'daily_notice',  'title'=>'曲丽丽 评论了你',            'content'=>'曲丽丽 评论了你的工作日志',                          'status'=>'unread','priority'=>1,'created_at'=>$now - 86400],
            ['type'=>'notify','category_key'=>'daily','module_key'=>'daily_news',    'title'=>'系统公告：新版本上线',        'content'=>'系统已升级到 v3.2.0，请查看更新日志',               'status'=>'read',  'priority'=>2,'created_at'=>$now - 172800,'read_at'=>$now - 86400],

            // ---- 考勤打卡 (3 未读 + 1 已读) ----
            ['type'=>'notify','category_key'=>'attendance','module_key'=>'attendance_remind','title'=>'考勤异常提醒',     'content'=>'本月考勤异常汇总，请查看详情',                                          'status'=>'unread','priority'=>2,'created_at'=>$now - 172800],
            ['type'=>'notify','category_key'=>'attendance','module_key'=>'attendance_leave', 'title'=>'请假申请待审批',   'content'=>'张三提交了请假申请，请尽快审批',                                          'status'=>'unread','priority'=>3,'created_at'=>$now - 1800,'action_url'=>'/workspace'],
            ['type'=>'notify','category_key'=>'attendance','module_key'=>'attendance_leave', 'title'=>'加班申请待审批',   'content'=>'你有一条待审批的加班申请',                                                'status'=>'unread','priority'=>2,'created_at'=>$now - 86400],
            ['type'=>'notify','category_key'=>'attendance','module_key'=>'attendance_remind','title'=>'打卡提醒',         'content'=>'今日已完成打卡，继续加油',                                                'status'=>'read',  'priority'=>1,'created_at'=>$now - 28800,'read_at'=>$now - 25200],

            // ---- OA审批 (3 未读 + 1 已读) ----
            ['type'=>'notify','category_key'=>'approval','module_key'=>'approval_wait',  'title'=>'报销申请待审批',      'content'=>'李四提交了报销申请，金额 ¥2,500.00',                                     'status'=>'unread','priority'=>3,'created_at'=>$now - 7200,'action_url'=>'/workspace'],
            ['type'=>'notify','category_key'=>'approval','module_key'=>'approval_result','title'=>'审批通过通知',        'content'=>'你提交的请假申请已通过审批',                                              'status'=>'unread','priority'=>2,'created_at'=>$now - 18000],
            ['type'=>'notify','category_key'=>'approval','module_key'=>'approval_wait',  'title'=>'合同续费提醒',        'content'=>'合同 {#合同名称} {#续费类型} 今日到期，请立刻联系客户进行续费哦！',     'status'=>'unread','priority'=>3,'created_at'=>$now - 600],
            ['type'=>'notify','category_key'=>'approval','module_key'=>'approval_result','title'=>'审批驳回通知',        'content'=>'你提交的采购申请已被驳回，原因：预算不足',                                'status'=>'read',  'priority'=>2,'created_at'=>$now - 259200,'read_at'=>$now - 172800],

            // ---- 系统通知 (2 未读 + 1 已读) ----
            ['type'=>'notify','category_key'=>'system','module_key'=>'system_upgrade',     'title'=>'系统维护通知',     'content'=>'系统将于今晚 23:00 ~ 02:00 进行维护升级，期间服务不可用',                 'status'=>'unread','priority'=>1,'created_at'=>$now - 3600],
            ['type'=>'notify','category_key'=>'system','module_key'=>'system_maintenance', 'title'=>'存储空间告警',     'content'=>'服务器磁盘使用率已达 85%，请及时清理',                                   'status'=>'unread','priority'=>3,'created_at'=>$now - 21600],
            ['type'=>'notify','category_key'=>'system','module_key'=>'system_upgrade',     'title'=>'安全提醒',         'content'=>'您的账号有新设备登录，如非本人操作请立即修改密码',                        'status'=>'read',  'priority'=>3,'created_at'=>$now - 432000,'read_at'=>$now - 345600],
        ];

        foreach ($messages as $msg) {
            Message::create(array_merge([
                'sender_id'   => $adminId,
                'receiver_id' => $adminId,
                'updated_at'  => $msg['created_at'],
            ], $msg));
        }

        $unreadCount = count(array_filter($messages, fn($m) => $m['status'] === 'unread'));
        echo "  消息记录: " . count($messages) . " 条（未读 $unreadCount / 已读 " . (count($messages) - $unreadCount) . "）\n";
        echo ">>> 完成！\n";
    }
}
