# Madong-Saas 服务后端

Madong-Saas 极速后台开发框架 —— 多租户 SaaS 版本服务端，基于 [Webman](https://www.workerman.net/doc/webman) 常驻内存框架开发。

- 官网：<https://madong.tech>
- 下载中心（各端仓库 / 版本发布）：<https://madong.tech/download>
- 在线文档：<https://madong.tech> → 文档

## 相关仓库

本项目为 **服务后端（核心）**，配套前端、容器与工具仓库如下（均可在[下载中心](https://madong.tech/download)获取）：

| 端 | 说明 | Gitee | GitHub | GitCode |
|---|---|---|---|---|
| 服务后端（本仓库） | 多租户 SaaS 版本服务端，基于 Webman 常驻内存框架 | [madong-admin-saas](https://gitee.com/motion-code/madong-admin-saas) | [madong-admin-saas](https://github.com/madong-code/madong-admin-saas) | [madong-admin-saas](https://gitcode.com/motion-code/madong-admin-saas) |
| 平台管理 & 管理后台（前端） | 平台管理与租户管理后台前端（SPA） | [madong-multi](https://gitee.com/motion-code/madong-multi) | [madong-multi](https://github.com/madong-code/madong-multi) | [madong-multi](https://gitcode.com/motion-code/madong-multi) |
| 门户站点（前端） | 门户站点前端（Nuxt SSR） | [web-nuxt](https://gitee.com/motion-code/web-nuxt) | [web-nuxt](https://github.com/madong-code/web-nuxt) | [web-nuxt](https://gitcode.com/motion-code/web-nuxt) |
| AI 技能包 | 配套 AI Skills 技能包 | [madong-admin-saas-skills](https://gitee.com/motion-code/madong-admin-saas-skills) | [madong-admin-saas-skills](https://github.com/madong-code/madong-admin-saas-skills) | [madong-admin-saas-skills](https://gitcode.com/motion-code/madong-admin-saas-skills) |
| 容器环境 | Webman 官方 Docker 运行环境镜像，开箱即用 | [docker-webman](https://gitee.com/motion-code/docker-webman) | [docker-webman](https://github.com/madong-code/docker-webman) | [docker-webman](https://gitcode.com/motion-code/docker-webman) |

> 组件库：[madong/query](https://gitee.com/motion-code/query)（数据查询组件）、[madong/helper](https://gitee.com/motion-code/helper)（通用帮助组件）、[madong/swagger](https://gitee.com/motion-code/swagger)（API 文档组件）已作为 Composer 依赖引入。

## 环境要求

- PHP >= 8.2（需扩展：`pdo`、`redis`、`gd`、`zip`，建议启用 `event`）
- Composer >= 2.x
- MySQL 5.7+ / 8.0
- Redis 5.0+

## 快速开始

```bash
# 1. 按标准目录布局克隆（madong/backend + madong/template）
git clone https://gitee.com/motion-code/madong-admin-saas.git madong/backend
cd madong/backend

# 2. 安装依赖
composer install

# 3. 启动服务（需提前准备好 MySQL 与 Redis）
# Windows
php windows.php
# Linux / macOS
php start.php start -d

# 4. 可视化安装
# 浏览器访问 http://127.0.0.1:8500 进入安装向导，
# 按页面提示填写数据库 / Redis 连接信息与管理员账号，
# 系统将自动生成 .env 并完成数据表初始化
```

前端（管理端）克隆至同级目录 `madong/template`，部署请参考 [madong-multi](https://gitee.com/motion-code/madong-multi) 仓库说明；容器化部署请参考 [docker-webman](https://gitee.com/motion-code/docker-webman)。

## 目录结构

```
madong/                       # 项目根目录
├── backend/                  # 服务后端（本仓库）
│   ├── app/                  # 业务代码（adminapi/api/platform 控制器、service、dao、model 等）
│   ├── config/               # 框架与业务配置
│   ├── core/                 # 核心框架层
│   ├── plugin/               # 插件目录
│   ├── public/               # 公共资源与安装入口
│   ├── resource/             # 语言包等资源
│   ├── support/              # 启动引导与助手函数
│   ├── composer.json         # 依赖清单
│   ├── start.php             # Linux/macOS 启动入口
│   ├── windows.php           # Windows 启动入口
│   └── webman                # 命令行入口
└── template/                 # 前端模板（管理端 / 门户站点）
```

## 版本

| 版本 | 说明 |
|---|---|
| 5.x | 当前版本，后端独立成库（本仓库），多租户 SaaS 架构 |
| 4.x 及更早 | 全栈单仓库版本，见标签 [`v4.x-final`](https://gitee.com/motion-code/madong-admin-saas/releases) |

## 开源协议

[Apache-2.0](LICENSE)
