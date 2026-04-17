<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Tenant::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Tenant)->getFillable();

        foreach (['name', 'slug', 'domain', 'logo_path', 'settings', 'is_active'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_settings_cast_to_array(): void
    {
        $tenant = Tenant::factory()->create(['settings' => ['theme' => 'dark']]);

        $this->assertIsArray($tenant->fresh()->settings);
        $this->assertEquals('dark', $tenant->fresh()->settings['theme']);
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $this->assertIsBool($tenant->is_active);
    }

    public function test_has_many_users(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->assertCount(3, $tenant->users);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $tenant = Tenant::factory()->create();
        $id = $tenant->id;

        $tenant->delete();

        $this->assertNull(Tenant::find($id));
        $this->assertNotNull(Tenant::withTrashed()->find($id));
    }

    public function test_can_be_restored_after_soft_delete(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->delete();
        $tenant->restore();

        $this->assertNotNull(Tenant::find($tenant->id));
    }
}
