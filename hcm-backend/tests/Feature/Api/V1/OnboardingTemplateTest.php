<?php

namespace Tests\Feature\Api\V1;

use App\Models\OnboardingTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class OnboardingTemplateTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_templates(): void
    {
        OnboardingTemplate::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/onboarding-templates')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_current_tenant_templates(): void
    {
        OnboardingTemplate::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        OnboardingTemplate::factory()->count(3)->create(); // other tenant

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/onboarding-templates');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_is_active(): void
    {
        OnboardingTemplate::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        OnboardingTemplate::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/onboarding-templates?is_active=true');

        $this->assertCount(2, $response->json('data'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_template(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-templates', [
                'title'       => 'Engineering Onboarding',
                'description' => 'Standard onboarding for engineers.',
                'is_active'   => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Engineering Onboarding');
    }

    public function test_store_requires_title(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-templates', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_denied_without_permission(): void
    {
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-templates', ['title' => 'Test'])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_template_with_tasks(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/onboarding-templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonStructure(['data' => ['tasks']]);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $template = OnboardingTemplate::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/onboarding-templates/{$template->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_template(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/onboarding-templates/{$template->id}", ['title' => 'Updated Title'])
            ->assertOk();

        $this->assertDatabaseHas('onboarding_templates', ['id' => $template->id, 'title' => 'Updated Title']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_template(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/onboarding-templates/{$template->id}")
            ->assertOk();

        $this->assertSoftDeleted('onboarding_templates', ['id' => $template->id]);
    }
}
