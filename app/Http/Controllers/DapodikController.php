<?php

namespace App\Http\Controllers;

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
            'q' => ['required', 'string', 'min:5', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'jabodetabek' => ['nullable', 'boolean'],
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
            return response()->json([
                'data' => $dapo->search($q, $limit, $jabodetabek, $jenjang, $status),
                'source' => 'Dapodik public portal; verify records before operational use.',
            ]);
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'Layanan Dapodik sedang tidak tersedia. Silakan coba lagi.',
            ], 503);
        }
    }

    /**
     * School detail (address, coordinates, student count) for a single NPSN.
     */
    public function show(string $npsn, DapoApiService $dapo): JsonResponse
    {
        try {
            $detail = $dapo->detail($npsn);
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'Layanan Dapodik sedang tidak tersedia. Silakan coba lagi.',
            ], 503);
        }

        return response()->json([
            'data' => $detail,
            'source' => 'Dapodik public portal; verify records before operational use.',
        ], $detail ? 200 : 404);
    }
}
