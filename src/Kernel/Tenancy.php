<?php

declare(strict_types=1);

namespace SinceLeo\Tenancy\Kernel;

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Redis\RedisFactory;
use Hyperf\Redis\RedisProxy;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use SinceLeo\Tenancy\Kernel\Exceptions\TenancyException;
use SinceLeo\Tenancy\Kernel\Tenant\Cache\CacheManager;
use SinceLeo\Tenancy\Kernel\Tenant\Models\Domain;
use SinceLeo\Tenancy\Kernel\Tenant\Models\Tenants as TenantModel;
use SinceLeo\Tenancy\Kernel\Tenant\Tenant;

/**
 * 修复后的Tenancy核心类
 * 主要修复：
 * 1. SQL注入防护
 * 2. 输入验证增强
 * 3. 连接池管理
 */
class Tenancy
{
    /**
     * 获取上下文前缀
     */
    public static function getContextKey(): string
    {
        return config('tenancy.context', 'tenant_context');
    }

    /**
     * @throws TenancyException
     */
    public static function tenantModel(): TenantModel
    {
        $class = config('tenancy.tenant_model');
        $tenantModel = new $class();
        if (!$tenantModel instanceof TenantModel) {
            throw new TenancyException('tenant_model instanceof error!');
        }
        return $tenantModel;
    }

    /**
     * @throws TenancyException
     */
    public static function domainModel(): Domain
    {
        $class = config('tenancy.domain_model');
        $domainModel = new $class();

        if (!$domainModel instanceof Domain) {
            throw new TenancyException('domain_model instanceof error!');
        }
        return $domainModel;
    }

    /**
     * 中央域数据库链接池
     * 
     * @throws TenancyException
     */
    public static function getCentralConnection(): string
    {
        $centralDatabase = config('tenancy.database.central_connection', 'central');
        if (empty($centralDatabase)) {
            throw new TenancyException('Central Connection Not Allow Is Empty!');
        }
        self::checkDbConnectionName($centralDatabase);
        return $centralDatabase;
    }

    /**
     * 获取扩展数据库链接池
     * 
     * @throws TenancyException
     */
    public static function extendConnections(): array
    {
        $extendConnections = config('tenancy.database.extend_connections', []);
        if (!is_array($extendConnections)) {
            if (!is_string($extendConnections)) {
                throw new TenancyException('extend_connections Format Is Array');
            }
            $extendConnections = explode(',', $extendConnections);
        }
        if (!in_array(self::getCentralConnection(), $extendConnections)) {
            $extendConnections[] = self::getCentralConnection();
        }
        $extendConnections = array_diff($extendConnections, ['']);
        self::checkDbConnectionName($extendConnections);
        return array_values(array_unique($extendConnections));
    }

    /**
     * 租户数据库前缀
     */
    public static function getTenantDbPrefix(): string
    {
        return config('tenancy.database.tenant_prefix', 'tenant_');
    }

    /**
     * 租户数据库表前缀
     */
    public static function getTenantDbTablePrefix(): string
    {
        return config('tenancy.database.tenant_table_prefix', '');
    }

    /**
     * 指定租户内执行
     * 
     * @throws TenancyException
     */
    public static function runForMultiple(mixed $tenants, callable $callable): void
    {
        // Convert null to all tenants
        $tenants = empty($tenants) ? self::tenantModel()::query()->orderBy('created_at')->pluck('id')->toArray() : $tenants;

        // Convert incrementing int ids to strings
        $tenants = is_int($tenants) ? (string) $tenants : $tenants;

        // Wrap string in array
        $tenants = is_string($tenants) ? [$tenants] : $tenants;

        $originTenantId = tenancy()->getId(false);
        try {
            foreach ($tenants as $tenantId) {
                // 验证租户ID
                if (!self::validateTenantId($tenantId)) {
                    logger()->warning('Invalid tenant ID in runForMultiple', ['tenant_id' => $tenantId]);
                    continue;
                }
                
                $tenant = tenancy()->init($tenantId);
                $callable($tenant);
                tenancy()->destroy();
            }
        } finally {
            $originTenantId && tenancy()->init($originTenantId);
        }
    }

    /**
     * 租户通用Redis
     * 
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \RedisException|TenancyException
     */
    public static function redis(): RedisProxy
    {
        $tenantId = Tenant::instance()->getId();
        
        // 验证租户ID
        if (!self::validateTenantId($tenantId)) {
            throw new TenancyException('Invalid tenant ID for Redis connection');
        }
        
        $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get(config('tenancy.cache.tenant_connection'));
        $redis->setOption(
            \Redis::OPT_PREFIX,
            config('tenancy.cache.tenant_prefix', 'tenant_') . $tenantId
        );
        return $redis;
    }

