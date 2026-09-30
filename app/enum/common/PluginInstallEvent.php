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
namespace app\enum\common;

use core\foundation\interface\IEnum;

enum PluginInstallEvent: string implements IEnum
{
    /**
     * 安装开始事件
     */
    case INSTALL_START = 'plugin-install-start';

    /**
     * 安装检查事件
     */
    case INSTALL_CHECK = 'plugin-install-check';

    /**
     * 备份前端目录事件
     */
    case BACKUP_FRONTEND = 'plugin-backup-frontend';

    /**
     * 执行SQL事件
     */
    case EXECUTE_SQL = 'plugin-execute-sql';

    /**
     * 安装菜单事件
     */
    case INSTALL_MENU = 'plugin-install-menu';

    /**
     * 合并依赖事件
     */
    case MERGE_DEPEND = 'plugin-merge-depend';

    /**
     * 执行插件安装方法事件
     */
    case EXECUTE_INSTALL_METHOD = 'plugin-execute-install-method';

    /**
     * 安装迁移文件事件
     */
    case INSTALL_MIGRATION_FILE = 'plugin-install-migration-file';

    /**
     * 安装完成事件
     */
    case INSTALL_COMPLETED = 'plugin-install-completed';

    /**
     * 安装失败事件
     */
    case INSTALL_FAILED = 'plugin-install-failed';

    /**
     * 卸载开始事件
     */
    case UNINSTALL_START = 'plugin-uninstall-start';

    /**
     * 卸载检查事件
     */
    case UNINSTALL_CHECK = 'plugin-uninstall-check';

    /**
     * 执行卸载SQL事件
     */
    case EXECUTE_UNINSTALL_SQL = 'plugin-execute-uninstall-sql';

    /**
     * 卸载菜单事件
     */
    case UNINSTALL_MENU = 'plugin-uninstall-menu';

    /**
     * 执行插件卸载方法事件
     */
    case EXECUTE_UNINSTALL_METHOD = 'plugin-execute-uninstall-method';

    /**
     * 卸载完成事件
     */
    case UNINSTALL_COMPLETED = 'plugin-uninstall-completed';

    /**
     * 卸载失败事件
     */
    case UNINSTALL_FAILED = 'plugin-uninstall-failed';

    /**
     * 获取事件显示文本
     */
    public function label(): string
    {
        return match ($this) {
            self::INSTALL_START => '插件安装开始',
            self::INSTALL_CHECK => '安装检查',
            self::BACKUP_FRONTEND => '备份前端目录',
            self::EXECUTE_SQL => '执行安装SQL',
            self::INSTALL_MENU => '安装菜单',
            self::MERGE_DEPEND => '合并插件依赖',
            self::EXECUTE_INSTALL_METHOD => '执行插件安装方法',
            self::INSTALL_COMPLETED => '插件安装完成',
            self::INSTALL_FAILED => '插件安装失败',
            self::INSTALL_MIGRATION_FILE => '安装迁移文件',
            self::UNINSTALL_START => '插件卸载开始',
            self::UNINSTALL_CHECK => '卸载检查',
            self::EXECUTE_UNINSTALL_SQL => '执行卸载SQL',
            self::UNINSTALL_MENU => '卸载菜单',
            self::EXECUTE_UNINSTALL_METHOD => '执行插件卸载方法',
            self::UNINSTALL_COMPLETED => '插件卸载完成',
            self::UNINSTALL_FAILED => '插件卸载失败',
        };
    }
}