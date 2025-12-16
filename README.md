<p align="center">
    <a href="https://github.com/since-leoo/hyperf-tenancy"><img width="800" src="/art/logo.png" alt="Tenancy for Hyperf logo" /></a>
</p>

<p align="center">
    <a href="https://packagist.org/packages/since-leoo/hyperf-tenancy"><img src="https://img.shields.io/packagist/v/since-leoo/hyperf-tenancy" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/since-leoo/hyperf-tenancy"><img src="https://img.shields.io/packagist/dt/since-leoo/hyperf-tenancy" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/since-leoo/hyperf-tenancy"><img src="https://img.shields.io/packagist/l/since-leoo/hyperf-tenancy" alt="License"></a>
    <a href="https://packagist.org/packages/since-leoo/hyperf-tenancy"><img src="https://img.shields.io/packagist/php-v/since-leoo/hyperf-tenancy" alt="PHP Version"></a>
</p>

# Hyperf Tenancy - 企业级多租户解决方案

[English](README_EN.md) | 简体中文

## ✨ 特性

一个功能强大、安全可靠的 Hyperf 多租户组件，让你像开发单租户应用一样轻松构建 SaaS 平台。

- 🔒 **数据隔离** - 每个租户独立数据库，真正的数据隔离
- 🚀 **自动切换** - 基于域名或请求头自动识别租户，无需手动切换
- 🔄 **协程安全** - 完美支持 Swoole 协程，上下文自动传递
- 📦 **缓存隔离** - 租户级别的 Redis 缓存隔离
- 🔔 **队列支持** - AMQP 和 AsyncQueue 自动携带租户信息
- 🛡️ **安全加固** - 内置防注入、访问控制、速率限制
- 📊 **审计日志** - 完整的租户访问日志记录
- ⚡ **高性能** - 连接池复用，缓存优化
- 🎯 **易于使用** - 简洁的 API，开箱即用

## 📋 系统要求

- PHP >= 8.2
- Hyperf >= 3.1
- Swoole >= 5.0
- MySQL >= 5.7 或 MariaDB >= 10.3
- Redis >= 5.0

## 📦 安装

```bash
composer require since-leoo/hyperf-tenancy
```

## 🚀 快速开始

### 1️⃣ 发布配置文件

```bash
php bin/hyperf.php vendor:publish since-leoo/hyperf-tenancy
```

这将生成配置文件：`config/autoload/tenancy.php`

### 2️⃣ 配置数据库

#### 创建中央数据库

```bash
# 创建中央数据库（用于存储租户信息）
mysql -u root -p -e "CREATE DATABASE central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

#### 配置数据库连接

在 `config/autoload/databases.php` 中添加中央数据库配置：

```php
<?php
return [
    // 默认连接保持不变
    'default' => [
        // ... 你的默认配置
    ],
    
    // 添加中央数据库连接
    'central' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'central'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('DB_CENTRAL_PREFIX', ''),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => Hyperf\ModelCache\Handler\RedisHandler::class,
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'central',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,
        ],
    ],
];
```

### 3️⃣ 配置 Redis

在 `config/autoload/redis.php` 中添加租户 Redis 配置：

```php
<?php
return [
    'default' => [
        // ... 你的默认配置
    ],
    
    // 添加租户 Redis 连接
    'tenant' => [
        'host' => env('REDIS_HOST', 'localhost'),
        'auth' => env('REDIS_AUTH', null),
        'port' => (int)env('REDIS_PORT', 6379),
        'db' => (int)env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 32,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('REDIS_MAX_IDLE_TIME', 60),
        ]
    ]
];
```

### 4️⃣ 配置缓存

在 `config/autoload/cache.php` 中添加租户缓存配置：

```php
<?php
return [
    // 中央域缓存
    'default' => [
        'driver' => Hyperf\Cache\Driver\RedisDriver::class,
        'packer' => Hyperf\Codec\Packer\PhpSerializerPacker::class,
        'prefix' => 'c:',
    ],
    
    // 租户缓存（自动隔离）
    'tenant' => [
        'driver' => \SinceLeo\Tenancy\Kernel\Tenant\Cache\RedisDriver::class,
        'packer' => Hyperf\Codec\Packer\PhpSerializerPacker::class,
        'prefix' => 'tenant:cache:'
    ],
];
```

### 5️⃣ 配置环境变量

在 `.env` 文件中添加：

```env
# 中央数据库配置
DB_DATABASE=central

