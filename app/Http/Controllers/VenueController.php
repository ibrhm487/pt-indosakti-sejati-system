<?php

namespace App\Http\Controllers;

use App\Enums\VenueStatus;
use App\Models\Venue;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('venues.view'), 403);

        $selectedDate = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ])['date'] ?? today()->toDateString();

        $venues = Venue::query()
            ->with('approvedBy:id,name')
            ->withCount([
                'fjps' => fn (Builder $query) => $query->whereDate('event_date', $selectedDate),
                'events' => fn (Builder $query) => $query->whereDate('event_date', $selectedDate),
            ])
            ->where(fn (Builder $query) => $query
                ->whereHas('fjps', fn (Builder $fjps) => $fjps->whereDate('event_date', $selectedDate))
                ->orWhereHas('events', fn (Builder $events) => $events->whereDate('event_date', $selectedDate)))
            ->orderByRaw('case when status = ? then 0 else 1 end', [VenueStatus::PENDING->value])
            ->orderBy('name')
            ->get()
            ->map(fn (Venue $venue) => [
                'id' => $venue->id,
                'name' => $venue->name,
                'npsn' => $venue->npsn,
                'is_school' => $venue->is_school,
                'city' => $venue->city,
                'province' => $venue->province,
                'status' => $venue->status->value,
                'status_label' => $venue->status->label(),
                'approved_by' => $venue->approvedBy?->name,
                'approved_at' => $venue->approved_at?->toIso8601String(),
                'fjps_count' => $venue->fjps_count,
                'events_count' => $venue->events_count,
            ]);

        return Inertia::render('Venues/Index', [
            'venues' => $venues,
            'selectedDate' => $selectedDate,
            'pendingCount' => $venues->where('status', VenueStatus::PENDING->value)->count(),
            'canApprove' => $request->user()->hasPermission('venues.approve'),
            'canCreate' => $request->user()->hasPermission('venues.approve'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('venues.approve'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_school' => ['boolean'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['is_school'] = $request->boolean('is_school');
        $data['status'] = VenueStatus::APPROVED;
        $data['approved_by'] = $request->user()->id;
        $data['approved_at'] = now();

        $venue = Venue::create($data);

        AuditLogger::log('CREATE_VENUE', 'venues', $venue, null, [
            'name' => $venue->name,
            'is_school' => $venue->is_school,
            'city' => $venue->city,
            'status' => $venue->status->value,
        ]);

        return back()->with('success', 'Lokasi berhasil ditambahkan dan langsung tersedia di form event.');
    }

    public function approve(Request $request, Venue $venue)
    {
        abort_unless($request->user()->hasPermission('venues.approve'), 403);

        if ($venue->status === VenueStatus::APPROVED) {
            return back()->with('info', 'Lokasi sudah disetujui.');
        }

        if ($venue->status === VenueStatus::REJECTED) {
            throw ValidationException::withMessages([
                'venue' => 'Venue yang sudah ditolak tidak dapat disetujui kembali.',
            ]);
        }

        $venue->update([
            'status' => VenueStatus::APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        AuditLogger::log(
            'APPROVE_VENUE',
            'venues',
            $venue,
            ['status' => VenueStatus::PENDING->value],
            ['status' => VenueStatus::APPROVED->value],
        );

        return back()->with('success', 'Lokasi disetujui dan kini tersedia di pilihan venue event.');
    }

    public function reject(Request $request, Venue $venue)
    {
        abort_unless($request->user()->hasPermission('venues.approve'), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if ($venue->status !== VenueStatus::PENDING) {
            throw ValidationException::withMessages([
                'venue' => 'Hanya venue yang belum disetujui yang dapat ditolak.',
            ]);
        }

        $venue->update([
            'status' => VenueStatus::REJECTED,
            'rejection_reason' => $data['reason'],
        ]);

        AuditLogger::log('REJECT_VENUE', 'venues', $venue, [
            'status' => VenueStatus::PENDING->value,
        ], [
            'status' => VenueStatus::REJECTED->value,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Venue ditolak.');
    }
}
