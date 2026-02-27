
<p align="center">
    <a href="https://tenancyforlaravel.com"><img width="800" src="/art/logo.png" alt="Tenancy for Laravel logo" /></a>
</p>

# Tenancy for Hyperf

## 介绍

> 一个轻量级租户系统，支持多租户，支持跨租户请求，支持跨租户队列，支持跨租户数据库，支持跨租户缓存，支持跨租户日志，支持跨租户事件，支持跨租户路由，支持跨租户服务，支持跨租户配置，支持跨租户中间件，支持跨租户控制器，支持跨租户模型，支持跨租户服务提供者，

> 基于：Laravel Tenancy为灵感开发的一套简易版租户插件，让Hyperf也可以支持多租户，像Laravel Tenancy一样开发单租户业务一样，通过组件实现多租户的模式，做到真正的数据隔离
> 实现方式：通过中间件实现租户编号的获取，并设置到协程上下文中，通过重写数据库连接，在请求数据库时，自动切换到对应租户的数据库连接

## 安装

```shell
  composer require since-leoo/hyperf-tenancy
```

# 配置部分


## 生成配置文件

```
php bin/hyperf.php vendor:publish since-leoo/hyperf-tenancy
```

## 修改缓存配置

- cache.php修改为如下配置

```PHP
return [
    ...[],
    // 中央域缓存
    'default' => [
        'driver' => Hyperf\Cache\Driver\RedisDriver::class,
        'packer' => Hyperf\Codec\Packer\PhpSerializerPacker::class,
        'prefix' => 'c:',
    ],
    // 租户缓存
    'tenant' => [
        'driver' => \SinceLeo\Tenancy\Kernel\Tenant\Cache\RedisDriver::class,
        'packer' => Hyperf\Codec\Packer\PhpSerializerPacker::class,
        'prefix' => 'tenant:cache:'
    ],
];

```

- databases.php 增加如下配置

```PHP
return [
    'central' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'central'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_general_ci'),
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
        'commands' => [
            'gen:model' => [
                'path' => 'app\Model',
                'force_casts' => true,
                'inheritance' => 'Model',
                'uses' => '',
                'refresh_fillable' => true,
                'table_mapping' => [],
            ],
        ],
    ],
];
```

- redis.php 增加如下配置

```PHP
return [
    // 租户通用redis
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

- 配置文件tenancy.php 可选配置(可根据实际情况自行修改前缀等)
```PHP
<?php

use SinceLeo\Tenancy\Kernel\Tenant\Models\Domain;
use SinceLeo\Tenancy\Kernel\Tenant\Models\Tenants;

return [
    'tenant_model' => Tenants::class,
    'domain_model' => Domain::class,
    // 租户上下文
    'context' => 'tenant_context',
    'central_domains' => [
        '127.0.0.1',
        'localhost',
    ],
    // 忽略的路由
    'ignore_path' => [],
    'database' => [
        // 不允许为default
        'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'central'),
        // 扩展链接
        'extend_connections' => explode(',', env('TENANCY_EXTEND_CONNECTIONS', '')),
        // 租户数据库前缀
        'tenant_prefix' => env('TENANCY_TENANT_PREFIX', 'tenant_'),
        // 租户数据库表前缀
        'tenant_table_prefix' => env('TENANCY_TENANT_TABLE_PREFIX', ''),
        // 基础数据库
        'base_database' => 'base',
    ],
    'cache' => [
        'tenant_prefix' => 'tenant_',
        'tenant_connection' => 'tenant',
        'central_connection' => 'central',
    ],
];

```

## 初始化

    执行构建租户管理迁移文件  php bin/hyperf.php tenants:init 

- 注：必须配置好上述databases中的central数据库配置，手动创建好数据库
- 租户编号请在central中央库中进行维护，通过域名识别租户编号需要在central库中的tenant表与domain_tenants表中存在相应配置

## 使用说明

    获取租户编号的方式有三种（按优先级）：
    
    一、前端接口请求时在header中携带租户编号，如： X-TENANT-ID:xxxx

    二、在请求URL中通过query参数传递，如：http://api.com/users?tenant=xxxx
    
    三、在请求时通过域名获取租户编号，如：http://baidu.domain.com 内部将通过域名获取租户编号

### 租户操作

```PHP
    // 获取租户ID
    $tenantId = tenancy()->getId();
    
    // 执行脚本时，在指定租户内执行业务
    Tenancy::runForMultiple($tenantId, function ($tenant) {
            $this->line("Tenant: {$tenant['id']}");
           // 执行业务。。。。
    });

    // 执行脚本时，所有租户都执行
    Tenancy::runForMultiple(null, function ($tenant) {
            $this->line("Tenant: {$tenant['id']}");
           // 执行业务。。。。
    });