# 租户配置
TENANCY_CENTRAL_CONNECTION=central
TENANCY_TENANT_PREFIX=tenant_
TENANCY_TENANT_TABLE_PREFIX=

# 安全配置（可选）
TENANCY_ENABLE_ACCESS_CONTROL=false
TENANCY_ENABLE_AUDIT_LOG=true
TENANCY_RATE_LIMIT_ENABLED=true
TENANCY_RATE_LIMIT_MAX_REQUESTS=60
```

### 6️⃣ 初始化租户系统

```bash
# 执行迁移，创建租户管理表
php bin/hyperf.php tenants:init
```

这将在中央数据库创建以下表：
- `tenants` - 租户信息表
- `tenant_domains` - 租户域名表

### 7️⃣ 注册中间件

在 `config/autoload/middlewares.php` 中注册租户中间件：

```php
<?php
return [
    'http' => [
        // 在最前面添加租户中间件
        \SinceLeo\Tenancy\Middleware\TenantMiddleware::class,
        // ... 其他中间件
    ],
];
```

### 8️⃣ 创建第一个租户

```php
<?php
// 在 tinker 或控制器中执行

use SinceLeo\Tenancy\Kernel\Tenant\Models\Tenants;
use SinceLeo\Tenancy\Kernel\Tenant\Models\Domain;

// 创建租户
$tenant = Tenants::create([
    'id' => 'tenant_001',
    'data' => json_encode([
        'name' => '示例公司',
        'email' => 'admin@example.com',
    ]),
]);

// 绑定域名（可选）
Domain::create([
    'domain' => 'tenant001.yourdomain.com',
    'tenant_id' => 'tenant_001',
]);

// 创建租户数据库
// 方式1: 手动创建
// CREATE DATABASE tenant_tenant_001;

// 方式2: 使用代码创建（需要有权限）
DB::connection('central')->statement('CREATE DATABASE IF NOT EXISTS tenant_tenant_001');
```

✅ **完成！** 现在你可以开始使用多租户功能了。

## 📖 使用指南

### 租户识别方式

系统支持两种方式识别租户：

#### 方式一：通过请求头（推荐用于 API）

```bash
# 在请求头中携带租户ID
curl -H "X-TENANT-ID: tenant_001" https://api.yourdomain.com/users
```

```javascript
// JavaScript 示例
fetch('https://api.yourdomain.com/users', {
    headers: {
        'X-TENANT-ID': 'tenant_001',
        'Authorization': 'Bearer your-token'
    }
});
```

#### 方式二：通过域名（推荐用于 Web 应用）

```bash
# 通过子域名自动识别租户
https://tenant001.yourdomain.com/dashboard
https://company-a.yourdomain.com/products
```

配置域名映射：

```php
use SinceLeo\Tenancy\Kernel\Tenant\Models\Domain;

Domain::create([
    'domain' => 'tenant001.yourdomain.com',
    'tenant_id' => 'tenant_001',
]);
```

### 基础操作

#### 获取当前租户信息

```php
<?php

use SinceLeo\Tenancy\Kernel\Tenancy;

// 获取租户ID
$tenantId = tenancy()->getId();

// 获取租户模型
$tenant = tenancy()->getTenant();

// 获取租户数据
$tenantData = json_decode($tenant->data, true);
echo $tenantData['name']; // 输出：示例公司
```

#### 在控制器中使用

```php
<?php

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;

#[AutoController]
class UserController
{
    public function index()
    {
        // 自动使用当前租户的数据库
        $users = User::query()->get();
        
        // 获取当前租户ID
        $tenantId = tenancy()->getId();
        
        return [
            'tenant_id' => $tenantId,
            'users' => $users,
        ];
    }
}
```

#### 在指定租户内执行代码

```php
<?php

