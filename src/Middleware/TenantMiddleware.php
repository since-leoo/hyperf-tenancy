<?php

declare(strict_types=1);

namespace SinceLeo\Tenancy\Middleware;

use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SinceLeo\Tenancy\Kernel\Exceptions\TenancyException;
use SinceLeo\Tenancy\Kernel\Tenancy;
use function Hyperf\Config\config;

/**
 * 修复后的租户中间件
 * 主要增强：
 * 1. 速率限制
 * 2. 租户状态检查
 * 3. 访问日志记录
 * 4. 错误处理增强
 */
class TenantMiddleware implements MiddlewareInterface
{
    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @throws TenancyException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ignorePath = config('tenancy.ignore_path', []);
        $path = $request->getUri()->getPath();

        // 忽略的路径
        if (!empty($ignorePath) && $this->shouldIgnorePath($path, $ignorePath)) {
            return $handler->handle($request);
        }

        try {
            // 1. 速率限制检查
            $this->checkRateLimit($request);
            
            // 2. 初始化租户
            $tenant = tenancy()->init();
            
            // 3. 验证租户状态
            if (!$this->isTenantActive($tenant)) {
                throw new TenancyException('Tenant is inactive or suspended');
            }
            
            // 4. 检查IP白名单（如果配置了）
            $this->checkIpWhitelist($request, $tenant);
            
            // 5. 处理请求
            $response = $handler->handle($request);
            
            // 6. 添加租户信息到响应头（调试模式）
            if (config('app_debug')) {
                $response = $response->withHeader('X-Tenant-ID', $tenant->id);
                $response = $response->withHeader('X-Tenant-Database', Tenancy::tenancyDatabase($tenant->id));
            }
            
            return $response;
            
        } catch (TenancyException $e) {
            // 记录错误
            logger()->error('Tenant middleware error', [
                'error' => $e->getMessage(),
                'path' => $path,
                'ip' => $this->getClientIp($request),
                'user_agent' => $request->getHeaderLine('User-Agent'),
            ]);
            
            // 返回统一错误响应
            return $this->errorResponse($e);
            
        } catch (\Throwable $e) {
            // 记录未预期的错误
            logger()->error('Unexpected error in tenant middleware', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'path' => $path,
            ]);
            
            return $this->errorResponse(new TenancyException('Internal server error'));
        }
    }

    /**
     * 检查路径是否应该被忽略
     */
    protected function shouldIgnorePath(string $path, array $ignorePaths): bool
    {
        foreach ($ignorePaths as $pattern) {
            // 支持通配符匹配
            if (fnmatch($pattern, $path)) {
                return true;
            }
            
            // 支持正则表达式
            if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
                if (preg_match($pattern, $path)) {
                    return true;
                }
            }
            
            // 精确匹配
            if ($pattern === $path) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 速率限制检查
     */
    protected function checkRateLimit(ServerRequestInterface $request): void
    {
        if (!config('tenancy.rate_limit.enabled', false)) {
            return;
        }
        
        $ip = $this->getClientIp($request);
        $key = "rate_limit:{$ip}";
        $limit = config('tenancy.rate_limit.max_requests_per_minute', 60);
        
        try {
            $redis = $this->container->get(RedisFactory::class)->get('default');
            $current = $redis->incr($key);
            
            if ($current === 1) {
                $redis->expire($key, 60);
            }
            
            if ($current > $limit) {
                throw new TenancyException('Rate limit exceeded. Please try again later.');
            }
            
        } catch (\RedisException $e) {
            // Redis错误不应阻止请求
            logger()->warning('Rate limit check failed', [
                'error' => $e->getMessage(),
                'ip' => $ip,
            ]);
        }
    }

    /**
     * 检查租户是否激活
     */
    protected function isTenantActive($tenant): bool
    {
        if (!$tenant) {
            return false;
        }
        
        // 检查租户状态字段（如果存在）
        if (isset($tenant->status)) {
            return in_array($tenant->status, ['active', 'trial']);
        }
        
        // 检查租户是否过期（如果有过期时间字段）
        if (isset($tenant->expires_at)) {
            return $tenant->expires_at === null || $tenant->expires_at > now();
        }
        
        return true;
    }

    /**
     * 检查IP白名单
     */
    protected function checkIpWhitelist(ServerRequestInterface $request, $tenant): void
    {
        // 如果租户配置了IP白名单
        if (isset($tenant->allowed_ips) && !empty($tenant->allowed_ips)) {
            $clientIp = $this->getClientIp($request);
            $allowedIps = is_string($tenant->allowed_ips) 
                ? json_decode($tenant->allowed_ips, true) 
                : $tenant->allowed_ips;
            
            if (is_array($allowedIps) && !empty($allowedIps)) {
                $isAllowed = false;
                
                foreach ($allowedIps as $allowedIp) {
                    // 支持CIDR格式
                    if ($this->ipInRange($clientIp, $allowedIp)) {
                        $isAllowed = true;
                        break;
                    }
                }
                
                if (!$isAllowed) {
                    logger()->warning('IP not in whitelist', [
                        'tenant_id' => $tenant->id,
                        'client_ip' => $clientIp,
                        'allowed_ips' => $allowedIps,
                    ]);
                    
                    throw new TenancyException('Access denied: IP not allowed');
                }
            }
        }
    }

    /**
     * 检查IP是否在范围内（支持CIDR）
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        // 精确匹配
        if ($ip === $range) {
            return true;
        }
        
        // CIDR格式
        if (str_contains($range, '/')) {
            [$subnet, $mask] = explode('/', $range);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int)$mask);
            
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }
        
        return false;
    }

    /**
     * 获取客户端真实IP
     */
    protected function getClientIp(ServerRequestInterface $request): string
    {
        // 按优先级检查各种IP头
        $headers = [
            'X-Forwarded-For',
            'X-Real-IP',
            'CF-Connecting-IP', // Cloudflare
            'True-Client-IP',   // Akamai
        ];
        
        foreach ($headers as $header) {
            $ip = $request->getHeaderLine($header);
            if (!empty($ip)) {
                // X-Forwarded-For可能包含多个IP，取第一个
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // 验证IP格式
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // 回退到REMOTE_ADDR
        return $request->getServerParams()['remote_addr'] ?? 'unknown';
    }

    /**
     * 返回错误响应
     */
    protected function errorResponse(TenancyException $e): ResponseInterface
    {
        $statusCode = 403;
        $message = 'Access denied';
        
        // 根据异常类型返回不同的状态码
        if (str_contains($e->getMessage(), 'Rate limit')) {
            $statusCode = 429;
            $message = 'Too many requests';
        } elseif (str_contains($e->getMessage(), 'missing or invalid')) {
            $statusCode = 400;
            $message = 'Bad request';
        }
        
        // 开发环境返回详细错误信息
        if (config('app_debug')) {
            $message = $e->getMessage();
        }
        
        $response = new \Hyperf\HttpMessage\Server\Response();
        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream(json_encode([
                'code' => $statusCode,
                'message' => $message,
            ])));
    }
}
