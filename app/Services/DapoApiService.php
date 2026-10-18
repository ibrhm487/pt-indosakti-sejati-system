<?php

namespace App\Services;

use ErrorException;
use RuntimeException;

/**
 * Read-only client for the public Dapodik (Kemendikdasmen) school database
 * ("dapo"). Used to look up schools by name / NPSN and to pull their address,
 * coordinates and student count.
 *
 * NOTE: the portal is fronted by a WAF that blocks PHP/cURL TLS fingerprints
 * (403), so requests are made through PHP's OpenSSL streams instead, which the
 * WAF allows. The bearer token is served publicly by the portal via /env.js.
 */
class DapoApiService
{
    private string $baseUrl;

    private ?string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.dapo.base_url'), '/');
        $this->token = config('services.dapo.token') ?: null;
    }

    /**
     * Search schools by name or NPSN.
     *
     * @param  array<string>  $status
     * @return array<int, array<string, mixed>>
     */
    public function search(
        string $query,
        int $limit = 100,
        bool $jabodetabekOnly = false,
        ?string $jenjang = null,
        array $status = []
    ): array {
        $query = trim($query);

        if (mb_strlen($query) < 5) {
            return [];
        }

        $json = $this->get('/api/detail-sekolah/search', ['q' => $query]);
        $data = $json['data'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException('The Dapodik service returned an invalid search response.');
        }

        $rows = collect($data)
            ->filter(fn ($row) => is_array($row) && ! empty($row['npsn']))
            ->map(fn ($row) => [
                'npsn' => (string) $row['npsn'],
                'nama' => $row['nama'] ?? null,
                'jenjang' => $row['jenjang'] ?? null,
                'status_sekolah' => $row['status_sekolah'] ?? null,
                'provinsi' => $row['provinsi'] ?? null,
                'kabupaten' => $row['kabupaten'] ?? null,
                'kecamatan' => $row['kecamatan'] ?? null,
            ]);

        if ($jabodetabekOnly) {
            $rows = $rows->filter(fn ($row) => $this->isJabodetabek($row['provinsi'], $row['kabupaten']));
        }

        if ($jenjang !== null && $jenjang !== '') {
            $jenjangTarget = mb_strtoupper(trim($jenjang));
            $rows = $rows->filter(function ($row) use ($jenjangTarget) {
                $j = mb_strtoupper((string) ($row['jenjang'] ?? ''));

                return $j === $jenjangTarget;
            });
        }

        if ($status !== []) {
            $statusTargets = collect($status)
                ->map(fn ($s) => mb_strtoupper(trim((string) $s)))
                ->filter(fn ($s) => $s !== '')
                ->unique()
                ->values()
                ->all();

            if ($statusTargets !== []) {
                $rows = $rows->filter(function ($row) use ($statusTargets) {
                    $st = mb_strtoupper((string) ($row['status_sekolah'] ?? ''));
                    foreach ($statusTargets as $target) {
                        if ($target === 'ALL') {
                            return true;
                        }
                        if ($target === 'NEGERI' && ($st === 'NEGERI' || str_contains($st, 'NEGERI'))) {
                            return true;
                        }
                        if ($target === 'SWASTA' && ($st === 'SWASTA' || str_contains($st, 'SWASTA'))) {
                            return true;
                        }
                        if ($st === $target) {
                            return true;
                        }
                    }

                    return false;
                });
            }
        }

        return $rows->take($limit)->values()->all();
    }

    /**
     * School detail (address, coordinates, student count) for a single NPSN.
     *
     * @return array<string, mixed>|null
     */
    public function detail(string $npsn): ?array
    {
        $npsn = trim($npsn);

        if ($npsn === '') {
            return null;
        }

        $json = $this->get('/api/detail-sekolah', ['npsn' => $npsn], true);
        $data = $json['data'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException('The Dapodik service returned an invalid school response.');
        }

        $row = $data[0] ?? null;

        if (! is_array($row)) {
            return null;
        }

        $latitude = $this->number($row['lintang'] ?? null);
        $longitude = $this->number($row['bujur'] ?? null);

        return [
            'npsn' => (string) ($row['npsn'] ?? $npsn),
            'school_name' => $row['nama'] ?? null,
            'jenjang' => $row['jenjang'] ?? null,
            'status_sekolah' => $row['status_sekolah'] ?? null,
            'provinsi' => $row['provinsi'] ?? null,
            'kabupaten' => $row['kabupaten'] ?? null,
            'kecamatan' => $row['kecamatan'] ?? null,
            'address' => $this->formatAddress($row),
            'jumlah_siswa' => $this->number($row['pd'] ?? null),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'maps_url' => ($latitude !== null && $longitude !== null)
                ? "https://www.google.com/maps?q={$latitude},{$longitude}"
                : null,
        ];
    }

    /**
     * Perform a GET request via OpenSSL streams and decode the JSON body.
     *
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = [], bool $notFoundIsEmpty = false): array
    {
        $url = $this->baseUrl.$path;

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        $headers = ['Accept: application/json'];

        if ($this->token) {
            $headers[] = 'Authorization: Bearer '.$this->token;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $body = file_get_contents($url, false, $context);
        } catch (ErrorException $exception) {
            throw new RuntimeException('The Dapodik service could not be reached.', 0, $exception);
        } finally {
            restore_error_handler();
        }

        if ($body === false) {
            throw new RuntimeException('The Dapodik service returned no response.');
        }

        $status = null;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        if ($status === 404 && $notFoundIsEmpty) {
            return [];
        }

        if ($status === null || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Dapodik service returned an unsuccessful response.');
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The Dapodik service returned an invalid response.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function formatAddress(array $row): ?string
    {
        $street = trim((string) ($row['alamat_jalan'] ?? ''));
        $rt = trim((string) ($row['rt'] ?? ''));
        $rw = trim((string) ($row['rw'] ?? ''));
        $rtRw = collect([$rt !== '' ? "RT {$rt}" : null, $rw !== '' ? "RW {$rw}" : null])
            ->filter()
            ->implode('/');

        $parts = array_filter([
            $street,
            $rtRw,
            $row['desa_kelurahan'] ?? null,
            $row['kecamatan'] ?? null,
            $row['kabupaten'] ?? null,
            $row['provinsi'] ?? null,
            $row['kode_pos'] ?? null,
        ], fn ($p) => is_string($p) && trim($p) !== '');

        $address = implode(', ', array_map('trim', $parts));

        return $address !== '' ? $address : null;
    }

    private function number(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    private function isJabodetabek(?string $provinsi, ?string $kabupaten): bool
    {
        $provinsi = (string) $provinsi;
        $kabupaten = (string) $kabupaten;

        if (str_contains($provinsi, 'Jakarta')) {
            return true;
        }

        $areas = ['Kota Bogor', 'Kab. Bogor', 'Kota Depok', 'Kota Bekasi', 'Kab. Bekasi', 'Kota Tangerang', 'Kab. Tangerang', 'Tangerang Selatan'];

        foreach ($areas as $area) {
            if (str_contains($kabupaten, $area)) {
                return true;
            }
        }

        return false;
    }
}
