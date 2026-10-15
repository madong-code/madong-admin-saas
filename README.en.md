> Need the **standard version [single-user standalone version]**? Visit the backend repository: [madong-code/madong-admin](https://github.com/madong-code/madong-admin)

<div align="center">

<img src="public/logo.png" alt="Madong Logo" width="120" />

# Madong-Saas Multitenant SaaS Backend

The multitenant SaaS backend built on the [Webman](https://www.workerman.net/doc/webman) resident-memory framework

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Webman](https://img.shields.io/badge/Webman-2.x-2488C9)](https://www.workerman.net/doc/webman)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2F8.0-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Redis](https://img.shields.io/badge/Redis-5.0+-DC382D?logo=redis&logoColor=white)](https://redis.io)
[![License](https://img.shields.io/badge/License-Apache--2.0-blue)](./LICENSE)

[![Docs](https://img.shields.io/badge/Docs-madong.tech-7C3AED)](https://madong.tech)
[![Download Center](https://img.shields.io/badge/Download%20Center-madong.tech-10B981)](https://madong.tech/download)

[中文](./README.md) | **English**

</div>

## Introduction

Madong-Saas is a multitenant SaaS backend built on the PHP **Webman** resident-memory framework, providing stable and efficient API services for frontend applications.

- The backend adopts a clear business layering architecture (DAO / Service / Controller) and a plugin system;
- **Multitenancy (SaaS)** is supported natively, with tenant isolation and per-tenant resource configuration;
- The frontend is based on the `mono` monorepo (big-repo) edition; the standard-version frontend can be reused, but some service interfaces differ and require targeted adjustments.

> Frontend and backend are deployed separately; the frontend directory must be downloaded or created on its own.

## Repository Addresses

This project is the **backend service (core)**. The companion frontend, container, and tooling repositories are listed below (pulled from GitHub by default; use the Gitee / GitCode mirrors when network access is limited):

| Module | GitHub | Gitee Mirror | GitCode Mirror |
| :--- | :--- | :--- | :--- |
| Backend service | https://github.com/madong-code/madong-admin-saas | https://gitee.com/motion-code/madong-admin-saas | https://gitcode.com/motion-code/madong-admin-saas |
| Mono [Platform / Tenant / Install] | https://github.com/madong-code/madong-mono | https://gitee.com/motion-code/madong-mono | https://gitcode.com/motion-code/madong-mono |
| Web | https://github.com/madong-code/web-nuxt | https://gitee.com/motion-code/web-nuxt | https://gitcode.com/motion-code/web-nuxt |
| Skills | https://github.com/madong-code/madong-admin-saas-skills | https://gitee.com/motion-code/madong-admin-saas-skills | https://gitcode.com/motion-code/madong-admin-saas-skills |
| Container | https://github.com/madong-code/docker-webman | https://gitee.com/motion-code/docker-webman | https://gitcode.com/motion-code/docker-webman |

> Component libraries: [madong/query](https://gitee.com/motion-code/query) (data query), [madong/helper](https://gitee.com/motion-code/helper) (general helpers), and [madong/swagger](https://gitee.com/motion-code/swagger) (API docs) are included as Composer dependencies.

## Runtime Environment

- PHP 8.2–8.4 (extensions required: `pdo`, `redis`, `gd`, `zip`; `event` recommended)
- Composer >= 2.x
- MySQL 5.7+ / 8.0
- Redis 5.0+
- Nginx / Apache (optional for production)

## Quick Start

Following the standard directory layout, clone this repository into `madong/backend` and the frontend into the sibling directory `madong/template`.

```bash
# 1. Clone the backend service
git clone https://github.com/madong-code/madong-admin-saas.git madong/backend
cd madong/backend

# 2. Install dependencies
composer install

# 3. Start the service (MySQL and Redis must be prepared in advance)
# Windows
php windows.php
# Linux / macOS
php start.php start -d

# 4. Visual installation
# Open http://127.0.0.1:8500 in your browser to launch the install wizard,
# fill in the database / Redis connection info and admin account as prompted,
# and the system will auto-generate .env and initialize the data tables
```

Clone the frontend (admin) into `madong/template`; for deployment refer to the [madong-mono](https://github.com/madong-code/madong-mono) repository. For containerized deployment refer to [docker-webman](https://github.com/madong-code/docker-webman).

## Directory Structure

```
madong/                       # project root
├── backend/                  # backend service (this repo)
└── template/                 # frontend templates (admin / portal)
```

## Versions

| Version | Description |
| :--- | :--- |
| 5.x | Current version; backend split into its own library (this repo) with a multitenant SaaS architecture |
| 4.x and earlier | Full-stack monorepo version; see the [`v4.x-final`](https://github.com/madong-code/madong-admin-saas/releases) tag |

## How to Contribute

Contributions are very welcome! [Open an Issue](https://github.com/madong-code/madong-admin-saas/issues) or submit a Pull Request.

1. Fork the repository
2. Create a branch: `git checkout -b feature/xxxx`
3. Commit: `git commit -am 'feat(function): add xxxxx'`
4. Push: `git push origin feature/xxxx`
5. Submit a Pull Request

## Git Commit Convention

Reference [Conventional Commits](https://www.conventionalcommits.org/): `feat` / `fix` / `style` / `perf` / `refactor` / `revert` / `test` / `docs` / `chore` / `ci` / `types` / `wip`.

## License

[Apache-2.0](LICENSE)