use SinceLeo\Tenancy\Kernel\Tenancy;

// 在单个租户内执行
Tenancy::runForMultiple('tenant_001', function ($tenant) {
    echo "当前租户: {$tenant->id}\n";
    
    // 这里的数据库操作会自动使用 tenant_001 的数据库
    $userCount = User::count();
    echo "用户数量: {$userCount}\n";
});

// 在多个租户内执行
Tenancy::runForMultiple(['tenant_001', 'tenant_002'], function ($tenant) {
    echo "处理租户: {$tenant->id}\n";
    // 执行业务逻辑
});

// 在所有租户内执行
Tenancy::runForMultiple(null, function ($tenant) {
    echo "处理租户: {$tenant->id}\n";
    // 批量处理所有租户
});
```

### 数据库迁移

#### 创建租户迁移文件

```bash
# 生成迁移文件
php bin/hyperf.php tenants:migrate-gen create_users_table

# 或使用 gen:migration 命令
php bin/hyperf.php gen:migration create_products_table
```

迁移文件需要继承 `TenantMigration`：

```php
<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use SinceLeo\Tenancy\Kernel\ClassMap\Migration as TenantMigration;

class CreateUsersTable extends TenantMigration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}
```

#### 执行迁移

```bash
# 为所有租户执行迁移
php bin/hyperf.php tenants:migrate

# 为指定租户执行迁移
php bin/hyperf.php tenants:migrate --tenants=tenant_001

# 为多个租户执行迁移
php bin/hyperf.php tenants:migrate --tenants=tenant_001,tenant_002

# 回滚迁移
php bin/hyperf.php tenants:rollback

# 执行数据填充
php bin/hyperf.php tenants:seeder --class=UserSeeder
```

### 缓存使用

#### 租户隔离的缓存

```php
<?php

// 使用租户缓存（自动隔离）
$cache = tenant_cache();

// 设置缓存
$cache->set('user_count', 100, 3600);

// 获取缓存
$count = $cache->get('user_count');

// 删除缓存
$cache->delete('user_count');
```

#### 租户 Redis

```php
<?php

// 使用租户 Redis（自动添加租户前缀）
$redis = tenant_redis();

// 设置值
$redis->set('key', 'value');
$redis->expire('key', 3600);

// 获取值
$value = $redis->get('key');

// 实际存储的键名会自动添加租户前缀
// 例如：tenant_tenant_001:key
```

### 队列使用

#### 创建租户队列任务

```php
<?php

namespace App\Job;

use Hyperf\AsyncQueue\Job;

class TenantJob extends Job
{
    protected $params;
    
    public function __construct($params)
    {
        // 传递普通数据，避免使用 IO 对象
        $this->params = $params;
    }

    public function handle()
    {
        // 队列会自动恢复租户上下文
        $tenantId = tenancy()->getId();
        
        echo "当前租户: {$tenantId}\n";
        echo "参数: " . json_encode($this->params) . "\n";
        
        // 执行业务逻辑
        // 数据库操作会自动使用当前租户的数据库
        $users = User::query()->get();
    }
}
```

#### 推送队列任务

```php
<?php

use App\Job\TenantJob;
use Hyperf\AsyncQueue\Driver\DriverFactory;

// 方式一：使用辅助函数
queue_push(new TenantJob(['action' => 'send_email']), 5);

// 方式二：使用 DriverFactory
$driver = di()->get(DriverFactory::class)->get('default');
$driver->push(new TenantJob(['action' => 'process_data']), 10);
```

#### AMQP 队列

```php
<?php

namespace App\Amqp\Producer;

use SinceLeo\Tenancy\Kernel\Amqp\TenancyProducer;

class UserCreatedProducer extends TenancyProducer
{
    protected string $exchange = 'user.events';
    protected string $routingKey = 'user.created';
    
    public function __construct($data)
    {
        $this->payload = $data;
    }
}
```

```php
<?php

namespace App\Amqp\Consumer;

use SinceLeo\Tenancy\Kernel\Amqp\TenancyConsumer;
use Hyperf\Amqp\Result;

