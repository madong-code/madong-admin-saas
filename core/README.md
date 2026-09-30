# backend/core

> 核心模块目录 · 6 大分组 · SaaS 多租户平台 (madong.tech)

`backend/core/` 是系统的核心库，提供框架基础能力、基础设施、安全认证、通信通知、文件 IO 和业务模块。所有模块按功能领域归并为 6 个分组，采用 PSR-4 自动加载。

---

## 目录结构

```
backend/core/
├── functions.php            # 全局辅助函数
├── README.md                # 本文件
│
├── foundation/              # 第1层 - 基础支撑
│   ├── config/              # 配置（app.php + 子模块配置）
│   ├── base/                # MVC 基础抽象类 (Controller/Service/Dao/Model/Validate)
│   ├── exception/           # 异常处理体系 (Handler + 15 种异常类)
│   ├── interface/           # 接口定义 (IEnum, IRequestInterface)
│   ├── trait/               # Trait 复用 (ServiceTrait, RequestHelpTrait)
│   └── tool/                # 工具类 (Json, Assert, Sse, RSA, Util)
│
├── infrastructure/          # 第2层 - 基础设施
│   ├── config/              
│   ├── cache/               # 缓存服务 (Symfony Cache)
│   ├── logger/              # 日志系统 (Monolog, 每日分割, 7天保留)
│   ├── monitor/             # 服务器监控 (CPU/内存/磁盘)
│   └── scheduler/           # 任务调度 (PHP/Shell/URL/类方法)
│
├── security/                # 第2层 - 安全认证
│   ├── config/              
│   ├── captcha/             # 验证码生成 (Session/Redis)
│   └── jwt/                 # JWT 认证 (双Token/多端登录/黑名单)
│
├── communication/           # 第3层 - 通信通知
│   ├── config/              
│   ├── email/               # 邮件服务 (PHPMailer)
│   ├── sms/                 # 短信服务 (阿里云/腾讯云)
│   └── notify/              # 消息推送 (Webman Push WebSocket)
│
├── io/                      # 第3层 - 文件 IO
│   ├── config/              
│   ├── excel/               # Excel 导出 (PhpSpreadsheet)
│   ├── upload/              # 文件上传 (本地/OSS/COS/七牛/S3)
│   └── uuid/                # ID 生成 (雪花ID, UUID)
│
└── business/                # 第4层 - 业务模块
    ├── config/              
    ├── generator/           # 全栈代码生成器 (14个文件生成器 + 模板)
    ├── plugin/              # 插件系统 (安装/卸载/迁移/模板/依赖)
    ├── route/               # 路由组织 + Swagger 注册
    ├── service/             # 核心服务 (动态表管理, 字段权限)
    └── tenant/              # 多租户核心 (上下文/连接管理/全局作用域)
```

---

## 配置加载机制

**入口文件：** `app/bootstrap/CoreConfigBootstrap.php`

系统扫描 `core/` 下的一级分组目录，检查每个分组的 `config/app.php` 中的 `enable` 字段。启用后，该分组的配置以 `core.{group}` 为命名空间加载。

### 访问方式

```php
config('core.{group}.{module_file}.{key}')
```

### 示例

| 配置键 | 对应文件 | 说明 |
|--------|---------|------|
| `core.foundation.app.enable` | `foundation/config/app.php` | 分组启用开关 |
| `core.foundation.exception.handler.dont_report` | `foundation/config/exception.php` | 异常免报列表 |
| `core.infrastructure.logger.base.path` | `infrastructure/config/logger.php` | 日志存储路径 |
| `core.infrastructure.scheduler.listen` | `infrastructure/config/scheduler.php` | 调度监听端口 |
| `core.security.jwt.token_name` | `security/config/jwt.php` | JWT Token 名 |
| `core.security.captcha.mode` | `security/config/captcha.php` | 验证码模式 |
| `core.communication.notify.webman-push` | `communication/config/notify.php` | Webman Push 配置 |
| `core.io.upload.adapter_classes` | `io/config/upload.php` | 上传适配器 |
| `core.io.snowflake.node_id` | `io/config/snowflake.php` | 雪花算法节点ID |
| `core.business.plugin.install` | `business/config/plugin.php` | 插件安装配置 |

---

## 命名空间规则

所有模块使用 `core\{group}\{module}` 命名空间：

```php
// 基础支撑
use core\foundation\base\BaseController;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;

// 基础设施
use core\infrastructure\cache\CacheService;
use core\infrastructure\logger\LoggerService;
use core\infrastructure\scheduler\SchedulerServer;

// 安全认证
use core\security\jwt\JwtToken;
use core\security\captcha\Captcha;

// 通信通知
use core\communication\notify\NotificationService;

// 文件 IO
use core\io\upload\UploadFile;
use core\io\uuid\UUIDGenerator;

// 业务模块
use core\business\generator\GeneratorEngine;
use core\business\plugin\PluginInstall;
use core\business\tenant\TenantConnectionManager;
```

---

## 依赖层级

```
foundation/ (第1层) → infrastructure/ + security/ (第2层) → communication/ + io/ (第3层) → business/ (第4层)
```

- **foundation/**：无内部依赖，所有模块都可能依赖
- **infrastructure/** + **security/**：依赖 foundation/
- **communication/** + **io/**：依赖 infrastructure/ 和 foundation/
- **business/**：依赖所有下层模块

---

## 如何新增模块

1. 在对应分组下创建目录：`core/{group}/{module}/`
2. 创建模块的 PHP 类，namespace 为 `core\{group}\{module}`
3. 如需配置，在分组 `config/` 目录下添加 `{module}.php`
4. 如需控制分组启用，在分组 `config/app.php` 中设置 `enable`

---

## functions.php

保留在 `core/` 根级，提供全局辅助函数。内部引用了 `core\foundation\exception\handler\CommonException`。
