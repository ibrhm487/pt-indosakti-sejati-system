<?php

namespace Tests\Feature;

use App\Enums\PermitStatus;
use App\Models\Fjp;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FjpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_permitter_can_submit_a_draft_fjp(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp(PermitStatus::DRAFT);

        $this->actingAs($permitter)
            ->from(route('fjp.show', $fjp))
            ->post(route('fjp.workflow', $fjp), ['action' => 'submit'])
            ->assertRedirect(route('fjp.show', $fjp))
            ->assertSessionHasNoErrors();

        $this->assertSame(PermitStatus::SUBMITTED, $fjp->fresh()->permit_status);
    }

    public function test_approving_a_draft_directly_is_rejected(): void
    {
        $admin = $this->user('admin@indosakti.com');
        $fjp = $this->createFjp(PermitStatus::DRAFT);

        $this->actingAs($admin)
            ->from(route('fjp.show', $fjp))
            ->post(route('fjp.workflow', $fjp), ['action' => 'approve'])
            ->assertRedirect(route('fjp.show', $fjp))
            ->assertSessionHasErrors('permit_status');

        $this->assertSame(PermitStatus::DRAFT, $fjp->fresh()->permit_status);
    }

    public function test_admin_can_approve_a_submitted_fjp(): void
    {
        $admin = $this->user('admin@indosakti.com');
        $fjp = $this->createFjp(PermitStatus::SUBMITTED);

        $this->actingAs($admin)
            ->from(route('fjp.show', $fjp))
            ->post(route('fjp.workflow', $fjp), ['action' => 'approve'])
            ->assertRedirect(route('fjp.show', $fjp))
            ->assertSessionHasNoErrors();

        $this->assertSame(PermitStatus::APPROVED, $fjp->fresh()->permit_status);
    }

    public function test_rejecting_requires_a_reason_and_stores_it(): void
    {
        $admin = $this->user('admin@indosakti.com');
        $fjp = $this->createFjp(PermitStatus::SUBMITTED);

        $this->actingAs($admin)
            ->from(route('fjp.show', $fjp))
            ->post(route('fjp.workflow', $fjp), ['action' => 'reject'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->from(route('fjp.show', $fjp))
            ->post(route('fjp.workflow', $fjp), [
                'action' => 'reject',
                'reason' => 'Surat izin sekolah belum lengkap.',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $fjp->fresh();
        $this->assertSame(PermitStatus::REJECTED, $fresh->permit_status);
        $this->assertSame('Surat izin sekolah belum lengkap.', $fresh->rejection_reason);
    }

    public function test_cancelled_and_approved_fjp_are_terminal(): void
    {
        $admin = $this->user('admin@indosakti.com');

        $cancelled = $this->createFjp(PermitStatus::CANCELLED);
        $this->actingAs($admin)
            ->from(route('fjp.show', $cancelled))
            ->post(route('fjp.workflow', $cancelled), [
                'action' => 'cancel',
                'reason' => 'Membatalkan yang sudah dibatalkan.',
            ])
            ->assertSessionHasErrors('permit_status');

        $approved = $this->createFjp(PermitStatus::APPROVED);
        $this->actingAs($admin)
            ->from(route('fjp.show', $approved))
            ->post(route('fjp.workflow', $approved), [
                'action' => 'cancel',
                'reason' => 'Pembatalan setelah persetujuan.',
            ])
            ->assertSessionHasErrors('permit_status');

        $this->assertSame(PermitStatus::CANCELLED, $cancelled->fresh()->permit_status);
        $this->assertSame(PermitStatus::APPROVED, $approved->fresh()->permit_status);
    }

    public function test_rejected_fjp_can_be_resubmitted(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp(PermitStatus::REJECTED);

        $this->actingAs($permitter)
            ->from(route('fjp.show', $fjp))
            ->post(route('fjp.workflow', $fjp), ['action' => 'submit'])
            ->assertSessionHasNoErrors();

        $this->assertSame(PermitStatus::SUBMITTED, $fjp->fresh()->permit_status);
    }

    public function test_permitter_cannot_approve_a_fjp(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp(PermitStatus::SUBMITTED);

        $this->actingAs($permitter)
            ->post(route('fjp.workflow', $fjp), ['action' => 'approve'])
            ->assertForbidden();

        $this->assertSame(PermitStatus::SUBMITTED, $fjp->fresh()->permit_status);
    }

    public function test_user_without_fjp_update_permission_cannot_use_the_workflow(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->createFjp(PermitStatus::DRAFT);

        $this->actingAs($pic)
            ->post(route('fjp.workflow', $fjp), ['action' => 'submit'])
            ->assertForbidden();

        $this->assertSame(PermitStatus::DRAFT, $fjp->fresh()->permit_status);
    }

    public function test_show_exposes_capability_flags_and_status_history(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp(PermitStatus::DRAFT);

        $this->actingAs($permitter)
            ->post(route('fjp.workflow', $fjp), ['action' => 'submit'])
            ->assertSessionHasNoErrors();

        $admin = $this->user('admin@indosakti.com');

        $this->actingAs($admin)
            ->get(route('fjp.show', $fjp))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Fjp/Show')
                ->has('history')
                ->where('can.approve', true)
                ->where('can.reject', true)
                ->where('can.update', true)
                ->where('history.0.action', 'FJP_SUBMIT')
                ->where('history.0.user', $permitter->name));

        $this->actingAs($permitter)
            ->get(route('fjp.show', $fjp))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.approve', false)
                ->where('can.reject', false));
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function createFjp(PermitStatus $status): Fjp
    {
        return Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP Workflow '.uniqid(),
            'school_name' => 'Sekolah Uji',
            'npsn' => '20112345',
            'permit_status' => $status,
            'status' => 'draft',
        ]);
    }
}