class UserCreatedConsumer extends TenancyConsumer
{
    protected string $exchange = 'user.events';
    protected string $routingKey = 'user.created';
    protected string $queue = 'user.created.queue';
    
    public function consumeMessage($data, $message): Result
    {
        // 自动恢复租户上下文
        $tenantId = tenancy()->getId();
        
        // 处理消息
        echo "租户 {$tenantId} 创建了用户: " . json_encode($data) . "\n";
        
        return Result::ACK;
    }
}
```

### 命令行脚本

在命令行脚本中使用租户功能：

```php
<?php

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use SinceLeo\Tenancy\Kernel\Tenancy;

#[Command]
class TenantReportCommand extends HyperfCommand
{
    protected ?string $name = 'tenant:report';

    public function handle()
    {
        // 为所有租户生成报告
        Tenancy::runForMultiple(null, function ($tenant) {
            $this->line("生成租户 {$tenant->id} 的报告...");
            
            // 执行业务逻辑
            $userCount = User::count();
            $orderCount = Order::count();
            
            $this->info("用户数: {$userCount}, 订单数: {$orderCount}");
        });
        
        $this->info('所有租户报告生成完成！');
    }
}
```



## 🔧 高级配置

### 配置文件详解

`config/autoload/tenancy.php` 完整配置说明：

```php
<?php

return [
    // 租户模型类
    'tenant_model' => \SinceLeo\Tenancy\Kernel\Tenant\Models\Tenants::class,
    
    // 域名模型类
    'domain_model' => \SinceLeo\Tenancy\Kernel\Tenant\Models\Domain::class,
    
    // 租户上下文键名
    'context' => 'tenant_context',
    
    // 中央域名（不需要租户识别的域名）
    'central_domains' => [
        '127.0.0.1',
        'localhost',
        'admin.yourdomain.com', // 管理后台域名
    ],
    
    // 忽略的路由（不进行租户识别）
    'ignore_path' => [
        '/health',           // 健康检查
        '/metrics',          // 监控指标
        '/favicon.ico',      // 图标
        '/public/*',         // 公共资源（支持通配符）
    ],
    
    // 数据库配置
    'database' => [
        // 中央数据库连接名（存储租户信息）
        'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'central'),
        
        // 扩展连接（不会被租户切换的连接）
        'extend_connections' => explode(',', env('TENANCY_EXTEND_CONNECTIONS', '')),
        
        // 租户数据库前缀
        'tenant_prefix' => env('TENANCY_TENANT_PREFIX', 'tenant_'),
        
        // 租户数据库表前缀
        'tenant_table_prefix' => env('TENANCY_TENANT_TABLE_PREFIX', ''),
        
        // 基础数据库（用于初始化新租户）
        'base_database' => 'base',
    ],
    
    // 缓存配置
    'cache' => [
        'tenant_prefix' => 'tenant_',
        'tenant_connection' => 'tenant',
        'central_connection' => 'central',
    ],
    
    // 安全配置（推荐配置）
    'security' => [
        // 租户ID验证规则
        'tenant_id_pattern' => '/^[a-zA-Z0-9_]{1,64}$/',
        
        // 允许的域名模式
        'allowed_domains' => [
            '*.yourdomain.com',
            'localhost',
        ],
        
        // 是否启用访问控制
        'enable_access_control' => env('TENANCY_ENABLE_ACCESS_CONTROL', false),
        
        // 是否记录审计日志
        'enable_audit_log' => env('TENANCY_ENABLE_AUDIT_LOG', true),
        
        // 每个租户最大连接数
        'max_connections_per_tenant' => env('TENANCY_MAX_CONNECTIONS_PER_TENANT', 5),
    ],
    
    // 速率限制
    'rate_limit' => [
        'enabled' => env('TENANCY_RATE_LIMIT_ENABLED', true),
        'max_requests_per_minute' => env('TENANCY_RATE_LIMIT_MAX_REQUESTS', 60),
    ],
];
```

### 自定义租户模型

如果需要扩展租户模型，可以创建自己的模型类：

```php
<?php

