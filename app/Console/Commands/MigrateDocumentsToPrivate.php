<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\FjpAttachment;
use App\Models\KitchenReport;
use App\Models\PermitDocument;
use App\Models\QuickReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Copies every document that still lives on the public disk to the private
 * (local) disk. Source files are never deleted here — verify the copies, then
 * remove the public originals manually once confirmed.
 */
class MigrateDocumentsToPrivate extends Command
{
    protected $signature = 'documents:migrate-to-private {--dry-run : List what would be copied without writing anything}';

    protected $description = 'Salin dokumen dari disk publik ke disk privat (storage/app/private). File sumber TIDAK dihapus.';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $local = Storage::disk('local');
        $dryRun = (bool) $this->option('dry-run');

        $paths = $this->collectPaths();

        if ($paths === []) {
            $this->info('Tidak ada dokumen yang tercatat di database.');

            return self::SUCCESS;
        }

        $copied = 0;
        $skipped = 0;
        $missing = 0;
        $failed = 0;

        foreach ($paths as $label => $path) {
            if (! $public->exists($path)) {
                if ($local->exists($path)) {
                    $this->line("  [SKIP] {$label}: sudah berada di disk privat.");
                    $skipped++;

                    continue;
                }

                $this->warn("  [MISSING] {$label}: file tidak ditemukan di disk publik maupun privat ({$path}).");
                $missing++;

                continue;
            }

            if ($local->exists($path)) {
                if ($public->size($path) === $local->size($path)) {
                    $this->line("  [SKIP] {$label}: salinan privat sudah ada dengan ukuran sama.");
                    $skipped++;

                    continue;
                }

                $this->warn("  [CONFLICT] {$label}: file privat sudah ada tetapi ukurannya berbeda ({$path}). Tidak disalin.");
                $failed++;

                continue;
            }

            if ($dryRun) {
                $this->line("  [WOULD COPY] {$label}: {$path} ({$public->size($path)} bytes)");
                $copied++;

                continue;
            }

            try {
                $stream = $public->readStream($path);

                if ($stream === null) {
                    throw new \RuntimeException('readStream mengembalikan null.');
                }

                $written = $local->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (! $written || ! $local->exists($path) || $local->size($path) !== $public->size($path)) {
                    throw new \RuntimeException('Verifikasi ukuran file hasil salinan gagal.');
                }

                $this->info("  [COPIED] {$label}: {$path}");
                $copied++;
            } catch (\Throwable $e) {
                $this->error("  [FAILED] {$label}: {$path} — {$e->getMessage()}");
                $local->delete($path);
                $failed++;
            }
        }

        $this->newLine();
        $this->table(['Ringkasan', 'Jumlah'], [
            ['Disalin', $copied],
            ['Dilewati', $skipped],
            ['Tidak ditemukan', $missing],
            ['Gagal', $failed],
        ]);

        if (! $dryRun && $copied > 0) {
            $this->newLine();
            $this->comment('File sumber di disk publik TIDAK dihapus. Setelah diverifikasi manual, hapus isi storage/app/public/{fjp,permits,documents,quick-reports,kitchen} secara manual.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, string> label => path
     */
    private function collectPaths(): array
    {
        $entries = [];

        foreach (FjpAttachment::query()->get(['id', 'path']) as $a) {
            $entries["FJP attachment #{$a->id}"] = $a->path;
        }

        foreach (PermitDocument::query()->get(['id', 'path']) as $d) {
            $entries["Dokumen perizinan #{$d->id}"] = $d->path;
        }

        foreach (Document::query()->get(['id', 'path']) as $d) {
            $entries["Dokumen #{$d->id}"] = $d->path;
        }

        foreach (QuickReport::query()->whereNotNull('photo_path')->get(['id', 'photo_path']) as $q) {
            $entries["Foto quick report #{$q->id}"] = $q->photo_path;
        }

        foreach (KitchenReport::query()->get(['id', 'photo_path', 'washing_photo_path']) as $k) {
            if ($k->photo_path) {
                $entries["Foto masak kitchen #{$k->id}"] = $k->photo_path;
            }

            if ($k->washing_photo_path) {
                $entries["Foto cuci jug #{$k->id}"] = $k->washing_photo_path;
            }
        }

        return array_unique($entries, SORT_REGULAR);
    }
}
