<?php

namespace App\Http\Controllers;

use App\Enums\VenueStatus;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canApproveVenue = $user->hasPermission('venues.approve');

        abort_unless($canApproveVenue, 403);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $status = $filters['status'] ?? 'pending';
        $selectedDate = $filters['date'] ?? today()->toDateString();
        $items = $this->venues($status, $canApproveVenue, $selectedDate);

        return Inertia::render('Verification/Index', [
            'items' => $items,
            'status' => $status,
            'selectedDate' => $selectedDate,
        ]);
    }

    private function venues(string $status, bool $canApprove, string $selectedDate): mixed
    {
        return Venue::query()
            ->where(
                'status',
                $status === 'pending' ? VenueStatus::PENDING : VenueStatus::APPROVED,
            )
            ->where(fn (Builder $query) => $query
                ->whereHas('fjps', fn (Builder $fjps) => $fjps->whereDate('event_date', $selectedDate))
                ->orWhereHas('events', fn (Builder $events) => $events->whereDate('event_date', $selectedDate)))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Venue $venue) => [
                'id' => $venue->id,
                'type' => 'venue',
                'name' => $venue->name,
                'code' => $venue->npsn,
                'venue' => $venue->city,
                'status' => $status === 'pending' ? 'Menunggu verifikasi' : 'Disetujui',
                'date' => $selectedDate,
                'url' => null,
                'can_approve' => false,
                'can_reject' => $canApprove && $status === 'pending',
                'can_approve_venue' => $canApprove && $status === 'pending',
                'fjps' => [],
            ]);
    }
}