namespace App\Model;

use SinceLeo\Tenancy\Kernel\Tenant\Models\Tenants as BaseTenants;

class Tenant extends BaseTenants
{
    // 添加自定义字段
    protected array $fillable = [
        'id',
        'data',
        'status',
        'plan',
        'expires_at',
    ];
    
    // 添加自定义方法
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    public function isPremium(): bool
    {
        return $this->plan === 'premium';
    }
}
```

然后在配置文件中指定：

```php
'tenant_model' => \App\Model\Tenant::class,
```

### 自定义中间件

如果需要自定义租户识别逻辑，可以扩展中间件：

```php
<?php

namespace App\Middleware;

use SinceLeo\Tenancy\Middleware\TenantMiddleware as BaseTenantMiddleware;
use Psr\Http\Message\ServerRequestInterface;

class CustomTenantMiddleware extends BaseTenantMiddleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 自定义逻辑：从 JWT token 中获取租户ID
        $token = $request->getHeaderLine('Authorization');
        if ($token) {
            $payload = $this->parseJWT($token);
            if (isset($payload['tenant_id'])) {
                tenancy()->init($payload['tenant_id']);
                return $handler->handle($request);
            }
        }
        
        // 回退到默认逻辑
        return parent::process($request, $handler);
    }
    
    private function parseJWT(string $token): array
    {
        // 实现 JWT 解析逻辑
        return [];
    }
}
```

## 🛡️ 安全最佳实践

### 1. 启用访问控制

```php
// config/autoload/tenancy.php
'security' => [
    'enable_access_control' => true,
],
```

创建租户用户关联：

```php
use Hyperf\DbConnection\Db;

// 授权用户访问租户
Db::connection('central')->table('tenant_users')->insert([
    'tenant_id' => 'tenant_001',
    'user_id' => 'user_123',
    'roles' => json_encode(['admin']),
    'status' => 'active',
    'created_at' => now(),
    'updated_at' => now(),
]);
```

### 2. 启用速率限制

```env
TENANCY_RATE_LIMIT_ENABLED=true
TENANCY_RATE_LIMIT_MAX_REQUESTS=60
```

### 3. 启用审计日志

```env
TENANCY_ENABLE_AUDIT_LOG=true
```

查看审计日志：

```bash
tail -f runtime/logs/tenant_access.log
```

### 4. 配置域名白名单

```php
'security' => [
    'allowed_domains' => [
        '*.yourdomain.com',
        'localhost',
    ],
],
```

### 5. 验证租户ID格式

系统会自动验证租户ID，只允许字母、数字和下划线，长度1-64位。

## 🔍 故障排查

### 问题：租户未识别

**症状**：请求返回 "The tenant ID is missing or invalid"

**解决方案**：

1. 检查请求头是否正确：
```bash
curl -H "X-TENANT-ID: tenant_001" http://localhost:9501/api/users
```

2. 检查域名是否已配置：
```php
Domain::where('domain', 'tenant001.yourdomain.com')->first();
```

3. 检查中间件是否已注册：
```php
// config/autoload/middlewares.php
'http' => [
    \SinceLeo\Tenancy\Middleware\TenantMiddleware::class,
],
```

### 问题：数据库连接失败

**症状**：SQLSTATE[HY000] [1049] Unknown database 'tenant_xxx'

**解决方案**：

1. 确保租户数据库已创建：
```sql
CREATE DATABASE tenant_tenant_001 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. 检查数据库配置：
```php
// config/autoload/databases.php
'central' => [
    'host' => 'localhost',
    'username' => 'root',
    'password' => 'your_password',
],
```

### 问题：缓存未隔离

**症状**：不同租户看到相同的缓存数据

**解决方案**：

1. 确保使用租户缓存：
```php
// ❌ 错误：使用默认缓存
$cache = di()->get(\Psr\SimpleCache\CacheInterface::class);

// ✅ 正确：使用租户缓存
$cache = tenant_cache();
```

2. 检查缓存配置：
```php
// config/autoload/cache.php
'tenant' => [
    'driver' => \SinceLeo\Tenancy\Kernel\Tenant\Cache\RedisDriver::class,
],
```

