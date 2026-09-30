<?php

return [
    'enabled' => true,
    'npm_package_manager' => 'pnpm',
    'package_managers' => [
        'npm' => [
            'name' => 'NPM',
            'install' => 'npm install',
            'build' => 'npm run build',
            'check' => 'npm --version',
        ],
        'cnpm' => [
            'name' => 'CNPM',
            'install' => 'cnpm install',
            'build' => 'cnpm run build',
            'check' => 'cnpm --version',
        ],
        'pnpm' => [
            'name' => 'PNPM',
            'install' => 'pnpm install',
            'build' => 'pnpm run build',
            'check' => 'pnpm --version',
        ],
        'yarn' => [
            'name' => 'YARN',
            'install' => 'yarn install',
            'build' => 'yarn build',
            'check' => 'yarn --version',
        ],
    ],
    'commands' => [
        'test' => [
            'default' => [
                'cwd' => '{project_root}/template/mono/apps/admin',
                'command' => 'pnpm run test',
                'description' => '运行测试命令',
            ],
        ],
        'install' => [
            'admin' => [
                'cwd' => '{project_root}/template/mono/apps/admin',
                'command' => '{package_manager} install',
                'description' => '安装Admin前端依赖',
                'variables' => [
                    '{package_manager}' => 'npm_package_manager',
                ],
            ],
            'platform' => [
                'cwd' => '{project_root}/template/mono/apps/platform',
                'command' => '{package_manager} install',
                'description' => '安装Platform前端依赖',
                'variables' => [
                    '{package_manager}' => 'npm_package_manager',
                ],
            ],
            'web' => [
                'cwd' => '{project_root}/template/web',
                'command' => '{package_manager} install',
                'description' => '安装Web前端依赖',
                'variables' => [
                    '{package_manager}' => 'npm_package_manager',
                ],
            ],
            'h5' => [
                'cwd' => '{project_root}/template/uni-app',
                'command' => '{package_manager} install',
                'description' => '安装H5前端依赖',
                'variables' => [
                    '{package_manager}' => 'npm_package_manager',
                ],
            ],
            'app' => [
                'cwd' => '{project_root}/template/uni-app',
                'command' => '{package_manager} install',
                'description' => '安装App前端依赖',
                'variables' => [
                    '{package_manager}' => 'npm_package_manager',
                ],
            ],
            'server' => [
                'cwd' => '{backend_root}',
                'command' => 'composer install',
                'description' => '安装Server后端依赖',
            ],
        ],
        'build' => [
            'admin' => [
                'cwd' => '{project_root}/template/mono/apps/admin',
                'command' => '{package_manager} run build:integrated',
                'description' => '构建Admin前端',
                'variables' => [
                    '{package_manager}' => 'npm_package_manager',
                ],
                'before' => [
                ],
                'after' => [
                ],
            ],
            'platform' => [
                'cwd' => '{project_root}/template/mono/apps/platform',
                'command' => '{package_manager} run build:integrated',
                'description' => '构建Platform前端',
                'variables' => [
                    '{package_manager}' => 'npm_package_manager',
                ],
                'before' => [
                ],
                'after' => [
                ],
            ],
            'web' => [
                'cwd' => '{project_root}/template/web',
                'command' => '{package_manager} run build:integrated',
                'description' => '构建Web前端',
                'variables' => [
                    '{package_manager}' => 'npm_package_manager',
                ],
                'before' => [
                ],
                'after' => [
                ],
            ],
            'h5' => [
                'cwd' => '{project_root}/template/uni-app',
                'command' => '{package_manager} run build:h5',
                'description' => '构建H5前端',
                'variables' => [
                    '{package_manager}' => 'npm_package_manager',
                ],
                'before' => [
                ],
                'after' => [
                ],
            ],
            'app' => [
                'cwd' => '{project_root}/template/uni-app',
                'command' => '{package_manager} run build:app',
                'description' => '构建App前端',
                'variables' => [
                    '{package_manager}' => 'npm_package_manager',
                ],
            ],
        ],
        'debug' => [
            'default' => [
                'cwd' => '{backend_root}',
                'command' => 'echo "Debug command executed"',
                'description' => '调试命令',
                'before' => [
                    'class' => 'app\service\core\terminal\intercept\CommonIntercept',
                    'method' => 'before',
                ],
                'after' => [
                    'class' => 'app\service\core\terminal\intercept\CommonIntercept',
                    'method' => 'after',
                ],
            ],
        ],
        'custom' => [
            'test' => [
                'cwd' => '{backend_root}',
                'command' => 'echo "Hello, World!"',
                'description' => '测试自定义命令',
                'before' => [
                    'class' => 'app\service\core\terminal\intercept\CustomIntercept',
                    'method' => 'before',
                ],
                'after' => [
                    'class' => 'app\service\core\terminal\intercept\CustomIntercept',
                    'method' => 'after',
                ],
            ],
            'list-files' => [
                'cwd' => '{backend_root}',
                'command' => 'dir',
                'description' => '列出当前目录文件',
                'before' => [
                    'class' => 'app\service\core\terminal\intercept\CustomIntercept',
                    'method' => 'before',
                ],
                'after' => [
                    'class' => 'app\service\core\terminal\intercept\CustomIntercept',
                    'method' => 'after',
                ],
            ],
        ],
        'check' => [
            'node' => [
                'cwd' => '{backend_root}',
                'command' => 'node --version',
                'description' => '检查Node.js版本',
            ],
            'npm' => [
                'cwd' => '{backend_root}',
                'command' => 'npm --version',
                'description' => '检查NPM版本',
            ],
            'pnpm' => [
                'cwd' => '{backend_root}',
                'command' => 'pnpm --version',
                'description' => '检查PNPM版本',
            ],
            'composer' => [
                'cwd' => '{backend_root}',
                'command' => 'composer --version',
                'description' => '检查Composer版本',
            ],
        ],
    ],
    'execution' => [
        'output_dir' => '{backend_root}/runtime/install/terminal',
        'output_file' => 'exec.log',
        'timeout' => 300,
        'poll_interval' => 500000,
    ],
    'frontend_programs' => [
        'admin' => [
            'enabled' => true,
            'source_dir' => '{project_root}/template/mono/apps/admin/dist',
            'target_dir' => '{backend_root}/public/admin',
            'copy_mappings' => [
                '*' => '.',
            ],
            'clean_target' => true,
            'preserve_files' => [
                0 => '.gitkeep',
            ],
            'copy_options' => [
                'recursive' => true,
                'overwrite' => true,
                'preserve_permissions' => true,
            ],
        ],
        'platform' => [
            'enabled' => true,
            'source_dir' => '{project_root}/template/mono/apps/platform/dist',
            'target_dir' => '{backend_root}/public/platform',
            'copy_mappings' => [
                '*' => '.',
            ],
            'clean_target' => true,
            'preserve_files' => [
                0 => '.gitkeep',
            ],
            'copy_options' => [
                'recursive' => true,
                'overwrite' => true,
                'preserve_permissions' => true,
            ],
        ],
        'web' => [
            'enabled' => true,
            'source_dir' => '{project_root}/template/web/.output/public',
            'target_dir' => '{backend_root}/public/web',
            'copy_mappings' => [
                '*' => '.',
            ],
            'clean_target' => true,
            'preserve_files' => [
                0 => '.gitkeep',
            ],
            'copy_options' => [
                'recursive' => true,
                'overwrite' => true,
                'preserve_permissions' => true,
            ],
        ],
        'h5' => [
            'enabled' => true,
            'source_dir' => '{project_root}/template/uni-app/dist/build/h5',
            'target_dir' => '{backend_root}/public/h5',
            'copy_mappings' => [
                '*' => '.',
            ],
            'clean_target' => true,
            'preserve_files' => [
                0 => '.gitkeep',
            ],
            'copy_options' => [
                'recursive' => true,
                'overwrite' => true,
                'preserve_permissions' => true,
            ],
        ],
        'app' => [
            'enabled' => true,
            'source_dir' => '{project_root}/template/uni-app/dist/build/app',
            'target_dir' => '{backend_root}/public/app',
            'copy_mappings' => [
                '*' => '.',
            ],
            'clean_target' => true,
            'preserve_files' => [
                0 => '.gitkeep',
            ],
            'copy_options' => [
                'recursive' => true,
                'overwrite' => true,
                'preserve_permissions' => true,
            ],
        ],
        'uni-app' => [
            'enabled' => true,
            'platforms' => [
                'h5' => [
                    'source_dir' => '{project_root}/template/uni-app/dist/build/h5',
                    'target_dir' => '{backend_root}/public/uni-app/h5',
                    'copy_mappings' => [
                        '*' => '.',
                    ],
                ],
                'mp-weixin' => [
                    'source_dir' => '{project_root}/template/uni-app/dist/build/mp-weixin',
                    'target_dir' => '{backend_root}/public/uni-app/mp-weixin',
                    'copy_mappings' => [
                        '*' => '.',
                    ],
                ],
                'app-plus' => [
                    'source_dir' => '{project_root}/template/uni-app/dist/build/app-plus',
                    'target_dir' => '{backend_root}/public/uni-app/app-plus',
                    'copy_mappings' => [
                        '*' => '.',
                    ],
                ],
            ],
            'default_platform' => 'h5',
            'clean_target' => true,
            'preserve_files' => [
                0 => '.gitkeep',
            ],
            'copy_options' => [
                'recursive' => true,
                'overwrite' => true,
                'preserve_permissions' => true,
            ],
        ],
    ],
    'registries' => [
        'npm' => [
            'npm' => 'https://registry.npmjs.org/',
            'taobao' => 'https://registry.npmmirror.com/',
            'tencent' => 'https://mirrors.cloud.tencent.com/npm/',
            'huawei' => 'https://repo.huaweicloud.com/repository/npm/',
            'aliyun' => 'https://registry.npmmirror.com/',
            'current' => 'npm',
        ],
        'composer' => [
            'composer' => 'https://packagist.org/',
            'huawei' => 'https://repo.huaweicloud.com/repository/packagist/',
            'aliyun' => 'https://mirrors.aliyun.com/composer/',
            'tencent' => 'https://mirrors.cloud.tencent.com/composer/',
            'current' => 'composer',
        ],
    ],
    'web' => [
        'command_groups' => [
            0 => [
                'id' => 'test',
                'name' => '测试',
                'icon' => 'ant-design:code-outlined',
                'commands' => [
                    0 => [
                        'key' => 'check.node',
                        'name' => '检查Node.js版本',
                    ],
                    1 => [
                        'key' => 'check.npm',
                        'name' => '检查NPM版本',
                    ],
                    2 => [
                        'key' => 'check.pnpm',
                        'name' => '检查PNPM版本',
                    ],
                    3 => [
                        'key' => 'check.composer',
                        'name' => '检查Composer版本',
                    ],
                ],
            ],
            1 => [
                'id' => 'install',
                'name' => '安装依赖',
                'icon' => 'ant-design:download-outlined',
                'commands' => [
                    0 => [
                        'key' => 'install.admin',
                        'name' => '安装Admin前端依赖',
                    ],
                    1 => [
                        'key' => 'install.platform',
                        'name' => '安装Platform前端依赖',
                    ],
                    2 => [
                        'key' => 'install.web',
                        'name' => '安装Web前端依赖',
                    ],
                    3 => [
                        'key' => 'install.h5',
                        'name' => '安装H5前端依赖',
                    ],
                    4 => [
                        'key' => 'install.app',
                        'name' => '安装App前端依赖',
                    ],
                    5 => [
                        'key' => 'install.server',
                        'name' => '安装Server后端依赖',
                    ],
                ],
            ],
            2 => [
                'id' => 'build',
                'name' => '重新发布',
                'icon' => 'ant-design:tag-outlined',
                'commands' => [
                    0 => [
                        'key' => 'build.admin',
                        'name' => '构建Admin前端',
                    ],
                    1 => [
                        'key' => 'build.platform',
                        'name' => '构建Platform前端',
                    ],
                    2 => [
                        'key' => 'build.web',
                        'name' => '构建Web前端',
                    ],
                    3 => [
                        'key' => 'build.h5',
                        'name' => '构建H5前端',
                    ],
                    4 => [
                        'key' => 'build.app',
                        'name' => '构建App前端',
                    ],
                ],
            ],
            3 => [
                'id' => 'custom',
                'name' => '自定义命令',
                'icon' => 'ant-design:code-square-outlined',
                'commands' => [
                    0 => [
                        'key' => 'custom.test',
                        'name' => '测试命令',
                    ],
                    1 => [
                        'key' => 'custom.list-files',
                        'name' => '列出文件',
                    ],
                ],
            ],
        ],
    ],
];
