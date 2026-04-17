<?php

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class OfferTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_draft_offer(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/offers', [
                'application_id' => $app->id,
                'salary'         => 85000,
                'currency'       => 'USD',
                'start_date'     => now()->addDays(30)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_store_requires_application_salary_and_start_date(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/offers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['application_id', 'salary', 'start_date']);
    }

    public function test_store_prevents_duplicate_offer(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        Offer::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/offers', [
                'application_id' => $app->id,
                'salary'         => 90000,
                'currency'       => 'USD',
                'start_date'     => now()->addDays(30)->toDateString(),
            ])
            ->assertUnprocessable();
    }

    public function test_store_denied_without_permission(): void
    {
        $app  = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/offers', [
                'application_id' => $app->id,
                'salary'         => 80000,
                'currency'       => 'USD',
                'start_date'     => now()->addDays(30)->toDateString(),
            ])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_offer(): void
    {
        $app   = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        $offer = Offer::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $offer->id);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $offer = Offer::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/offers/{$offer->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_offer_details(): void
    {
        $app   = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        $offer = Offer::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/offers/{$offer->id}", ['salary' => 95000])
            ->assertOk();

        $this->assertDatabaseHas('offers', ['id' => $offer->id, 'salary' => 95000]);
    }

    // ── Send ──────────────────────────────────────────────────────────────────

    public function test_send_transitions_draft_offer_to_sent(): void
    {
        $app   = Application::factory()->create(['tenant_id' => $this->tenant->id, 'stage' => 'interview']);
        $offer = Offer::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id, 'status' => 'draft']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/offers/{$offer->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('applications', ['id' => $app->id, 'stage' => 'offer']);
    }

    public function test_send_rejects_non_draft_offer(): void
    {
        $app   = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        $offer = Offer::factory()->sent()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/offers/{$offer->id}/send")
            ->assertUnprocessable();
    }

    // ── Update Status ─────────────────────────────────────────────────────────

    public function test_update_status_accepted_advances_application_to_hired(): void
    {
        $app   = Application::factory()->create(['tenant_id' => $this->tenant->id, 'stage' => 'offer']);
        $offer = Offer::factory()->sent()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/offers/{$offer->id}/status", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('applications', ['id' => $app->id, 'stage' => 'hired']);
    }

    public function test_update_status_declined_marks_application_rejected(): void
    {
        $app   = Application::factory()->create(['tenant_id' => $this->tenant->id, 'stage' => 'offer']);
        $offer = Offer::factory()->sent()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/offers/{$offer->id}/status", ['status' => 'declined'])
            ->assertOk();

        $this->assertDatabaseHas('applications', ['id' => $app->id, 'stage' => 'rejected']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_offer(): void
    {
        $app   = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        $offer = Offer::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/offers/{$offer->id}")
            ->assertOk();

        $this->assertSoftDeleted('offers', ['id' => $offer->id]);
    }
}