```

#### 数据迁移

- 使用 tenants:migrate-gen 进行创建生成迁移文件， 或通过其他方式创建迁移文件，但需更改迁移文件继承类为TenantMigration
- --tenants 为可选参数，默认不传递则所有租户执行此迁移，传递租户编号则只执行此租户
- php bin/hyperf.php tenants的迁移命令都继承于hyperf自身，所以框架自身支持的path，database等参数都支持使用

```
执行迁移：php bin/hyperf.php tenants:migrate  
执行回滚：php bin/hyperf.php tenants:rollback 
执行填充：php bin/hyperf.php tenants:seeder
```

### 队列使用

#### AsyncQueue 配置

在 `config/autoload/async_queue.php` 中配置使用租户队列驱动：

```PHP
<?php
return [
    'default' => [
        'driver' => \SinceLeo\Tenancy\Kernel\Amqp\AsyncQueue\RedisDriver::class,
        'channel' => 'queue',
        'timeout' => 2,
        'retry_seconds' => 5,
        'handle_timeout' => 10,
        'processes' => 1,
        'concurrent' => [
            'limit' => 10,
        ],
    ],
];
```

#### 创建队列任务

```PHP
<?php

declare(strict_types=1);

namespace App\Job;

use Hyperf\AsyncQueue\Job;

class TenantJob extends Job
{
    protected $params;
    
    public function __construct($params)
    {
        // 这里最好是普通数据，不要使用携带 IO 的对象，比如 PDO 对象
        $this->params = $params;
    }

    public function handle()
    {
        // 在这里可以直接使用租户上下文
        // 租户ID会自动从队列消息中恢复
        $tenantId = tenancy()->getId();
        var_dump($this->params, $tenantId);
        
        // 执行业务逻辑...
    }
}
```

#### 发送队列

```PHP
use App\Job\TenantJob;

// 立即执行
queue_push(new TenantJob(['data' => 'value']));

// 延迟5秒执行
queue_push(new TenantJob(['data' => 'value']), 5);
```

#### 注意事项

1. 队列任务会自动携带当前租户ID
2. 如果推送任务时没有租户上下文，任务将在中央域执行（tenantId为null）
3. 确保队列消费进程有访问所有租户数据库的权限
4. 任务执行完成后会自动清理租户上下文，避免协程复用时的上下文污染

## 最佳实践

### 1. 租户ID格式规范

租户ID只允许包含字母、数字、下划线和连字符，建议使用以下格式：
- UUID: `550e8400-e29b-41d4-a716-446655440000`
- 短ID: `tenant_001`, `company_abc`

### 2. 性能优化建议

- 使用 `ignore_path` 配置排除不需要租户识别的路径（如健康检查、静态资源）
- 合理设置数据库连接池大小，避免连接数过多
- 对于高频访问的租户，考虑使用 Redis 缓存租户信息

### 3. 安全建议

- 在生产环境中，建议通过域名识别租户，避免租户ID泄露
- 定期审计租户访问日志，检测异常访问
- 为不同租户设置独立的数据库用户权限

### 4. 数据库管理

- 定期备份租户数据库
- 监控租户数据库大小，及时清理过期数据
- 使用数据库命名规范：`tenant_{tenant_id}`

### 5. 缓存管理

```PHP
// 使用租户缓存
$cache = tenant_cache();
$cache->set('key', 'value', 3600);

// 使用租户Redis
$redis = tenant_redis();
$redis->set('key', 'value');
```

## 常见问题

### Q1: 队列任务中获取不到租户ID？

确保在 `config/autoload/async_queue.php` 中配置了租户队列驱动：
```php
'driver' => \SinceLeo\Tenancy\Kernel\Amqp\AsyncQueue\RedisDriver::class,
```

### Q2: 如何在命令行脚本中使用租户功能？

```php
// 手动初始化租户
tenancy()->init('tenant_001', false);

// 执行业务逻辑
// ...

// 清理租户上下文
tenancy()->destroy();
```

### Q3: 如何清除租户缓存？

```php
use SinceLeo\Tenancy\Kernel\Tenant\Models\Tenants;
use SinceLeo\Tenancy\Kernel\Tenant\Models\Domain;

// 清除租户缓存
Tenants::clearCache();

// 清除域名缓存
Domain::clearCache();
```

### Q4: 租户数据库如何初始化？

```bash
# 为指定租户执行迁移
php bin/hyperf.php tenants:migrate --tenants=tenant_001

# 为所有租户执行迁移
php bin/hyperf.php tenants:migrate
```

### Q5: 如何处理跨租户查询？

```php
// 使用中央数据库连接
$centralConnection = Tenancy::getCentralConnection();
$users = DB::connection($centralConnection)->table('users')->get();

// 或者在指定租户内执行
Tenancy::runForMultiple(['tenant_001', 'tenant_002'], function ($tenant) {
    // 这里会自动切换到对应租户的数据库
    $data = YourModel::query()->get();
});
```

## 故障排查

### 租户上下文丢失

检查以下几点：
1. 中间件是否正确配置
2. 请求头或域名是否正确传递
3. 查看日志确认租户初始化是否成功

### 数据库连接错误

1. 确认中央数据库配置正确
2. 检查租户数据库是否已创建
3. 验证数据库用户权限

### 队列任务执行失败

1. 检查队列驱动配置
2. 确认租户ID是否正确传递
3. 查看队列日志排查具体错误

## 贡献

欢迎提交 Issue 和 Pull Request！

## 许可证

MIT License

