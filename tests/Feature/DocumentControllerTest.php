<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_upload_a_document_to_an_event_they_cannot_see(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('public');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $event = $this->createEvent(
            User::where('email', 'pic@indosakti.com')->firstOrFail(),
        );

        $this->actingAs($team)
            ->post(route('documents.store'), [
                'documentable_type' => 'event',
                'documentable_id' => $event->id,
                'category' => 'photo',
                'file' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('documents', [
            'documentable_type' => Event::class,
            'documentable_id' => $event->id,
        ]);
    }

    public function test_authorized_user_can_upload_a_document_to_their_event(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('public');
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::where('pic_id', $pic->id)->firstOrFail();

        $this->actingAs($pic)
            ->post(route('documents.store'), [
                'documentable_type' => 'event',
                'documentable_id' => $event->id,
                'category' => 'photo',
                'file' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'documentable_type' => Event::class,
            'documentable_id' => $event->id,
            'category' => 'photo',
            'uploaded_by' => $pic->id,
        ]);
    }

    public function test_invalid_document_category_is_rejected(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('public');
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::where('pic_id', $pic->id)->firstOrFail();

        $this->actingAs($pic)
            ->post(route('documents.store'), [
                'documentable_type' => 'event',
                'documentable_id' => $event->id,
                'category' => 'malicious',
                'file' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertSessionHasErrors('category');
    }

    public function test_user_cannot_download_a_document_belonging_to_another_pics_event(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('public');
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $otherPic = User::factory()->create();
        $event = $this->createEvent($otherPic);

        Storage::disk('public')->put('documents/event/secret.pdf', 'secret');
        $document = $event->documents()->create([
            'category' => 'document',
            'original_name' => 'secret.pdf',
            'path' => 'documents/event/secret.pdf',
            'mime' => 'application/pdf',
            'size' => 6,
            'uploaded_by' => $otherPic->id,
        ]);

        $this->actingAs($pic)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_authorized_user_can_download_a_document_from_their_event(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('public');
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::where('pic_id', $pic->id)->firstOrFail();

        Storage::disk('public')->put('documents/event/report.pdf', 'berkas');
        $document = $event->documents()->create([
            'category' => 'document',
            'original_name' => 'report.pdf',
            'path' => 'documents/event/report.pdf',
            'mime' => 'application/pdf',
            'size' => 6,
            'uploaded_by' => $pic->id,
        ]);

        $this->actingAs($pic)
            ->get(route('documents.download', $document))
            ->assertOk();
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    private function createEvent(User $pic, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'event_code' => 'EVT-DOC-'.uniqid(),
            'event_name' => 'Event Dokumen '.uniqid(),
            'status' => EventStatus::READY,
            'pic_id' => $pic->id,
        ], $overrides));
    }
}
