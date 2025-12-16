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

namespace SinceLeo\Tenancy\Kernel\Tenant\Models;

use Carbon\Carbon;
use Hyperf\Collection\Collection;
use Hyperf\Context\Context;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Model\Model;
use SinceLeo\Tenancy\Kernel\Exceptions\TenancyException;
use SinceLeo\Tenancy\Kernel\Tenant\CentralConnection;

/**
 * @property string $id
 * @property string $data 租户数据
 * @property string $status 租户状态
 * @property Carbon $last_accessed_at 最后访问时间
 * @property int $access_count 访问次数
 * @property array $allowed_ips 允许IP列表
 * @property Carbon $expires_at 过期时间
 * @property array $security_settings 安全设置
 * @property Carbon $deleted_at 删除时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
class Tenants extends Model
{
    use SoftDeletes;
    use CentralConnection;

    /**
     * The table associated with the model.
     */
    protected ?string $table = 'tenants';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = ['id', 'data', 'status', 'last_accessed_at', 'access_count', 'allowed_ips', 'expires_at', 'security_settings', 'deleted_at', 'created_at', 'updated_at'];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'id' => 'string',
        'data' => 'string',
        'status' => 'string',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
        'allowed_ips' => 'array',
        'expires_at' => 'datetime',
        'security_settings' => 'array',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function tenantsAll(?string $id = null, bool $reset = false)
    {
        $tenants = Context::get(self::class);
        if (empty($tenants) || $reset) {
            $tenants = self::query()->orderBy('created_at')->get();
            Context::set(self::class, $tenants);
        }
        if (! empty($id)) {
            $tenant = Collection::make($tenants)->where('id', $id)->first();
            if (empty($tenant)) {
                if ($reset) {
                    throw new TenancyException(
                        sprintf('The tenant %s is invalid', $id)
                    );
                }
                return self::tenantsAll($id, true);
            }
            return $tenant;
        }
        return $tenants;
    }
}
