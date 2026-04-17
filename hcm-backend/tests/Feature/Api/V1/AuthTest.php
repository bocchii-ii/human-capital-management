<?php

namespace Tests\Feature\Api\V1;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class AuthTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email'    => $this->adminUser->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'tenant_id', 'roles', 'permissions'],
            ]);
    }

    public function test_login_returns_token(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email'    => $this->adminUser->email,
            'password' => 'password',
        ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_returns_roles_and_permissions(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email'    => $this->adminUser->email,
            'password' => 'password',
        ]);

        $this->assertContains('HR Admin', $response->json('user.roles'));
        $this->assertNotEmpty($response->json('user.permissions'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->postJson('/api/v1/login', [
            'email'    => $this->adminUser->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('email');
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->postJson('/api/v1/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonFragment(['id' => $this->adminUser->id, 'email' => $this->adminUser->email]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_invalidates_token(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out.']);

        // Token should no longer exist in the DB
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/logout')->assertUnauthorized();
    }
}
