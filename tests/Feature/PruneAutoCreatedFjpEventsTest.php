<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneAutoCreatedFjpEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_it_deletes_auto_created_events_without_pic_or_operational_data(): void
    {
        $event = $this->autoEvent();

        $this->artisan('fjp:prune-auto-events')->assertSuccessful();

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_it_keeps_auto_created_events_that_already_have_a_pic(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->autoEvent(['pic_id' => $pic->id]);

        $this->artisan('fjp:prune-auto-events')->assertSuccessful();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    public function test_dry_run_does_not_delete_anything(): void
    {
        $event = $this->autoEvent();

        $this->artisan('fjp:prune-auto-events', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    /** @param array<string, mixed> $overrides */
    private function autoEvent(array $overrides = []): Event
    {
        $event = Event::create(array_merge([
            'event_code' => Event::generateCode(),
            'event_name' => 'Auto Event Uji',
            'status' => EventStatus::PLANNING,
            'event_date' => now()->addDays(3)->toDateString(),
        ], $overrides));

        AuditLog::create([
            'action' => 'CREATE_EVENT_FROM_FJP',
            'module' => 'events',
            'record_type' => (new Event)->getMorphClass(),
            'record_id' => $event->id,
        ]);

        return $event;
    }
}
