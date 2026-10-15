> 如需 **标准版本【单用户独立版本】**，请访问服务端仓库：[madong-code/madong-admin](https://github.com/madong-code/madong-admin)

<div align="center">

<img src="public/logo.png" alt="Madong Logo" width="120" />

# Madong-Saas 多租户 SaaS 服务后端

基于 [Webman](https://www.workerman.net/doc/webman) 常驻内存框架开发的多租户 SaaS 版服务端

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Webman](https://img.shields.io/badge/Webman-2.x-2488C9)](https://www.workerman.net/doc/webman)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2F8.0-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Redis](https://img.shields.io/badge/Redis-5.0+-DC382D?logo=redis&logoColor=white)](https://redis.io)
[![License](https://img.shields.io/badge/License-Apache--2.0-blue)](./LICENSE)

[![Docs](https://img.shields.io/badge/文档-madong.tech-7C3AED)](https://madong.tech)
[![Download](https://img.shields.io/badge/下载中心-madong.tech-10B981)](https://madong.tech/download)

**中文** | [English](./README.en.md)

</div>

## 介绍

Madong-Saas 是基于 PHP **Webman** 常驻内存框架的多租户 SaaS 服务后端，为前端应用提供稳定、高效的 API 服务。

- 后端采用清晰的业务分层架构（DAO / Service / Controller）与插件系统；
- 原生支持 **多租户（SaaS）**，提供租户隔离与按租户资源配置；
- 前端基于 `mono` 大仓（Monorepo）版本；可复用标准版本的前端，但因服务接口存在差异，需针对性调整部分接口。

> 前后端分离部署，前端目录需单独下载或创建。

## 仓库地址

本项目为 **服务后端（核心）**，配套前端、容器与工具仓库如下（默认从 GitHub 拉取，网络受限时可改用 Gitee / GitCode 镜像）：

| 模块 | GitHub | Gitee 镜像 | GitCode 镜像 |
| :----- | :--- | :--- | :--- |
| 服务端 | https://github.com/madong-code/madong-admin-saas | https://gitee.com/motion-code/madong-admin-saas | https://gitcode.com/motion-code/madong-admin-saas |
| Mono【平台、租户、安装】 | https://github.com/madong-code/madong-mono | https://gitee.com/motion-code/madong-mono | https://gitcode.com/motion-code/madong-mono |
| Web | https://github.com/madong-code/web-nuxt | https://gitee.com/motion-code/web-nuxt | https://gitcode.com/motion-code/web-nuxt |
| Skills | https://github.com/madong-code/madong-admin-saas-skills | https://gitee.com/motion-code/madong-admin-saas-skills | https://gitcode.com/motion-code/madong-admin-saas-skills |
| 容器 | https://github.com/madong-code/docker-webman | https://gitee.com/motion-code/docker-webman | https://gitcode.com/motion-code/docker-webman |


> 组件库：[madong/query](https://gitee.com/motion-code/query)（数据查询）、[madong/helper](https://gitee.com/motion-code/helper)（通用帮助）、[madong/swagger](https://gitee.com/motion-code/swagger)（API 文档）已作为 Composer 依赖引入。

## 运行环境

- PHP  8.2-8.4（需扩展：`pdo`、`redis`、`gd`、`zip`，建议启用 `event`）
- Composer >= 2.x
- MySQL 5.7+ / 8.0
- Redis 5.0+
- Nginx / Apache（生产环境可选）

## 快速开始

按标准目录布局，将本仓库克隆到 `madong/backend`，前端克隆到同级目录 `madong/template`。

```bash
# 1. 克隆服务后端
git clone https://github.com/madong-code/madong-admin-saas.git madong/backend
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

前端（管理端）克隆至 `madong/template`，部署请参考 [madong-mono](https://github.com/madong-code/madong-mono) 仓库说明；容器化部署请参考 [docker-webman](https://github.com/madong-code/docker-webman)。

## 目录结构

```
madong/                       # 项目根目录
├── backend/                  # 服务后端（本仓库）
└── template/                 # 前端模板（管理端 / 门户站点）
```

## 版本

| 版本 | 说明 |
| :--- | :--- |
| 5.x | 当前版本，后端独立成库（本仓库），多租户 SaaS 架构 |
| 4.x 及更早 | 全栈单仓库版本，见标签 [`v4.x-final`](https://github.com/madong-code/madong-admin-saas/releases) |

## 如何贡献

非常欢迎你的加入！[提一个 Issue](https://github.com/madong-code/madong-admin-saas/issues) 或提交 Pull Request。

1. Fork 代码
2. 创建分支：`git checkout -b feature/xxxx`
3. 提交：`git commit -am 'feat(function): add xxxxx'`
4. 推送：`git push origin feature/xxxx`
5. 提交 Pull Request

## Git 提交规范

参考 [Conventional Commits](https://www.conventionalcommits.org/)：`feat` / `fix` / `style` / `perf` / `refactor` / `revert` / `test` / `docs` / `chore` / `ci` / `types` / `wip`。

## 开源协议

[Apache-2.0](LICENSE)
