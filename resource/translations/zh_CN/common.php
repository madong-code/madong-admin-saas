<?php

return [
    // 操作反馈（成功/失败/确认）
    'operation' => [
        'success'  => '成功',
        'fail'     => '失败',
        'save'     => [
            'success' => '保存成功',
            'fail'    => '保存失败',
            'confirm' => '确认保存吗？',
        ],
        'delete'   => [
            'success' => '删除成功',
            'fail'    => '删除失败',
            'confirm' => '确认删除吗？',
        ],
        'update'   => [
            'success' => '更新成功',
            'fail'    => '更新失败',
        ],
        'submit'   => [
            'success' => '提交成功',
            'fail'    => '提交失败',
            'confirm' => '确认提交吗？',
        ],
        'upload'   => [
            'success'      => '上传成功',
            'fail'         => '上传失败',
            'too_large'    => '文件过大',
            'invalid_type' => '无效文件类型',
        ],
        'download' => [
            'success' => '下载成功',
            'fail'    => '下载失败',
        ],
    ],

    // 通用交互动作（按钮/导航/数据操作）
    'action'    => [
        'confirm'  => '确认',
        'ensure'   => '确保',
        'yes'      => '是',
        'ok'       => '确定',
        'no'       => '否',
        'cancel'   => '取消',
        'abort'    => '中止',
        'back'     => '返回',
        'return'   => '返回上一页',
        'next'     => '下一步',
        'continue' => '继续',
        'previous' => '上一步',
        'home'     => '首页',
        'save'     => '保存',
        'store'    => '存储',
        'edit'     => '编辑',
        'modify'   => '修改',
        'update'   => '更新',
        'refresh'  => '刷新',
        'reset'    => '重置',
        'clear'    => '清除',
        'add'      => '添加',
        'create'   => '创建',
        'delete'   => '删除',
        'remove'   => '移除',
        'view'     => '查看',
        'detail'   => '详情',
        'search'   => '搜索',
        'filter'   => '筛选',
        'import'   => '导入',
        'export'   => '导出',
        'print'    => '打印',
        'help'     => '帮助',
        'settings' => '设置',
        'close'    => '关闭',
        'exit'     => '退出',
    ],

    // 状态提示（加载/错误/成功等）
    'status'    => [
        'loading'           => '加载中...',
        'processing'        => '处理中...',
        'please_wait'       => '请稍候...',
        'success'           => '成功',
        'completed'         => '已完成',
        'failure'           => '失败',
        'fail'              => '已失败',
        'error'             => '错误',
        'warning'           => '警告',
        'info'              => '信息',
        'no_data'           => '暂无数据',
        'permission_denied' => '权限不足',
        'login_expired'     => '登录已过期',
        'network_error'     => '网络连接错误', // 原网络错误（泛化）
        'server_error'      => '内部服务器错误', // 原服务器错误（泛化）
        'request_timeout'   => '请求超时', // 原请求超时（泛化）
    ],

    // 网络异常（HTTP 状态码 + 具体网络错误）
    'http'      => [
        // 状态码（语义化键名 + 数字键名双映射）
        'bad_request_400'           => '错误请求',
        '400'                       => '错误请求',
        'unauthorized_401'          => '未授权',
        '401'                       => '未授权',
        'forbidden_403'             => '禁止访问',
        '403'                       => '禁止访问',
        'not_found_404'             => '页面未找到',
        '404'                       => '页面未找到',
        'method_not_allowed_405'    => '方法不被允许',
        '405'                       => '方法不被允许',
        'request_timeout_408'       => '请求超时',
        '408'                       => '请求超时',
        'too_many_requests_429'     => '请求过于频繁',
        '429'                       => '请求过于频繁',
        'internal_server_error_500' => '服务器内部错误',
        '500'                       => '服务器内部错误',
        'bad_gateway_502'           => '网关错误',
        '502'                       => '网关错误',
        'service_unavailable_503'   => '服务不可用',
        '503'                       => '服务不可用',
        'gateway_timeout_504'       => '网关超时',
        '504'                       => '网关超时',

        // 网络异常描述（非状态码）
        'connection_refused'        => '连接被拒绝',
        'dns_lookup_failed'         => 'DNS 解析失败',
        'ssl_handshake_failed'      => 'SSL 握手失败',
        'resource_unavailable'      => '资源暂时不可用',
    ],

    // 基础界面元素（通用名词）
    'ui'        => [
        'dashboard'    => '仪表盘',
        'profile'      => '个人资料',
        'account'      => '账户',
        'notification' => '通知',
        'message'      => '消息',
        'log'          => '日志',
        'history'      => '历史记录',
        'about'        => '关于',
        'contact'      => '联系我们',
        'language'     => '语言',
        'theme'        => '主题',
        'version'      => '版本',
        'copyright'    => '版权所有',
    ],

    //插件管理
    'plugin'    => [
        'error' => [
            'repeat_install'       => '插件已安装,不能重复安装',       // 对应英文：Plugin is already installed...
            'not_uninstall'        => '当前插件未安装,不能进行卸载操作',     // 对应英文：Plugin is not installed...
            'info_file_not_exist'  => '未找到:name插件的info.json文件',   // 对应英文：Plugin info.json file not found
            'dependency_missing'   => '缺少依赖插件：:name',            // 补充：依赖插件缺失
            'config_invalid'       => '插件配置文件无效',                  // 补充：配置文件无效
            'dir_not_exist'        => ':name插件目录不存在',
            'version_not_support'  => ':name插件不支持当前框架版本',
            'sql_fail'             => '插件数据库操作失败',
            'not_uninstall_delete' => ':name插件未卸载不能进行删除操作',
        ],
    ],
];