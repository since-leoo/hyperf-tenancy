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
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateTenantTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->engine = 'Innodb';
            $table->comment('[系统]租户表');
            $table->string('id')->unique()->comment('租户ID');
            $table->string('data', 500)->comment('配置');
            // 租户状态：active-激活, suspended-暂停, inactive-停用, trial-试用
            $table->enum('status', ['active', 'suspended', 'inactive', 'trial'])->default('active')->after('data')->comment('租户状态(租户状态：active-激活, suspended-暂停, inactive-停用, trial-试用)');
            // 最后访问时间
            $table->timestamp('last_accessed_at')->nullable()->after('status')->comment('最后访问时间');
            // 访问次数统计
            $table->unsignedBigInteger('access_count')->default(0)->after('last_accessed_at')->comment('访问次数');
            // IP白名单（JSON格式）
            $table->json('allowed_ips')->nullable()->after('access_count')->comment('IP白名单');
            // 过期时间
            $table->timestamp('expires_at')->nullable()->after('allowed_ips')->comment('过期时间');
            // 安全设置（JSON格式）
            $table->json('security_settings')->nullable()->after('expires_at')->comment('安全设置');

            $table->index('status');
            $table->index('last_accessed_at');
            $table->addColumn('timestamp', 'created_at', ['precision' => 0, 'comment' => '创建时间'])->nullable();
            $table->addColumn('timestamp', 'updated_at', ['precision' => 0, 'comment' => '更新时间'])->nullable();
            $table->addColumn('timestamp', 'deleted_at', ['precision' => 0, 'comment' => '删除时间'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
