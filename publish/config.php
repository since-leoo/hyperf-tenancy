<?php

declare(strict_types=1);

use SinceLeo\Tenancy\Kernel\Tenant\Models\Domain;
use SinceLeo\Tenancy\Kernel\Tenant\Models\Tenants;

/**
 * 修复后的配置文件
 * 增加了安全相关配置项
 */
return [
    'tenant_model' => Tenants::class,
    'domain_model' => Domain::class,
    
    // 租户上下文键名
    'context' => 'tenant_context',
    
    // 中央域名（不需要租户识别的域名）
    'central_domains' => [
        '127.0.0.1',
        'localhost',
    ],
    
    // 忽略的路由（支持通配符和正则表达式）
    'ignore_path' => [
        '/health',
        '/metrics',
        '/favicon.ico',
        // 通配符示例
        '/public/*',
        // 正则表达式示例（需要用/包裹）
        // '/^\/api\/v1\/auth\/.*$/',
    ],
    
    // 数据库配置
    'database' => [
        // 中央数据库连接名（不允许为default）
        'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'central'),
        
        // 扩展连接（不会被租户切换的连接）
        'extend_connections' => explode(',', env('TENANCY_EXTEND_CONNECTIONS', '')),
        
        // 租户数据库前缀
        'tenant_prefix' => env('TENANCY_TENANT_PREFIX', 'tenant_'),
        
        // 租户数据库表前缀
        'tenant_table_prefix' => env('TENANCY_TENANT_TABLE_PREFIX', ''),
        
        // 基础数据库（用于初始化租户数据库）
        'base_database' => 'base',
    ],
    
    // 缓存配置
    'cache' => [
        // 租户缓存前缀
        'tenant_prefix' => 'tenant_',
        
        // 租户缓存连接
        'tenant_connection' => 'tenant',
        
        // 中央缓存连接
        'central_connection' => 'central',
    ],
    
    // 安全配置
    'security' => [
        // 租户ID验证规则（正则表达式）
        // 只允许字母、数字、下划线，长度1-64
        'tenant_id_pattern' => '/^[a-zA-Z0-9_]{1,64}$/',
        
        // 允许的域名模式（支持通配符）
        'allowed_domains' => [
            '*.yourdomain.com',
            'localhost',
            '127.0.0.1',
        ],
        
        // 是否启用租户访问控制
        // 启用后会验证用户是否有权限访问指定租户
        'enable_access_control' => env('TENANCY_ENABLE_ACCESS_CONTROL', false),
        
        // 最大租户数限制（防止恶意创建）
        'max_tenants' => env('TENANCY_MAX_TENANTS', 1000),
        
        // 每个租户最大数据库连接数
        'max_connections_per_tenant' => env('TENANCY_MAX_CONNECTIONS_PER_TENANT', 5),
        
        // 是否记录审计日志
        'enable_audit_log' => env('TENANCY_ENABLE_AUDIT_LOG', true),
        
        // 审计日志保留天数
        'audit_log_retention_days' => env('TENANCY_AUDIT_LOG_RETENTION_DAYS', 90),
        
        // 是否在生产环境隐藏详细错误信息
        'hide_error_details_in_production' => true,
    ],
    
    // 速率限制配置
    'rate_limit' => [
        // 是否启用速率限制
        'enabled' => env('TENANCY_RATE_LIMIT_ENABLED', true),
        
        // 每分钟最大请求数
        'max_requests_per_minute' => env('TENANCY_RATE_LIMIT_MAX_REQUESTS', 60),
        
        // 速率限制的Redis连接
        'redis_connection' => 'default',
    ],
    
    // 性能配置
    'performance' => [
        // 是否启用租户信息缓存
        'enable_tenant_cache' => true,
        
        // 租户信息缓存时间（秒）
        'tenant_cache_ttl' => 3600,
        
        // 是否启用域名信息缓存
        'enable_domain_cache' => true,
        
        // 域名信息缓存时间（秒）
        'domain_cache_ttl' => 3600,
        
        // 是否启用连接池预热
        'enable_connection_warmup' => false,
    ],
    
    // 监控配置
    'monitoring' => [
        // 是否启用性能监控
        'enabled' => env('TENANCY_MONITORING_ENABLED', false),
        
        // 慢查询阈值（毫秒）
        'slow_query_threshold' => 1000,
        
        // 是否记录租户切换事件
        'log_tenant_switches' => true,
    ],
];
