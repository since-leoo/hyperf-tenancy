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

namespace SinceLeo\Tenancy\Kernel\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Psr\Container\ContainerInterface;
use SinceLeo\Tenancy\Kernel\Tenancy;

#[Listener]
class ValidateConfigListener implements ListenerInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        try {
            Tenancy::validateConfig();
        } catch (\Exception $e) {
            // 记录警告但不阻止应用启动
            echo sprintf("[Tenancy Warning] Configuration validation failed: %s\n", $e->getMessage());
        }
    }
}
