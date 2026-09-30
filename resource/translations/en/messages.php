<?php
/**
 *+------------------
 * madong - System Default Language Package
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

return [
    // User Management
    'user'         => [
        'title'                  => 'User Management',
        'not_exist'              => 'User does not exist',
        'create_success'         => 'User created successfully',
        'create_fail'            => 'User creation failed',
        'update_success'         => 'User updated successfully',
        'update_fail'            => 'User update failed',
        'delete_success'         => 'User deleted successfully',
        'delete_fail'            => 'User deletion failed',
        'enable_success'         => 'User enabled successfully',
        'enable_fail'            => 'User enable failed',
        'disable_success'        => 'User disabled successfully',
        'disable_fail'           => 'User disable failed',
        'lock_success'           => 'User locked successfully',
        'lock_fail'              => 'User lock failed',
        'unlock_success'         => 'User unlocked successfully',
        'unlock_fail'            => 'User unlock failed',
        'password_reset_success' => 'Password reset successfully',
        'password_reset_fail'    => 'Password reset failed',
        'username_exist'         => 'Username already exists',
        'email_exist'            => 'Email already exists',
        'mobile_exist'           => 'Mobile number already exists',
        'admin_not_editable'     => 'Super admin cannot be edited',
        'admin_not_deletable'    => 'Super admin cannot be deleted',
    ],

    // Role Management
    'role'         => [
        'title'                    => 'Role Management',
        'not_exist'                => 'Role does not exist',
        'create_success'           => 'Role created successfully',
        'create_fail'              => 'Role creation failed',
        'update_success'           => 'Role updated successfully',
        'update_fail'              => 'Role update failed',
        'delete_success'           => 'Role deleted successfully',
        'delete_fail'              => 'Role deletion failed',
        'assign_success'           => 'Role assigned successfully',
        'assign_fail'              => 'Role assignment failed',
        'admin_role_not_editable'  => 'Admin role cannot be edited',
        'admin_role_not_deletable' => 'Admin role cannot be deleted',
        'in_use'                   => 'Role is in use, cannot be deleted',
        'permission'               => [
            'assign_success' => 'Permission assigned successfully',
            'assign_fail'    => 'Permission assignment failed',
            'update_success' => 'Permission updated successfully',
            'update_fail'    => 'Permission update failed',
        ],
    ],

    // Menu Management
    'menu'         => [
        'title'          => 'Menu Management',
        'not_exist'      => 'Menu does not exist',
        'create_success' => 'Menu created successfully',
        'create_fail'    => 'Menu creation failed',
        'update_success' => 'Menu updated successfully',
        'update_fail'    => 'Menu update failed',
        'delete_success' => 'Menu deleted successfully',
        'delete_fail'    => 'Menu deletion failed',
        'move_success'   => 'Menu moved successfully',
        'move_fail'      => 'Menu move failed',
        'has_children'   => 'Has submenus, cannot be deleted',
        'type'           => [
            'directory' => 'Directory',
            'menu'      => 'Menu',
            'button'    => 'Button',
        ],
        'status'         => [
            'enabled'  => 'Enabled',
            'disabled' => 'Disabled',
        ],
    ],

    // Data Dictionary
    'dict'         => [
        'title'          => 'Data Dictionary',
        'not_exist'      => 'Dictionary does not exist',
        'create_success' => 'Dictionary created successfully',
        'create_fail'    => 'Dictionary creation failed',
        'update_success' => 'Dictionary updated successfully',
        'update_fail'    => 'Dictionary update failed',
        'delete_success' => 'Dictionary deleted successfully',
        'delete_fail'    => 'Dictionary deletion failed',
        'item'           => [
            'not_exist'      => 'Dictionary item does not exist',
            'create_success' => 'Dictionary item created successfully',
            'create_fail'    => 'Dictionary item creation failed',
            'update_success' => 'Dictionary item updated successfully',
            'update_fail'    => 'Dictionary item update failed',
            'delete_success' => 'Dictionary item deleted successfully',
            'delete_fail'    => 'Dictionary item deletion failed',
        ],
    ],

    // Department Management
    'dept'         => [
        'title'          => 'Department Management',
        'not_exist'      => 'Department does not exist',
        'create_success' => 'Department created successfully',
        'create_fail'    => 'Department creation failed',
        'update_success' => 'Department updated successfully',
        'update_fail'    => 'Department update failed',
        'delete_success' => 'Department deleted successfully',
        'delete_fail'    => 'Department deletion failed',
        'has_children'   => 'Has subdepartments, cannot be deleted',
        'has_users'      => 'Has users, cannot be deleted',
    ],

    // Post Management
    'post'         => [
        'title'          => 'Post Management',
        'not_exist'      => 'Post does not exist',
        'create_success' => 'Post created successfully',
        'create_fail'    => 'Post creation failed',
        'update_success' => 'Post updated successfully',
        'update_fail'    => 'Post update failed',
        'delete_success' => 'Post deleted successfully',
        'delete_fail'    => 'Post deletion failed',
        'has_users'      => 'Has users, cannot be deleted',
    ],

    // System Configuration
    'config'       => [
        'title'          => 'System Configuration',
        'not_exist'      => 'Configuration does not exist',
        'update_success' => 'Configuration updated successfully',
        'update_fail'    => 'Configuration update failed',
        'group'          => [
            'system'   => 'System Settings',
            'security' => 'Security Settings',
            'mail'     => 'Mail Settings',
            'sms'      => 'SMS Settings',
            'storage'  => 'Storage Settings',
        ],
        'cache_cleared'  => 'Configuration cache cleared',
    ],

    // Recycle Bin
    'recycle'      => [
        'title'           => 'Recycle Bin',
        'not_exist'       => 'Recycle item does not exist',
        'recycle_success' => 'Data recycled successfully',
        'recycle_fail'    => 'Data recycle failed',
        'restore_success' => 'Data restored successfully',
        'restore_fail'    => 'Data restore failed',
        'delete_success'  => 'Data permanently deleted successfully',
        'delete_fail'     => 'Data permanent deletion failed',
        'empty_success'   => 'Recycle bin emptied successfully',
        'empty_fail'      => 'Recycle bin empty failed',
        'type'            => [
            'user'       => 'User',
            'role'       => 'Role',
            'menu'       => 'Menu',
            'dept'       => 'Department',
            'post'       => 'Post',
            'dict'       => 'Dictionary',
            'attachment' => 'Attachment',
        ],
    ],

    // Operate Log
    'operate_log'  => [
        'title'          => 'Operate Log',
        'not_exist'      => 'Log does not exist',
        'clear_success'  => 'Log cleared successfully',
        'clear_fail'     => 'Log clear failed',
        'export_success' => 'Log exported successfully',
        'export_fail'    => 'Log export failed',
        'type'           => [
            'add'      => 'Add',
            'edit'     => 'Edit',
            'delete'   => 'Delete',
            'login'    => 'Login',
            'logout'   => 'Logout',
            'upload'   => 'Upload',
            'download' => 'Download',
        ],
        'result'         => [
            'success' => 'Success',
            'fail'    => 'Fail',
        ],
    ],

    // Login Log
    'login_log'    => [
        'title'          => 'Login Log',
        'not_exist'      => 'Log does not exist',
        'clear_success'  => 'Log cleared successfully',
        'clear_fail'     => 'Log clear failed',
        'export_success' => 'Log exported successfully',
        'export_fail'    => 'Log export failed',
        'status'         => [
            'success' => 'Success',
            'fail'    => 'Fail',
        ],
        'type'           => [
            'web'         => 'Web Login',
            'mobile'      => 'Mobile Login',
            'api'         => 'API Login',
            'third_party' => 'Third Party Login',
        ],
    ],

    // Crontab Task
    'crontab'      => [
        'title'          => 'Crontab Task',
        'not_exist'      => 'Task does not exist',
        'create_success' => 'Task created successfully',
        'create_fail'    => 'Task creation failed',
        'update_success' => 'Task updated successfully',
        'update_fail'    => 'Task update failed',
        'delete_success' => 'Task deleted successfully',
        'delete_fail'    => 'Task deletion failed',
        'start_success'  => 'Task started successfully',
        'start_fail'     => 'Task start failed',
        'stop_success'   => 'Task stopped successfully',
        'stop_fail'      => 'Task stop failed',
        'run_success'    => 'Task executed successfully',
        'run_fail'       => 'Task execution failed',
        'status'         => [
            'enabled'  => 'Enabled',
            'disabled' => 'Disabled',
            'running'  => 'Running',
        ],
        'type'           => [
            'command'  => 'Command',
            'callback' => 'Callback',
            'url'      => 'URL Request',
        ],
        'log'            => [
            'title'         => 'Task Log',
            'not_exist'     => 'Log does not exist',
            'clear_success' => 'Log cleared successfully',
            'clear_fail'    => 'Log clear failed',
        ],
    ],
    // Message Management
    'message'      => [
        'title'          => 'Message Management',
        'not_exist'      => 'Message does not exist',
        'create_success' => 'Message created successfully',
        'create_fail'    => 'Message creation failed',
        'update_success' => 'Message updated successfully',
        'update_fail'    => 'Message update failed',
        'delete_success' => 'Message deleted successfully',
        'delete_fail'    => 'Message deletion failed',
        'send_success'   => 'Message sent successfully',
        'send_fail'      => 'Message sending failed',
        'read_success'   => 'Message marked as read',
        'type'           => [
            'system'       => 'System Message',
            'notification' => 'Notification',
            'private'      => 'Private Message',
        ],
        'status'         => [
            'unread'  => 'Unread',
            'read'    => 'Read',
            'deleted' => 'Deleted',
        ],
    ],

    // Notification Management
    'notification' => [
        'title'           => 'Notification Management',
        'not_exist'       => 'Notification does not exist',
        'create_success'  => 'Notification created successfully',
        'create_fail'     => 'Notification creation failed',
        'update_success'  => 'Notification updated successfully',
        'update_fail'     => 'Notification update failed',
        'delete_success'  => 'Notification deleted successfully',
        'delete_fail'     => 'Notification deletion failed',
        'publish_success' => 'Notification published successfully',
        'publish_fail'    => 'Notification publishing failed',
        'type'            => [
            'system'       => 'System Notification',
            'announcement' => 'Announcement',
            'reminder'     => 'Reminder',
        ],
        'status'          => [
            'draft'     => 'Draft',
            'published' => 'Published',
            'expired'   => 'Expired',
        ],
    ],

    // Attachment Management
    'attachment'   => [
        'title'            => 'Attachment Management',
        'not_exist'        => 'Attachment does not exist',
        'upload_success'   => 'Attachment uploaded successfully',
        'upload_fail'      => 'Attachment upload failed',
        'download_success' => 'Attachment downloaded successfully',
        'download_fail'    => 'Attachment download failed',
        'delete_success'   => 'Attachment deleted successfully',
        'delete_fail'      => 'Attachment deletion failed',
        'type'             => [
            'image'    => 'Image',
            'document' => 'Document',
            'video'    => 'Video',
            'audio'    => 'Audio',
            'other'    => 'Other',
        ],
        'size_exceed'      => 'File size exceeds limit',
        'type_not_allowed' => 'File type not allowed',
    ],
    'plugin'       => [
        'error' => [
            'repeat_install'      => 'Plugin is already installed...',
            'not_uninstall'       => 'Plugin is not installed...',
            'info_file_not_exist' => 'Plugin info.json file not found',
            'dependency_missing'  => 'Dependency plugin :name is missing',
            'config_invalid'      => 'Plugin config file is invalid',
        ],
    ],

];