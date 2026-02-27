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

namespace SinceLeo\Tenancy\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SinceLeo\Tenancy\Kernel\Exceptions\TenancyException;
use function Hyperf\Config\config;

class TenantMiddleware implements MiddlewareInterface
{
    protected ContainerInterface $container;

    protected static array $ignorePathCache = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @throws TenancyException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // 使用缓存优化忽略路径检查
        if ($this->shouldIgnorePath($path)) {
            return $handler->handle($request);
        }

        try {
            tenancy()->init();
            return $handler->handle($request);
        } finally {
            // 请求结束后清理租户上下文（可选，根据实际需求）
            // tenancy()->destroy();
        }
    }

    /**
     * 检查是否应该忽略该路径.
     */
    protected function shouldIgnorePath(string $path): bool
    {
        if (! isset(self::$ignorePathCache[$path])) {
            $ignorePath = config('tenancy.ignore_path', []);
            self::$ignorePathCache[$path] = ! empty($ignorePath) && in_array($path, $ignorePath);
        }

        return self::$ignorePathCache[$path];
    }
}
