<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\KitchenStatus;
use App\Enums\ReportStatus;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\EventTeamMember;
use App\Models\KitchenRequest;
use App\Models\User;
use App\Services\EventReadinessService;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KitchenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_request_cannot_be_created_for_an_event_without_jug_required(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, jugRequired: false);

        $this->actingAs($kitchenUser)
            ->from(route('kitchen.create'))
            ->post(route('kitchen.store'), [
                'event_id' => $event->id,
                'jug_quantity' => 5,
            ])
            ->assertRedirect(route('kitchen.create'))
            ->assertSessionHasErrors('event_id');

        $this->assertDatabaseMissing('kitchen_requests', ['event_id' => $event->id]);
    }

    public function test_duplicate_kitchen_request_for_the_same_event_returns_a_friendly_error(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, jugRequired: true);

        $this->actingAs($kitchenUser)
            ->post(route('kitchen.store'), [
                'event_id' => $event->id,
                'jug_quantity' => 5,
            ])
            ->assertSessionHasNoErrors();

        // The event_id column is unique: a concurrent double submit must be
        // answered with a gentle validation error, not a raw database crash.
        $this->actingAs($kitchenUser)
            ->from(route('kitchen.create'))
            ->post(route('kitchen.store'), [
                'event_id' => $event->id,
                'jug_quantity' => 5,
            ])
            ->assertRedirect(route('kitchen.create'))
            ->assertSessionHasErrors('event_id');

        $this->assertSame(1, KitchenRequest::where('event_id', $event->id)->count());
    }

    public function test_kitchen_role_uses_report_mode_on_index_and_create_pages(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();

        $this->actingAs($kitchenUser)
            ->get(route('kitchen.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kitchen/Index')
                ->where('reportMode', true));

        $this->actingAs($kitchenUser)
            ->get(route('kitchen.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kitchen/Create')
                ->where('reportMode', true));

        $this->actingAs($admin)
            ->get(route('kitchen.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kitchen/Index')
                ->where('reportMode', false));
    }

    public function test_kitchen_report_stores_cooking_and_washing_photos(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $kitchen = $this->createKitchenRequest($pic);

        $this->actingAs($kitchenUser)
            ->from(route('kitchen.show', $kitchen))
            ->post(route('kitchen.reports.store', $kitchen), [
                'prepared_quantity' => 5,
                'condition' => 'Baik',
                'photo' => UploadedFile::fake()->image('masak.jpg'),
                'washing_photo' => UploadedFile::fake()->image('cuci.jpg'),
            ])
            ->assertRedirect(route('kitchen.show', $kitchen))
            ->assertSessionHasNoErrors();

        $report = $kitchen->reports()->firstOrFail();
        $this->assertNotNull($report->photo_path);
        $this->assertNotNull($report->washing_photo_path);
        Storage::disk('local')->assertExists($report->photo_path);
        Storage::disk('local')->assertExists($report->washing_photo_path);

        $this->actingAs($kitchenUser)
            ->get(route('kitchen.show', $kitchen))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kitchen/Show')
                ->where('reports.0.photo_url', route('kitchen.reports.photo', $report->id))
                ->where('reports.0.washing_photo_url', route('kitchen.reports.washing-photo', $report->id)));

        $this->actingAs($kitchenUser)
            ->get(route('kitchen.reports.photo', $report))
            ->assertOk();

        $this->actingAs($kitchenUser)
            ->get(route('kitchen.reports.washing-photo', $report))
            ->assertOk();
    }

    public function test_washing_stage_stores_multiple_photos_and_automatic_timestamp(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $kitchen = $this->createKitchenRequest($pic);

        $this->actingAs($kitchenUser)
            ->from(route('kitchen.show', $kitchen))
            ->post(route('kitchen.reports.washing', $kitchen), [
                'washing_photos' => [
                    UploadedFile::fake()->image('cuci-1.jpg'),
                    UploadedFile::fake()->image('cuci-2.jpg'),
                    UploadedFile::fake()->image('cuci-3.jpg'),
                    UploadedFile::fake()->image('cuci-4.jpg'),
                ],
                'washing_at' => now()->subDay()->toDateTimeString(),
            ])
            ->assertRedirect(route('kitchen.show', $kitchen))
            ->assertSessionHasNoErrors();

        $report = $kitchen->reports()->firstOrFail();
        $this->assertNotNull($report->washing_at);
        $this->assertTrue($report->washing_at->isToday());
        $this->assertNull($report->cooking_at);
        $this->assertSame(4, $report->media()->where('type', 'washing')->count());
        $this->assertSame($report->media()->where('type', 'washing')->first()->path, $report->washing_photo_path);
        Storage::disk('local')->assertExists($report->washing_photo_path);
    }

    public function test_cooking_stage_requires_washing_report_first(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $kitchen = $this->createKitchenRequest($pic);

        $this->actingAs($kitchenUser)
            ->from(route('kitchen.show', $kitchen))
            ->post(route('kitchen.reports.cooking', $kitchen), [
                'cooking_photos' => [UploadedFile::fake()->image('masak.jpg')],
            ])
            ->assertRedirect(route('kitchen.show', $kitchen))
            ->assertSessionHasErrors('cooking_photos');

        $this->assertSame(0, $kitchen->reports()->count());
    }

    public function test_cooking_photos_are_merged_into_the_same_report(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $kitchen = $this->createKitchenRequest($pic);

        $this->actingAs($kitchenUser)->post(route('kitchen.reports.washing', $kitchen), [
            'washing_photos' => [UploadedFile::fake()->image('cuci.jpg')],
        ])->assertSessionHasNoErrors();

        $reportId = $kitchen->reports()->firstOrFail()->id;

        $this->actingAs($kitchenUser)->post(route('kitchen.reports.cooking', $kitchen), [
            'cooking_photos' => [
                UploadedFile::fake()->image('masak-1.jpg'),
                UploadedFile::fake()->image('masak-2.jpg'),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $kitchen->reports()->count());

        $report = $kitchen->reports()->findOrFail($reportId);
        $this->assertNotNull($report->cooking_at);
        $this->assertSame(2, $report->media()->where('type', 'cooking')->count());
        $this->assertSame(1, $report->media()->where('type', 'washing')->count());
    }

    public function test_ready_stage_requires_cooking_report_first(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $kitchen = $this->createKitchenRequest($pic);

        $this->actingAs($kitchenUser)->post(route('kitchen.reports.washing', $kitchen), [
            'washing_photos' => [UploadedFile::fake()->image('cuci.jpg')],
        ])->assertSessionHasNoErrors();

        $this->actingAs($kitchenUser)
            ->from(route('kitchen.show', $kitchen))
            ->post(route('kitchen.reports.ready', $kitchen), [
                'ready_photos' => [UploadedFile::fake()->image('siap.jpg')],
            ])
            ->assertSessionHasErrors('ready_photos');
    }

    public function test_ready_stage_completes_task_automatically(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, jugRequired: true, code: 'EVT-KITCHEN-READY');
        $kitchen = $event->kitchenRequest()->create([
            'jug_quantity' => 24,
            'status' => KitchenStatus::PENDING,
        ]);

        $this->actingAs($kitchenUser)->post(route('kitchen.reports.washing', $kitchen), [
            'washing_photos' => [UploadedFile::fake()->image('cuci.jpg')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(KitchenStatus::PREPARING, $kitchen->fresh()->status);

        $this->actingAs($kitchenUser)->post(route('kitchen.reports.cooking', $kitchen), [
            'cooking_photos' => [UploadedFile::fake()->image('masak.jpg')],
        ])->assertSessionHasNoErrors();

        $this->actingAs($kitchenUser)->post(route('kitchen.reports.ready', $kitchen), [
            'ready_photos' => [
                UploadedFile::fake()->image('siap-1.jpg'),
                UploadedFile::fake()->image('siap-2.jpg'),
                UploadedFile::fake()->image('siap-3.jpg'),
            ],
        ])->assertSessionHasNoErrors();

        $report = $kitchen->reports()->firstOrFail();
        $this->assertNotNull($report->ready_at);
        $this->assertTrue($report->isComplete());
        $this->assertSame(3, $report->media()->where('type', 'ready')->count());
        $this->assertSame(KitchenStatus::READY, $kitchen->fresh()->status);

        $readyMedia = $report->media()->where('type', 'ready')->firstOrFail();

        $this->actingAs($kitchenUser)
            ->get(route('kitchen.reports.media', $readyMedia))
            ->assertOk();
    }

    public function test_kitchen_media_can_be_deleted_and_cover_is_updated(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $kitchen = $this->createKitchenRequest($pic);

        $this->actingAs($kitchenUser)->post(route('kitchen.reports.washing', $kitchen), [
            'washing_photos' => [
                UploadedFile::fake()->image('cuci-1.jpg'),
                UploadedFile::fake()->image('cuci-2.jpg'),
            ],
        ])->assertSessionHasNoErrors();

        $report = $kitchen->reports()->firstOrFail();
        $first = $report->media()->where('type', 'washing')->orderBy('id')->firstOrFail();
        $second = $report->media()->where('type', 'washing')->orderByDesc('id')->firstOrFail();

        $this->assertSame($first->path, $report->washing_photo_path);

        $this->actingAs($kitchenUser)
            ->delete(route('kitchen.reports.media.destroy', $first))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('kitchen_report_media', ['id' => $first->id]);
        $this->assertSame($second->path, $report->fresh()->washing_photo_path);
        Storage::disk('local')->assertMissing($first->path);
    }

    public function test_washing_photo_requires_login(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $kitchen = $this->createKitchenRequest($pic);
        $report = $kitchen->reports()->create([
            'prepared_quantity' => 5,
            'photo_path' => 'kitchen/reports/bukti.jpg',
            'washing_photo_path' => 'kitchen/reports/washing/cuci.jpg',
        ]);

        $this->get(route('kitchen.reports.washing-photo', $report))
            ->assertRedirect(route('login'));
    }

    public function test_team_member_out_of_event_scope_cannot_open_washing_photo(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();

        // An event the team member is not assigned to.
        $foreignEvent = $this->createEvent($pic, jugRequired: true, code: 'EVT-KITCHEN-FOREIGN');
        $kitchen = $foreignEvent->kitchenRequest()->create([
            'jug_quantity' => 5,
            'status' => KitchenStatus::PREPARING,
        ]);
        Storage::disk('local')->put('kitchen/reports/washing/cuci.jpg', 'foto');
        $report = $kitchen->reports()->create([
            'prepared_quantity' => 5,
            'washing_photo_path' => 'kitchen/reports/washing/cuci.jpg',
        ]);

        // The team role grants kitchen.view, but EventAccess must still deny.
        $this->actingAs($team)
            ->get(route('kitchen.reports.washing-photo', $report))
            ->assertForbidden();
    }

    public function test_readiness_marks_kitchen_not_required_when_event_does_not_require_jug(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();

        $withoutJug = $this->createEvent($pic, jugRequired: false, code: 'EVT-READINESS-NOJUG');
        $withJug = $this->createEvent($pic, jugRequired: true, code: 'EVT-READINESS-JUG');

        $service = new EventReadinessService;

        $this->assertSame(
            EventReadinessService::NOT_REQUIRED,
            $service->evaluate($withoutJug)['modules']['kitchen'],
        );

        // A jug event without a kitchen request stays pending, never silently ready.
        $this->assertSame(
            EventReadinessService::PENDING,
            $service->evaluate($withJug)['modules']['kitchen'],
        );
    }

    public function test_closing_an_event_without_jug_does_not_block_on_missing_kitchen(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();

        $event = $this->createEvent($pic, jugRequired: false, code: 'EVT-CLOSE-NOJUG', overrides: [
            'status' => EventStatus::ONGOING,
            'event_date' => now()->subDay(),
        ]);

        EventTeamMember::create([
            'event_id' => $event->id,
            'user_id' => $team->id,
            'position' => 'SPG',
            'status' => 'assigned',
        ]);

        EventReport::create([
            'event_id' => $event->id,
            'sampling_target' => 500,
            'actual_sampling' => 480,
            'status' => ReportStatus::VERIFIED,
            'submitted_by' => $team->id,
            'submitted_at' => now(),
            'verified_by' => $adminEvent->id,
            'verified_at' => now(),
        ]);

        // No kitchen request exists, yet a non-jug event must still be closable.
        $this->actingAs($adminEvent)
            ->from(route('events.show', $event))
            ->post(route('events.close', $event))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(EventStatus::COMPLETED->value, $event->fresh()->status->value);
    }

    private function createEvent(
        User $pic,
        bool $jugRequired,
        string $code = 'EVT-KITCHEN-TEST',
        array $overrides = [],
    ): Event {
        return Event::create(array_merge([
            'event_code' => $code,
            'event_name' => 'Event Uji Kitchen',
            'status' => EventStatus::ONGOING,
            'pic_id' => $pic->id,
            'jug_required' => $jugRequired,
        ], $overrides));
    }

    private function createKitchenRequest(User $pic, string $code = 'EVT-KITCHEN-REPORT'): KitchenRequest
    {
        $event = $this->createEvent($pic, jugRequired: true, code: $code);

        return $event->kitchenRequest()->create([
            'jug_quantity' => 5,
            'status' => KitchenStatus::PREPARING,
        ]);
    }
}
