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

namespace SinceLeo\Tenancy\Kernel\Amqp;

use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Packer\Packer;
use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SinceLeo\Tenancy\Kernel\Exceptions\TenancyException;

abstract class TenancyConsumer extends ConsumerMessage
{
    /**
     * 消息体反序列化.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function unserialize(string $data): mixed
    {
        $container = ApplicationContext::getContainer();
        $packer = $container->get(Packer::class);
        $result = $packer->unpack($data);
        $body = json_decode($result, true);
        
        // 只解析数据，不在这里初始化租户
        return $body;
    }

    /**
     * 消费消息.
     * @throws TenancyException
     */
    public function consumeMessage($data): string
    {
        $tenantId = $data['tenant_id'] ?? null;
        
        try {
            // 在消费时初始化租户
            if ($tenantId) {
                tenancy()->init($tenantId, false);
            }
            
            // 调用子类的实际消费逻辑
            return $this->handle($data['payload']);
        } finally {
            // 确保清理租户上下文
            if ($tenantId) {
                tenancy()->destroy();
            }
        }
    }

    /**
     * 子类实现具体业务逻辑.
     */
    abstract protected function handle(mixed $payload): string;
}
