<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */
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
    // 租户识别配置
    'tenant_identification' => [
        // 识别优先级: header > query > domain
        'priority' => ['header', 'query', 'domain'],
        'header_key' => 'x-tenant-id',
        'query_key' => 'tenant',
    ],
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
        // 租户缓存前缀
        'tenant_prefix' => 'tenant_',
        // 租户缓存驱动
        'tenant_connection' => 'tenant',
        // 缓存驱动
        'central_connection' => 'central',
    ],
];
