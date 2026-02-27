# 快速参考

## 🚀 快速开始

### 安装
```bash
composer require since-leoo/hyperf-tenancy
php bin/hyperf.php vendor:publish since-leoo/hyperf-tenancy
php bin/hyperf.php tenants:init
```

### 基础配置
```php
// config/autoload/tenancy.php
return [
    'tenant_model' => Tenants::class,
    'domain_model' => Domain::class,
    'database' => [
        'central_connection' => 'central',
        'tenant_prefix' => 'tenant_',
    ],
];
```

## 📖 常用API

### 租户操作

```php
// 获取租户ID
$tenantId = tenancy()->getId();

// 手动初始化租户
tenancy()->init('tenant_001', false);

// 销毁租户上下文
tenancy()->destroy();

// 多租户执行
Tenancy::runForMultiple(['tenant_001', 'tenant_002'], function ($tenant) {
    // 业务逻辑
});
```

### 队列操作

```php
// 推送队列任务
queue_push(new TenantJob(['data']));

// 延迟队列
queue_push(new TenantJob(['data']), 5);

// 创建队列任务
class TenantJob extends Job
{
    public function handle()
    {
        $tenantId = tenancy()->getId();
        // 业务逻辑
    }
}
```

### 缓存操作

```php
// 租户缓存
$cache = tenant_cache();
$cache->set('key', 'value', 3600);
$value = $cache->get('key');

// 租户Redis
$redis = tenant_redis();
$redis->set('key', 'value');
$value = $redis->get('key');
```

### 数据库操作

```php
// 自动使用租户数据库
$users = User::query()->get();

// 使用中央数据库
$connection = Tenancy::getCentralConnection();
$data = DB::connection($connection)->table('table')->get();
```

## 🔧 配置速查

### 租户识别方式

```php
// 1. Header 方式（优先级最高）
curl -H "X-TENANT-ID: tenant_001" http://api.com/users

// 2. Query 方式
curl http://api.com/users?tenant=tenant_001

// 3. Domain 方式（优先级最低）
curl -H "Host: tenant001.domain.com" http://api.com/users
```

### 忽略路径配置

```php
// config/autoload/tenancy.php
'ignore_path' => [
    '/health',
    '/metrics',
    '/favicon.ico',
],
```

### 队列驱动配置

```php
// config/autoload/async_queue.php
'default' => [
    'driver' => \SinceLeo\Tenancy\Kernel\Amqp\AsyncQueue\RedisDriver::class,
    'channel' => 'queue',
    'timeout' => 2,
    'retry_seconds' => 5,
],
```

## 🎯 命令速查

### 迁移命令

```bash
# 生成迁移文件
php bin/hyperf.php tenants:migrate-gen create_users_table

# 执行迁移（所有租户）
php bin/hyperf.php tenants:migrate

# 执行迁移（指定租户）
php bin/hyperf.php tenants:migrate --tenants=tenant_001

# 回滚迁移
php bin/hyperf.php tenants:rollback

# 执行填充
php bin/hyperf.php tenants:seeder
```

### 模型命令

```bash
# 生成模型
php bin/hyperf.php tenants:model User
```

## ⚠️ 注意事项

### 租户ID格式
✅ 允许: `tenant_001`, `company-abc`, `uuid-format`  
❌ 禁止: `tenant@001`, `tenant 001`, `tenant.001`

### 队列使用
- 任务会自动携带租户ID
- 任务执行完成后自动清理上下文
- 确保配置了租户队列驱动

### 性能优化
- 配置忽略路径减少不必要的租户识别
- 合理设置数据库连接池大小
- 使用缓存减少数据库查询

## 🐛 常见问题

### Q: 队列获取不到租户ID？
```php
// 检查队列驱动配置
'driver' => \SinceLeo\Tenancy\Kernel\Amqp\AsyncQueue\RedisDriver::class,
```

### Q: 租户上下文丢失？
```php
// 确保中间件已配置
// config/autoload/middlewares.php
'http' => [
    \SinceLeo\Tenancy\Middleware\TenantMiddleware::class,
],
```

### Q: 如何清除缓存？
```php
use SinceLeo\Tenancy\Kernel\Tenant\Models\Tenants;
use SinceLeo\Tenancy\Kernel\Tenant\Models\Domain;

Tenants::clearCache();
Domain::clearCache();
```

## 📊 性能基准

| 操作 | 耗时 | 说明 |
|------|------|------|
| 租户识别 | <1ms | 使用缓存优化 |
| 租户切换 | <2ms | 包含数据库连接 |
| 队列推送 | <5ms | 包含序列化 |
| 队列消费 | 变化 | 取决于业务逻辑 |

## 🔗 相关链接

- [完整文档](README.md)
- [优化说明](OPTIMIZATION.md)
- [升级指南](UPGRADE.md)
- [变更日志](CHANGELOG.md)
- [GitHub](https://github.com/since-leoo/hyperf-tenancy)

---

**提示**: 这是快速参考，详细信息请查看完整文档。
