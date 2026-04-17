<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(User::class));
    }

    public function test_uses_has_api_tokens(): void
    {
        $this->assertContains(HasApiTokens::class, class_uses_recursive(User::class));
    }

    public function test_uses_has_roles(): void
    {
        $this->assertContains(HasRoles::class, class_uses_recursive(User::class));
    }

    public function test_fillable_includes_tenant_id(): void
    {
        $this->assertContains('tenant_id', (new User)->getFillable());
    }

    public function test_password_is_hidden(): void
    {
        $this->assertContains('password', (new User)->getHidden());
    }

    public function test_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertEquals($tenant->id, $user->tenant->id);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $user = User::factory()->create();
        $id = $user->id;

        $user->delete();

        $this->assertNull(User::find($id));
        $this->assertNotNull(User::withTrashed()->find($id));
    }

    public function test_email_is_cast_correctly(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $this->assertEquals('test@example.com', $user->email);
    }
}
