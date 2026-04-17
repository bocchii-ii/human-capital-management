<?php

namespace Tests\Unit\Models;

use App\Models\Application;
use App\Models\Offer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant      = Tenant::factory()->create();
        $this->application = Application::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Offer::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Offer)->getFillable();

        foreach (['tenant_id', 'application_id', 'salary', 'currency', 'start_date', 'expires_at', 'status'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_salary_cast_to_decimal(): void
    {
        $offer = Offer::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
            'salary'         => 75000,
        ]);

        $this->assertEquals('75000.00', $offer->salary);
    }

    public function test_start_date_cast_to_date(): void
    {
        $offer = Offer::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $offer->start_date);
    }

    public function test_expires_at_cast_to_date(): void
    {
        $offer = Offer::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $offer->expires_at);
    }

    public function test_sent_at_cast_to_datetime(): void
    {
        $offer = Offer::factory()->sent()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $offer->sent_at);
    }

    public function test_belongs_to_tenant(): void
    {
        $offer = Offer::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertEquals($this->tenant->id, $offer->tenant->id);
    }

    public function test_belongs_to_application(): void
    {
        $offer = Offer::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertEquals($this->application->id, $offer->application->id);
    }

    public function test_sent_factory_state(): void
    {
        $offer = Offer::factory()->sent()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertEquals('sent', $offer->status);
        $this->assertNotNull($offer->sent_at);
    }

    public function test_accepted_factory_state(): void
    {
        $offer = Offer::factory()->accepted()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertEquals('accepted', $offer->status);
        $this->assertNotNull($offer->signed_at);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $offer = Offer::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);
        $id = $offer->id;

        $offer->delete();

        $this->assertNull(Offer::find($id));
        $this->assertNotNull(Offer::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $offer = Offer::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);
        $offer->delete();
        $offer->restore();

        $this->assertNotNull(Offer::find($offer->id));
    }
}
