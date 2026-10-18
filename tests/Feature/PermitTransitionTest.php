<?php

namespace Tests\Feature;

use App\Enums\PermitStatus;
use App\Models\PermitRequest;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_permitter_can_submit_a_pending_permit(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $permit = $this->createPermit(PermitStatus::PENDING);

        $this->actingAs($permitter)
            ->from(route('permits.show', $permit))
            ->post(route('permits.transition', $permit), ['action' => 'submit'])
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHasNoErrors();

        $this->assertSame(PermitStatus::SUBMITTED, $permit->fresh()->status);
    }

    public function test_admin_can_approve_a_submitted_permit(): void
    {
        $admin = $this->user('admin@indosakti.com');
        $permit = $this->createPermit(PermitStatus::SUBMITTED);

        $this->actingAs($admin)
            ->from(route('permits.show', $permit))
            ->post(route('permits.transition', $permit), ['action' => 'approve'])
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHasNoErrors();

        $this->assertSame(PermitStatus::APPROVED, $permit->fresh()->status);
    }

    public function test_approving_from_a_terminal_status_is_rejected(): void
    {
        $admin = $this->user('admin@indosakti.com');
        $permit = $this->createPermit(PermitStatus::CANCELLED);

        $this->actingAs($admin)
            ->from(route('permits.show', $permit))
            ->post(route('permits.transition', $permit), ['action' => 'approve'])
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHasErrors('status');

        $this->assertSame(PermitStatus::CANCELLED, $permit->fresh()->status);
    }

    public function test_revision_requires_a_reason(): void
    {
        $admin = $this->user('admin@indosakti.com');
        $permit = $this->createPermit(PermitStatus::SUBMITTED);

        $this->actingAs($admin)
            ->from(route('permits.show', $permit))
            ->post(route('permits.transition', $permit), ['action' => 'revision'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->from(route('permits.show', $permit))
            ->post(route('permits.transition', $permit), [
                'action' => 'revision',
                'reason' => 'Bubuhkan materai pada surat.',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $permit->fresh();
        $this->assertSame(PermitStatus::PENDING, $fresh->status);
        $this->assertSame('Bubuhkan materai pada surat.', $fresh->reason);
    }

    public function test_cancelling_an_approved_permit_is_rejected(): void
    {
        $admin = $this->user('admin@indosakti.com');
        $permit = $this->createPermit(PermitStatus::APPROVED);

        $this->actingAs($admin)
            ->from(route('permits.show', $permit))
            ->post(route('permits.transition', $permit), [
                'action' => 'cancel',
                'reason' => 'Dibatalkan tanpa proses resmi.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(PermitStatus::APPROVED, $permit->fresh()->status);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function createPermit(PermitStatus $status): PermitRequest
    {
        return PermitRequest::create([
            'permit_number' => PermitRequest::generateNumber(),
            'status' => $status,
            'created_by' => $this->user('admin@indosakti.com')->id,
        ]);
    }
}
