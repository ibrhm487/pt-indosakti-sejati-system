<?php

namespace App\Http\Controllers;

use App\Models\Fjp;
use App\Models\Venue;
use App\Services\DapoApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DapodikController extends Controller
{
    /**
     * Search schools by name / NPSN (proxied to the Dapodik API).
     */
    public function search(Request $request, DapoApiService $dapo): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'jabodetabek' => ['nullable'],
            'jakarta' => ['nullable'],
            'jenjang' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable'],
            'status.*' => ['string', 'max:20'],
        ]);

        $q = $data['q'];
        $limit = isset($data['limit']) ? (int) $data['limit'] : 200;
        $jabodetabek = $request->boolean('jabodetabek', false);
        $jenjang = isset($data['jenjang']) ? strtoupper(trim($data['jenjang'])) : null;
        $status = [];

        if ($request->has('status')) {
            $statusInput = $request->input('status');
            if (is_array($statusInput)) {
                $status = collect($statusInput)
                    ->map(fn ($s) => strtoupper(trim((string) $s)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            } elseif (is_string($statusInput) && $statusInput !== '') {
                $status = collect(explode(',', $statusInput))
                    ->map(fn ($s) => strtoupper(trim($s)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        try {
            $schools = $dapo->search(
                $q,
                $limit,
                $jabodetabek,
                $jenjang,
                $status,
                $request->boolean('jakarta'),
            );

            if ($schools === []) {
                foreach ($this->searchVariants($q) as $variant) {
                    $schools = $dapo->search(
                        $variant,
                        $limit,
                        $jabodetabek,
                        $jenjang,
                        $status,
                        $request->boolean('jakarta'),
                    );

                    if ($schools !== []) {
                        break;
                    }
                }
            }

            if ($schools === []) {
                $schools = $this->searchLocalSchools($q, $limit);
            }

            return response()->json([
                'data' => $schools,
                'source' => 'Dapodik public portal; verify records before operational use.',
            ]);
        } catch (RuntimeException $exception) {
            $schools = $this->searchLocalSchools($q, $limit);

            if ($schools !== []) {
                return response()->json([
                    'data' => $schools,
                    'source' => 'Data sekolah tersimpan lokal karena Dapodik sedang tidak tersedia.',
                ]);
            }

            return response()->json([
                'message' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Layanan Dapodik sedang tidak tersedia. Silakan coba lagi.',
            ], 503);
        }
    }

    /** @return array<int, string> */
    private function searchVariants(string $query): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
        $normalized = preg_replace(
            '/^(sdn|smpn|mtsn|mi|sd|smp|sma|smk|tk)\s+/iu',
            '',
            $query,
        ) ?? $query;

        return collect([
            $normalized,
            str_replace(['NEGERI ', 'Negeri '], '', $query),
        ])
            ->filter(fn (string $variant): bool => $variant !== '' && mb_strtolower($variant) !== mb_strtolower($query))
            ->unique(fn (string $variant): string => mb_strtolower($variant))
            ->take(3)
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function searchLocalSchools(string $query, int $limit): array
    {
        $query = trim($query);
        $normalized = preg_replace(
            '/^(sdn|smpn|mtsn|mi|sd|smp|sma|smk|tk)\s+/iu',
            '',
            $query,
        ) ?? $query;

        $venues = Venue::query()
            ->where(function ($builder) use ($normalized, $query): void {
                $builder->where('name', 'like', "%{$normalized}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('npsn', 'like', "%{$normalized}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['name', 'npsn', 'city', 'province']);

        $fjps = Fjp::query()
            ->where(function ($builder) use ($normalized, $query): void {
                $builder->where('school_name', 'like', "%{$normalized}%")
                    ->orWhere('school_name', 'like', "%{$query}%")
                    ->orWhere('npsn', 'like', "%{$normalized}%");
            })
            ->orderBy('school_name')
            ->limit($limit)
            ->get(['school_name', 'npsn', 'city', 'province', 'kecamatan']);

        return collect()
            ->merge($venues->map(fn (Venue $venue): array => [
                'npsn' => (string) $venue->npsn,
                'nama' => $venue->name,
                'jenjang' => null,
                'status_sekolah' => null,
                'provinsi' => $venue->province,
                'kabupaten' => $venue->city,
                'kecamatan' => null,
            ]))
            ->merge($fjps->map(fn (Fjp $fjp): array => [
                'npsn' => (string) $fjp->npsn,
                'nama' => $fjp->school_name,
                'jenjang' => null,
                'status_sekolah' => null,
                'provinsi' => $fjp->province,
                'kabupaten' => $fjp->city,
                'kecamatan' => $fjp->kecamatan,
            ]))
            ->filter(fn (array $row): bool => $row['npsn'] !== '' && $row['nama'])
            ->unique('npsn')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * School detail (address, coordinates, student count) for a single NPSN.
     */
    public function show(string $npsn, DapoApiService $dapo): JsonResponse
    {
        try {
            $detail = $dapo->detail($npsn);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Layanan Dapodik sedang tidak tersedia. Silakan coba lagi.',
            ], 503);
        }

        return response()->json([
            'data' => $detail,
            'source' => 'Dapodik public portal; verify records before operational use.',
        ], $detail ? 200 : 404);
    }
}
