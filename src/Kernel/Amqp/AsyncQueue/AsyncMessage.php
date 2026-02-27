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

namespace SinceLeo\Tenancy\Kernel\Amqp\AsyncQueue;

use Hyperf\AsyncQueue\JobInterface;
use Hyperf\AsyncQueue\JobMessage;
use Hyperf\Contract\UnCompressInterface;

class AsyncMessage extends JobMessage
{
    public ?string $tenantId = null;

    public function __construct(JobInterface $job)
    {
        parent::__construct($job);
        // 明确获取租户ID，如果没有则为null
        $this->tenantId = tenancy()->getId(false);
    }

    public function __serialize(): array
    {
        return [
            $this->job,
            $this->attempts,
            $this->tenantId,
        ];
    }

    public function __unserialize($serialized): void
    {
        [$job, $attempts, $tenantId] = $serialized;
        if ($job instanceof UnCompressInterface) {
            $job = $job->uncompress();
        }
        $this->job = $job;
        $this->attempts = $attempts;
        $this->tenantId = $tenantId;
    }
}
