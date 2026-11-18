<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LogReader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogViewerController extends Controller
{
    public function index(Request $request, LogReader $reader): Response
    {
        $level = $request->string('level')->toString() ?: 'all';
        $search = trim($request->string('search')->toString());
        $lines = max(50, min($request->integer('lines', 300), 2000));

        $entries = $reader->entries();
        $levels = array_values(array_unique(array_map(fn (array $entry) => $entry['level'], $entries)));
        sort($levels);

        $filtered = array_values(array_filter($entries, function (array $entry) use ($level, $search) {
            if ($level !== 'all' && $entry['level'] !== $level) {
                return false;
            }

            if ($search !== '' && stripos($entry['message'], $search) === false) {
                return false;
            }

            return true;
        }));

        $total = count($filtered);
        $filtered = array_reverse(array_slice($filtered, -$lines));

        return Inertia::render('Admin/Logs/Index', [
            'entries' => $filtered,
            'levels' => $levels,
            'total' => $total,
            'filters' => [
                'level' => $level,
                'search' => $search,
                'lines' => $lines,
            ],
            'log' => [
                'exists' => $reader->exists(),
                'size' => $reader->size(),
                'modified' => $reader->lastModified(),
            ],
        ]);
    }

    public function download(LogReader $reader)
    {
        abort_unless($reader->exists(), 404, 'Log belum tersedia.');

        return response()->download($reader->path(), 'laravel-'.now()->format('Ymd-His').'.log');
    }
}
