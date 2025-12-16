<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SinceLeo\Tenancy\Kernel\TenancyFixed;
use SinceLeo\Tenancy\Kernel\Exceptions\TenancyException;

/**
 * 租户安全测试
 */
class TenantSecurityTest extends TestCase
{
    /**
     * 测试租户ID注入防护
     */
    public function testTenantIdInjectionPrevention()
    {
        // 测试SQL注入字符
        $maliciousIds = [
            "1'; DROP TABLE tenants; --",
            "1 OR 1=1",
            "1; DELETE FROM users",
            "../../../etc/passwd",
            "tenant_1' UNION SELECT * FROM users--",
        ];
        
        foreach ($maliciousIds as $id) {
            $this->assertFalse(
                TenancyFixed::validateTenantId($id),
                "Should reject malicious tenant ID: {$id}"
            );
        }
    }
    
    /**
     * 测试合法租户ID
     */
    public function testValidTenantIds()
    {
        $validIds = [
            'tenant_123',
            'abc123',
            'test_tenant_001',
            'TENANT_ABC',
        ];
        
        foreach ($validIds as $id) {
            $this->assertTrue(
                TenancyFixed::validateTenantId($id),
                "Should accept valid tenant ID: {$id}"
            );
        }
    }
}