    /**
     * 缓存
     */
    public static function cache()
    {
        $tenantId = Tenant::instance()->getId();
        
        // 验证租户ID
        if (!self::validateTenantId($tenantId)) {
            throw new TenancyException('Invalid tenant ID for cache');
        }
        
        $tenantKey = config('tenancy.cache.tenant_prefix', 'tenant_') . $tenantId;
        return ApplicationContext::getContainer()->get(CacheManager::class)->setTenantConfig($tenantKey)->getDriver($tenantKey);
    }

    /**
     * 初始数据库
     */
    public static function baseDatabase(): mixed
    {
        return config('tenancy.database.base_database');
    }

    /**
     * 获取当前租户数据库（修复版本 - 防止SQL注入）
     * 
     * @throws TenancyException
     */
    public static function tenancyDatabase(?string $id = null): string
    {
        if (empty($id)) {
            $id = tenancy()->getId();
        }
        
        // 验证租户ID格式
        if (!self::validateTenantId($id)) {
            throw new TenancyException('Invalid tenant ID format');
        }
        
        return self::getTenantDbPrefix() . $id;
    }

    /**
     * 验证租户ID格式（防止SQL注入）
     */
    public static function validateTenantId(string $id): bool
    {
        // 获取配置的验证规则
        $pattern = config('tenancy.security.tenant_id_pattern', '/^[a-zA-Z0-9_]{1,64}$/');
        
        if (!preg_match($pattern, $id)) {
            return false;
        }
        
        // 限制长度
        if (strlen($id) > 64) {
            return false;
        }
        
        // 禁止特殊字符
        $blacklist = [';', '--', '/*', '*/', 'xp_', 'sp_', 'DROP', 'DELETE', 'INSERT', 'UPDATE'];
        foreach ($blacklist as $keyword) {
            if (stripos($id, $keyword) !== false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 初始化数据库连接配置
     * 
     * @throws TenancyException
     */
    public static function initDbConnectionName(?string $id = null): ?string
    {
        if (empty($id)) {
            $id = tenancy()->getId();
        }
        
        // 验证租户ID
        if (!self::validateTenantId($id)) {
            throw new TenancyException('Invalid tenant ID for database connection');
        }
        
        $name = self::tenancyDatabase($id);
        $key = 'databases.' . self::getCentralConnection();
        
        if (!config_base()->has($key)) {
            throw new TenancyException(sprintf('config[%s] is not exist!', $key));
        }
        
        $tenantKey = 'databases.' . $name;
        
        if (!config_base()->has($tenantKey)) {
            $tenantDatabaseConfig = config_base()->get($key);
            $tenantDatabaseConfig['database'] = $name;
            $tenantDatabaseConfig['prefix'] = self::getTenantDbTablePrefix();
            
            // 限制每个租户的最大连接数
            $maxConnectionsPerTenant = config('tenancy.security.max_connections_per_tenant', 5);
            if (isset($tenantDatabaseConfig['pool']['max_connections'])) {
                $tenantDatabaseConfig['pool']['max_connections'] = min(
                    $tenantDatabaseConfig['pool']['max_connections'],
                    $maxConnectionsPerTenant
                );
            }
            
            if (isset($tenantDatabaseConfig['cache']['prefix'])) {
                $tenantDatabaseConfig['cache']['prefix'] .= $id;
            }
            
            config_base()->set($tenantKey, $tenantDatabaseConfig);
        }
        
        return $name;
    }

    /**
     * 验证链接方式
     * 
     * @throws TenancyException
     */
    public static function checkDbConnectionName(array|string $connection, bool $isThrow = true): bool
    {
        $connection = is_array($connection) ? implode(',', $connection) : $connection;
        $connections = explode(',', $connection);
        
        if (in_array('default', $connections)) {
            throw new TenancyException('central or extend_connections Connection Not Allow Is default!');
        }
        
        if (str_contains($connection, self::getTenantDbPrefix())) {
            if ($isThrow) {
                throw new TenancyException('central or extend_connections Connection Not Allow Contain ' . self::getTenantDbPrefix());
            }
            return false;
        }
        
        return true;
    }

    /**
     * 是否存在 HTTP 请求
     */
    public static function checkIfHttpRequest(): bool
    {
        $request = Context::get(ServerRequestInterface::class);
        if ($request !== null) {
            return true;
        }
        return false;
    }
    
    /**
     * 获取租户统计信息
     */
    public static function getTenantStats(string $tenantId): array
    {
        if (!self::validateTenantId($tenantId)) {
            throw new TenancyException('Invalid tenant ID');
        }
        
        return [
            'tenant_id' => $tenantId,
            'database' => self::tenancyDatabase($tenantId),
            'cache_prefix' => config('tenancy.cache.tenant_prefix', 'tenant_') . $tenantId,
            'connection_name' => self::initDbConnectionName($tenantId),
        ];
    }
}
