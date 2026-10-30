# 数据库说明

`demo` 插件通过框架的迁移（migration）与种子（seeder）机制管理自身数据表，相关文件位于 `resource/database/`。

> **表名约定**：实际创建的表为 `{admin.database.table_prefix}demo_demo_test`。`config/info.php` 注释中提到的"demo_test"为旧表述，真实表名带 `demo_` 前缀，请以迁移文件为准。

---

## 1. 迁移文件

`resource/database/migrations/2026_06_16_000001_create_demo_test_table.php`

- 仅当表不存在时才创建（`hasTable` 判断）。
- 表注释：`demo插件-测试表`。

### 字段结构

| 字段 | 类型 | 约束 | 说明 |
| --- | --- | --- | --- |
| `id` | `bigInteger` unsigned | 主键 | 主键，使用雪花 ID 风格的大整型 |
| `name` | `string(100)` | 非空 | 名称 |
| `description` | `string(500)` | 可空 | 描述 |
| `category_id` | `unsignedBigInteger` | 默认 0，索引 | 分类 ID |
| `status` | `tinyInteger` | 默认 1，索引 | 状态：`1` 启用 / `2` 禁用 |
| `sort` | `unsignedInteger` | 默认 0 | 排序 |
| `deleted_at` | `unsignedInteger` | 可空 | 软删除时间（时间戳） |
| `created_at` | `unsignedInteger` | 非空 | 创建时间（时间戳） |
| `updated_at` | `unsignedInteger` | 非空 | 更新时间（时间戳） |

> 索引：`category_id`、`status` 各建单字段索引。时间字段采用 `unsignedInteger` 存 Unix 时间戳（非 `datetime`）。

### 回滚（down）
迁移的 `down()` 方法会 `dropIfExists` 同名表，配合框架迁移回滚使用。

---

## 2. 种子文件

`resource/database/seeds/DemoTestSeeder.php`

- 直接通过 `\support\Db::connection()` 操作，表名 `demo_demo_test`（`Db::table()` 会自动追加表前缀）。
- 幂等：若表中已有数据（`count > 0`）则直接返回，避免重复填充。
- 填充内容：10 条示例数据，字段包括 `id`（`time()*100 + i`）、`name`（`测试数据N`）、`description`、`category_id`（`rand(1,5)`）、`status=1`、`sort`、`created_at`/`updated_at` 为当前时间戳。

> 种子类名为 `DemoTestSeeder`，框架在填充阶段自动调用其 `run()` 方法。

---

## 3. 生命周期联动

| 时机 | 行为 |
| --- | --- |
| 安装插件 | 执行迁移建表 → 执行种子填充 10 条数据 |
| 重新安装（表已删） | 重新建表并再次填充 |
| 卸载插件（`drop_tables=true`） | 删除 `demo_demo_test` 表（含数据） |

---

## 4. 扩展建议

- 新增字段：在迁移文件中追加 `$table->...`，并重新执行迁移；或新建增量迁移文件（文件名带日期前缀）。
- 真实业务接入：示例页面当前使用前端 `mock` 数据。若需让后台示例页读取 `demo_demo_test`，请在 `app/adminapi/controller` 中实现 CRUD 接口，并将视图中的数据源从 `mock` 切换为真实 API（参见 [api.md](./api.md)）。
