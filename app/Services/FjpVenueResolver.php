<?php

namespace App\Services;

use App\Enums\VenueStatus;
use App\Models\Fjp;
use App\Models\Venue;
use Illuminate\Support\Str;

class FjpVenueResolver
{
    /**
     * Resolve the venue for an FJP, creating a school venue from the FJP's
     * Dapodik data when none is linked yet.
     */
    public function ensureVenue(Fjp $fjp): ?Venue
    {
        if ($fjp->venue_id) {
            $venue = Venue::find($fjp->venue_id);

            if ($venue) {
                $this->fillMissingLocation($venue, $fjp);

                return $venue;
            }
        }

        $schoolName = trim((string) $fjp->school_name);
        $npsn = trim((string) $fjp->npsn);

        if ($schoolName === '' && $npsn === '') {
            return null;
        }

        $venue = $this->findVenue($npsn, $schoolName)
            ?? Venue::create([
                'name' => $schoolName !== '' ? $schoolName : 'Sekolah '.$npsn,
                'npsn' => $npsn !== '' ? $npsn : null,
                'is_school' => true,
                'address' => $fjp->address,
                'city' => $fjp->city,
                'province' => $fjp->province,
                'status' => VenueStatus::PENDING,
            ]);

        $this->fillMissingLocation($venue, $fjp);

        $fjp->update(['venue_id' => $venue->id]);

        return $venue;
    }

    /**
     * Copy the FJP's location into its linked venue when the venue is missing
     * those values. Existing (approved) venue data is never overwritten.
     */
    public function syncLocation(Fjp $fjp): void
    {
        if (! $fjp->venue_id) {
            return;
        }

        $venue = Venue::find($fjp->venue_id);

        if ($venue) {
            $this->fillMissingLocation($venue, $fjp);
        }
    }

    private function fillMissingLocation(Venue $venue, Fjp $fjp): void
    {
        $venue->fill([
            'address' => $venue->address ?: $fjp->address,
            'city' => $venue->city ?: $fjp->city,
            'province' => $venue->province ?: $fjp->province,
        ]);

        if ($venue->isDirty()) {
            $venue->save();
        }
    }

    private function findVenue(string $npsn, string $schoolName): ?Venue
    {
        if ($npsn !== '') {
            $venue = Venue::query()->where('npsn', $npsn)->first();

            if ($venue) {
                return $venue;
            }
        }

        if ($schoolName !== '') {
            return Venue::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($schoolName)])
                ->first();
        }

        return null;
    }
}
