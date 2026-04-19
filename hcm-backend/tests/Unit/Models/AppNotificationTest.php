<?php

namespace Tests\Unit\Models;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class AppNotificationTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    private function makeNotification(array $overrides = []): AppNotification
    {
        return AppNotification::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->adminUser->id,
            'type'      => 'enrollment.completed',
            'title'     => 'Course Completed',
            'body'      => 'You completed the course.',
        ], $overrides));
    }

    public function test_fillable_fields(): void
    {
        $n = new AppNotification();
        $this->assertEqualsCanonicalizing(
            ['tenant_id', 'user_id', 'type', 'title', 'body', 'data', 'read_at'],
            $n->getFillable()
        );
    }

    public function test_data_is_cast_to_array(): void
    {
        $n = $this->makeNotification(['data' => ['key' => 'value']]);
        $this->assertIsArray($n->data);
        $this->assertEquals('value', $n->data['key']);
    }

    public function test_read_at_is_cast_to_datetime(): void
    {
        $n = $this->makeNotification(['read_at' => now()]);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $n->read_at);
    }

    public function test_is_read_returns_false_when_unread(): void
    {
        $n = $this->makeNotification();
        $this->assertFalse($n->isRead());
    }

    public function test_is_read_returns_true_when_read(): void
    {
        $n = $this->makeNotification(['read_at' => now()]);
        $this->assertTrue($n->isRead());
    }

    public function test_mark_as_read_sets_read_at(): void
    {
        $n = $this->makeNotification();
        $n->markAsRead();
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mark_as_read_is_idempotent(): void
    {
        $past = now()->subHour();
        $n    = $this->makeNotification(['read_at' => $past]);
        $n->markAsRead();
        // read_at should not have been updated to now (still roughly an hour ago)
        $this->assertGreaterThan(50, $n->fresh()->read_at->diffInMinutes(now()));
    }

    public function test_belongs_to_user(): void
    {
        $n = $this->makeNotification();
        $this->assertInstanceOf(User::class, $n->user);
    }
}
