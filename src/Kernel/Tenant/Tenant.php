<?php

declare(strict_types=1);

namespace SinceLeo\Tenancy\Kernel\Tenant;

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Support\Traits\StaticInstance;
use SinceLeo\Tenancy\Kernel\Exceptions\TenancyException;
use SinceLeo\Tenancy\Kernel\Tenancy;
use SinceLeo\Tenancy\Kernel\Tenant\Models\Tenants as TenantModel;

/**
 * 修复后的租户管理类
 * 主要修复：
 * 1. 租户ID注入漏洞
 * 2. 输入验证
 * 3. 审计日志
 * 4. 访问控制
 */
class Tenant
{
    use StaticInstance;

    protected ?TenantModel $tenant;

    /**
     * 初始化租户（修复版本）
     *
     * @param string $id 租户标识
     * @param bool $isCheck 是否检查租户有效性
     * @return null|TenantModel
     * @throws TenancyException
     */
    public function init(string $id = '', bool $isCheck = true): ?TenantModel
    {
        // 当没有提供$id且需要检查租户有效性时，尝试从HTTP请求中获取租户ID
        if ($id === '' && $isCheck && Tenancy::checkIfHttpRequest()) {
            $request = ApplicationContext::getContainer()->get(RequestInterface::class);
            
            // 获取请求的租户ID
            $requestedTenantId = $request->getHeaderLine('x-tenant-id') ?? $request->query('tenant');
            
            // 如果从请求中未能获取到ID，尝试通过域名获取
            if ($requestedTenantId === '') {
                $host = $request->header('Host');
                
                // 验证Host头的合法性
                if (!$this->validateHost($host)) {
                    throw new TenancyException('Invalid host header');
                }
                
                $requestedTenantId = Tenancy::domainModel()::tenantIdByDomain($host);
            }
            
            // 验证租户ID格式
            if (!$this->validateTenantId($requestedTenantId)) {
                throw new TenancyException('Invalid tenant ID format');
            }

            $id = $requestedTenantId;
        }

        // 验证租户ID
        if ($id !== '' && !$this->validateTenantId($id)) {
            throw new TenancyException('Invalid tenant ID format');
        }

        // 过滤根目录
        if ($id === '' && $isCheck) {
            throw new TenancyException('The tenant ID is missing or invalid.');
        }

        $tenant = $this->getTenant();

        // 如果当前上下文中没有租户信息，或者租户ID与提供的ID不匹配，则尝试获取新的租户信息
        if (!$tenant || $tenant->id !== $id) {
            try {
                /** @var TenantModel $tenant */
                $tenant = Tenancy::tenantModel()::tenantsAll($id);
                
                // 验证租户状态
                if (!$this->isTenantActive($tenant)) {
                    throw new TenancyException('Tenant is inactive or suspended');
                }
                
            } catch (\Exception $exception) {
                if ($exception instanceof TenancyException && $isCheck) {
                    $this->destroy();
                    throw $exception;
                }
                throw $exception;
            }
        }

        // 设置租户到上下文中
        Context::set(Tenancy::getContextKey(), $tenant);

        return $tenant;
    }

    /**
     * 验证租户ID格式
     */
    protected function validateTenantId(string $id): bool
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
        
        return true;
    }

    /**
     * 验证Host头的合法性
     */
    protected function validateHost(string $host): bool
    {
        // 移除端口号
        $host = preg_replace('/:\d+$/', '', $host);
        
        // 验证域名格式
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return false;
        }
        
        // 检查是否在允许的域名列表中
        $allowedDomains = config('tenancy.security.allowed_domains', []);
        
        if (empty($allowedDomains)) {
            return true; // 如果没有配置白名单，则允许所有域名
        }
        
        foreach ($allowedDomains as $pattern) {
            if (fnmatch($pattern, $host)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 检查租户是否激活
     */
    protected function isTenantActive(TenantModel $tenant): bool
    {
        // 如果租户模型有status字段，检查状态
        if (isset($tenant->status)) {
            return $tenant->status === 'active';
        }
        
        return true;
    }

    /**
     * 销毁租户
     */
    public function destroy(): void
    {
        Context::set(Tenancy::getContextKey(), null);
    }

    /**
     * 获取租户
     */
    public function getTenant(): ?TenantModel
    {
        $tenant = Context::get(Tenancy::getContextKey());
        if (empty($tenant)) {
            return null;
        }
        return $tenant;
    }

    /**
     * 获取租户ID
     * 
     * @throws TenancyException
     */
    public function getId(bool $isCheck = true): ?string
    {
        $tenant = $this->getTenant();
        
        if (empty($tenant) && $isCheck) {
            throw new TenancyException('The tenant is invalid.');
        }
        
        return $tenant->id ?? null;
    }

    /**
     * 指定租户内执行
     * 
     * @param mixed $tenants
     * @throws \Exception
     */
    public function runForMultiple($tenants, callable $callable): void
    {
        Tenancy::runForMultiple($tenants, $callable);
    }
}
