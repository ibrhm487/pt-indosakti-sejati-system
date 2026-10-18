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
        Storage::fake('local');
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
        Storage::fake('local');
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

        $document = $event->documents()->latest('id')->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertDatabaseHas('documents', [
            'documentable_type' => Event::class,
            'documentable_id' => $event->id,
            'category' => 'photo',
            'uploaded_by' => $pic->id,
        ]);
    }

    public function test_uploaded_documents_are_stored_on_the_private_disk_not_public(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
        Storage::fake('public');
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::where('pic_id', $pic->id)->firstOrFail();

        $this->actingAs($pic)
            ->post(route('documents.store'), [
                'documentable_type' => 'event',
                'documentable_id' => $event->id,
                'category' => 'document',
                'file' => UploadedFile::fake()->create('surat-izin.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = $event->documents()->latest('id')->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'Dokumen tidak boleh tersimpan di disk publik.');
    }

    public function test_invalid_document_category_is_rejected(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
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
        Storage::fake('local');
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $otherPic = User::factory()->create();
        $event = $this->createEvent($otherPic);

        Storage::disk('local')->put('documents/event/secret.pdf', 'secret');
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
        Storage::fake('local');
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::where('pic_id', $pic->id)->firstOrFail();

        Storage::disk('local')->put('documents/event/report.pdf', 'berkas');
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

    public function test_legacy_documents_on_the_public_disk_remain_downloadable_until_migrated(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
        Storage::fake('public');
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::where('pic_id', $pic->id)->firstOrFail();

        Storage::disk('public')->put('documents/event/legacy.pdf', 'lama');
        $document = $event->documents()->create([
            'category' => 'document',
            'original_name' => 'legacy.pdf',
            'path' => 'documents/event/legacy.pdf',
            'mime' => 'application/pdf',
            'size' => 4,
            'uploaded_by' => $pic->id,
        ]);

        $this->actingAs($pic)
            ->get(route('documents.download', $document))
            ->assertOk();
    }

    public function test_migrate_command_copies_public_files_to_private_without_deleting_sources(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
        Storage::fake('public');
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::where('pic_id', $pic->id)->firstOrFail();

        Storage::disk('public')->put('documents/event/legacy.pdf', 'isi-file-lama');
        $event->documents()->create([
            'category' => 'document',
            'original_name' => 'legacy.pdf',
            'path' => 'documents/event/legacy.pdf',
            'mime' => 'application/pdf',
            'size' => 13,
            'uploaded_by' => $pic->id,
        ]);

        $this->artisan('documents:migrate-to-private')->assertSuccessful();

        Storage::disk('local')->assertExists('documents/event/legacy.pdf');
        $this->assertSame('isi-file-lama', Storage::disk('local')->get('documents/event/legacy.pdf'));
        Storage::disk('public')->assertExists('documents/event/legacy.pdf');
        $this->assertSame('isi-file-lama', Storage::disk('public')->get('documents/event/legacy.pdf'), 'File sumber tidak boleh dihapus oleh command.');
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
