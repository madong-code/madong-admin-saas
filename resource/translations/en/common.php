<?php

return [
    // 操作反馈（成功/失败/确认）
    'operation' => [
        'success'  => 'Success',
        'fail'   => 'Failed',
        'save'     => [
            'success' => 'Saved successfully',
            'fail'  => 'Save failed',
            'confirm' => 'Confirm save?',
        ],
        'delete'   => [
            'success' => 'Deleted successfully',
            'fail'  => 'Delete failed',
            'confirm' => 'Confirm delete?',
        ],
        'update'   => [
            'success' => 'Updated successfully',
            'fail'  => 'Update failed',
        ],
        'submit'   => [
            'success' => 'Submitted successfully',
            'fail'  => 'Submit failed',
            'confirm' => 'Confirm submit?',
        ],
        'upload'   => [
            'success'      => 'Uploaded successfully',
            'fail'       => 'Upload failed',
            'too_large'    => 'File too large',
            'invalid_type' => 'Invalid file type',
        ],
        'download' => [
            'success' => 'Downloaded successfully',
            'fail'  => 'Download failed',
        ],
    ],

    // 通用交互动作（按钮/导航/数据操作）
    'action'    => [
        'confirm'  => 'Confirm',
        'ensure'   => 'Ensure',
        'yes'      => 'Yes',
        'ok'       => 'OK',
        'no'       => 'No',
        'cancel'   => 'Cancel',
        'abort'    => 'Abort',
        'back'     => 'Back',
        'return'   => 'Return',
        'next'     => 'Next',
        'continue' => 'Continue',
        'previous' => 'Previous',
        'home'     => 'Home',
        'save'     => 'Save',
        'store'    => 'Store',
        'edit'     => 'Edit',
        'modify'   => 'Modify',
        'update'   => 'Update',
        'refresh'  => 'Refresh',
        'reset'    => 'Reset',
        'clear'    => 'Clear',
        'add'      => 'Add',
        'create'   => 'Create',
        'delete'   => 'Delete',
        'remove'   => 'Remove',
        'view'     => 'View',
        'detail'   => 'Detail',
        'search'   => 'Search',
        'filter'   => 'Filter',
        'import'   => 'Import',
        'export'   => 'Export',
        'print'    => 'Print',
        'help'     => 'Help',
        'settings' => 'Settings',
        'close'    => 'Close',
        'exit'     => 'Exit',
    ],

    // 状态提示（加载/错误/成功等）
    'status'    => [
        'loading'           => 'Loading...',
        'processing'        => 'Processing...',
        'please_wait'       => 'Please wait...',
        'success'           => 'Success',
        'completed'         => 'Completed',
        'failure'           => 'Failure',
        'fail'            => 'Failed',
        'error'             => 'Error',
        'warning'           => 'Warning',
        'info'              => 'Information',
        'no_data'           => 'No data available',
        'permission_denied' => 'Permission denied',
        'login_expired'     => 'Login expired',
        'network_error'     => 'Network connection error', // 原网络错误（泛化）
        'server_error'      => 'Internal server error', // 原服务器错误（泛化）
        'request_timeout'   => 'Request timed out', // 原请求超时（泛化）
    ],

    // 网络异常（HTTP 状态码 + 具体网络错误）
    'http'      => [
        // 状态码（语义化键名 + 数字键名双映射）
        'bad_request_400'           => 'Bad Request',
        '400'                       => 'Bad Request',
        'unauthorized_401'          => 'Unauthorized',
        '401'                       => 'Unauthorized',
        'forbidden_403'             => 'Forbidden',
        '403'                       => 'Forbidden',
        'not_found_404'             => 'Not Found',
        '404'                       => 'Not Found',
        'method_not_allowed_405'    => 'Method Not Allowed',
        '405'                       => 'Method Not Allowed',
        'request_timeout_408'       => 'Request Timeout',
        '408'                       => 'Request Timeout',
        'too_many_requests_429'     => 'Too Many Requests',
        '429'                       => 'Too Many Requests',
        'internal_server_error_500' => 'Internal Server Error',
        '500'                       => 'Internal Server Error',
        'bad_gateway_502'           => 'Bad Gateway',
        '502'                       => 'Bad Gateway',
        'service_unavailable_503'   => 'Service Unavailable',
        '503'                       => 'Service Unavailable',
        'gateway_timeout_504'       => 'Gateway Timeout',
        '504'                       => 'Gateway Timeout',

        // 网络异常描述（非状态码）
        'connection_refused'        => 'Connection refused',
        'dns_lookup_failed'         => 'DNS lookup failed',
        'ssl_handshake_failed'      => 'SSL handshake failed',
        'resource_unavailable'      => 'Resource temporarily unavailable',
    ],

    // 基础界面元素
    'ui'        => [
        'dashboard'    => 'Dashboard',
        'profile'      => 'Profile',
        'account'      => 'Account',
        'notification' => 'Notification',
        'message'      => 'Message',
        'log'          => 'Log',
        'history'      => 'History',
        'about'        => 'About',
        'contact'      => 'Contact',
        'language'     => 'Language',
        'theme'        => 'Theme',
        'version'      => 'Version',
        'copyright'    => 'Copyright',
    ],

    // 插件管理
    'plugin'    => [
        'error' => [
            'repeat_install'       => 'Plugin is already installed, cannot reinstall',
            'not_uninstall'        => 'Plugin is not installed, cannot uninstall',
            'info_file_not_exist'  => 'Plugin :name info.json file not found',
            'dependency_missing'   => 'Missing dependency plugin :name',
            'config_invalid'       => 'Plugin config file is invalid',
            'dir_not_exist'        => 'Plugin :name directory not found',
            'version_not_support'  => 'Plugin :name does not support current framework version',
            'sql_fail'             => 'Plugin database operation failed',
            'not_uninstall_delete' => 'Plugin :name is not uninstalled, cannot be deleted',
        ],
    ],
];