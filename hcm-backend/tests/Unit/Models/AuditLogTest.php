<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class AuditLogTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    private function makeLog(array $overrides = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->adminUser->id,
            'event'     => 'user.login',
        ], $overrides));
    }

    public function test_fillable_fields(): void
    {
        $log = new AuditLog();
        $this->assertEqualsCanonicalizing([
            'tenant_id', 'user_id', 'event', 'auditable_type', 'auditable_id',
            'old_values', 'new_values', 'ip_address', 'created_at',
        ], $log->getFillable());
    }

    public function test_old_values_cast_to_array(): void
    {
        $log = $this->makeLog(['old_values' => ['status' => 'draft']]);
        $this->assertIsArray($log->old_values);
        $this->assertEquals('draft', $log->old_values['status']);
    }

    public function test_new_values_cast_to_array(): void
    {
        $log = $this->makeLog(['new_values' => ['status' => 'approved']]);
        $this->assertIsArray($log->new_values);
        $this->assertEquals('approved', $log->new_values['status']);
    }

    public function test_created_at_set_automatically(): void
    {
        $log = $this->makeLog();
        $this->assertNotNull($log->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->created_at);
    }

    public function test_has_no_updated_at(): void
    {
        $log = $this->makeLog();
        $this->assertFalse($log->usesTimestamps());
    }

    public function test_belongs_to_user(): void
    {
        $log = $this->makeLog();
        $this->assertInstanceOf(User::class, $log->user);
    }

    public function test_user_is_nullable(): void
    {
        $log = $this->makeLog(['user_id' => null]);
        $this->assertNull($log->user);
    }
}