### 问题：队列租户丢失

**症状**：队列任务中无法获取租户ID

**解决方案**：

确保队列任务继承正确的基类：

```php
// ❌ 错误
use Hyperf\AsyncQueue\Job;
class MyJob extends Job { }

// ✅ 正确：使用租户队列
use SinceLeo\Tenancy\Kernel\Amqp\AsyncQueue\AsyncMessage;
// 或确保队列驱动使用租户版本
```

## 📊 性能优化

### 1. 启用租户信息缓存

```php
'performance' => [
    'enable_tenant_cache' => true,
    'tenant_cache_ttl' => 3600,
],
```

### 2. 限制连接池大小

```php
'security' => [
    'max_connections_per_tenant' => 5,
],
```

### 3. 使用连接池预热

```php
'performance' => [
    'enable_connection_warmup' => true,
],
```

### 4. 优化数据库查询

```php
// 使用索引
Schema::table('users', function (Blueprint $table) {
    $table->index('tenant_id');
    $table->index(['tenant_id', 'created_at']);
});

// 使用缓存
$users = tenant_cache()->remember('users:all', 3600, function () {
    return User::all();
});
```

## 🧪 测试

### 单元测试

```php
<?php

namespace HyperfTest\Cases;

use HyperfTest\HttpTestCase;

class TenantTest extends HttpTestCase
{
    public function testTenantIdentification()
    {
        // 测试通过请求头识别租户
        $response = $this->get('/api/users', [
            'X-TENANT-ID' => 'tenant_001',
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
    
    public function testTenantIsolation()
    {
        // 测试租户数据隔离
        $tenant1Users = $this->withTenant('tenant_001', function () {
            return User::count();
        });
        
        $tenant2Users = $this->withTenant('tenant_002', function () {
            return User::count();
        });
        
        $this->assertNotEquals($tenant1Users, $tenant2Users);
    }
    
    protected function withTenant(string $tenantId, callable $callback)
    {
        tenancy()->init($tenantId);
        $result = $callback();
        tenancy()->destroy();
        return $result;
    }
}
```

## 📚 常见场景

### 场景1：SaaS 平台

每个客户独立数据库，完全隔离：

```php
// 客户A访问
// https://company-a.yourdomain.com
// 自动使用 tenant_company_a 数据库

// 客户B访问
// https://company-b.yourdomain.com
// 自动使用 tenant_company_b 数据库
```

### 场景2：多品牌电商

不同品牌共享部分数据：

```php
// 品牌独立数据
$products = Product::query()->get(); // 自动使用租户数据库

// 共享数据（使用中央数据库）
$categories = Category::query()
    ->setConnection('central')
    ->get();
```

### 场景3：教育平台

每个学校独立系统：

```php
// 学校A
tenancy()->init('school_001');
$students = Student::query()->get();

// 学校B
tenancy()->init('school_002');
$students = Student::query()->get();
```

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

### 开发环境设置

```bash
git clone https://github.com/since-leoo/hyperf-tenancy.git
cd hyperf-tenancy
composer install
```

### 运行测试

```bash
composer test
```

### 代码规范

```bash
composer cs-fix
```

## 📄 许可证

本项目采用 [MIT](LICENSE) 许可证。

## 🙏 致谢

本项目受 [Laravel Tenancy](https://tenancyforlaravel.com) 启发，感谢 Laravel 社区的贡献。

## 📞 支持

- 📧 Email: root@imoi.cn
- 🐛 Issues: [GitHub Issues](https://github.com/since-leoo/hyperf-tenancy/issues)
- 📖 文档: [Wiki](https://github.com/since-leoo/hyperf-tenancy/wiki)

## 🔗 相关链接

- [Hyperf 官方文档](https://hyperf.wiki)
- [Swoole 文档](https://wiki.swoole.com)
- [Laravel Tenancy](https://tenancyforlaravel.com)

---

<p align="center">
Made with ❤️ by <a href="https://github.com/since-leoo">since-leoo</a>
</p>
