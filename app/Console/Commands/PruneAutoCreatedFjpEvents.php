<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Event;
use App\Services\AuditLogger;
use App\Support\EventOperationalData;
use Illuminate\Console\Command;

/**
 * Removes events that were auto-provisioned from an FJP before event creation
 * moved to the PIC. Only orphaned events (no PIC and no operational data) are
 * deleted so the FJP becomes available again in the "Event Baru" form.
 */
class PruneAutoCreatedFjpEvents extends Command
{
    protected $signature = 'fjp:prune-auto-events {--dry-run : Tampilkan event yang akan dihapus tanpa menghapus}';

    protected $description = 'Hapus event hasil auto-provision FJP yang belum memiliki PIC dan data operasional.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $eventIds = AuditLog::query()
            ->where('action', 'CREATE_EVENT_FROM_FJP')
            ->where('module', 'events')
            ->where('record_type', (new Event)->getMorphClass())
            ->pluck('record_id')
            ->unique()
            ->values();

        if ($eventIds->isEmpty()) {
            $this->info('Tidak ada event auto-provision FJP yang tercatat.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $kept = 0;

        foreach (Event::query()->whereIn('id', $eventIds)->get() as $event) {
            if ($event->pic_id !== null || EventOperationalData::exists($event)) {
                $this->line("  [KEEP] {$event->event_code} — masih memiliki PIC atau data operasional.");
                $kept++;

                continue;
            }

            if ($dryRun) {
                $this->line("  [WOULD DELETE] {$event->event_code} — {$event->event_name}");
                $deleted++;

                continue;
            }

            $old = [
                'event_code' => $event->event_code,
                'event_name' => $event->event_name,
                'status' => $event->status->value,
                'pic_id' => $event->pic_id,
            ];

            $event->teamMembers()->delete();
            $event->delete();

            AuditLogger::log('DELETE_AUTO_EVENT_FROM_FJP', 'events', $event, $old, [
                'reason' => 'Event auto-provision tanpa PIC dan data operasional dihapus; PIC membuat event dari FJP.',
            ]);

            $this->info("  [DELETED] {$event->event_code} — {$event->event_name}");
            $deleted++;
        }

        $this->newLine();
        $this->table(['Ringkasan', 'Jumlah'], [
            ['Dihapus', $deleted],
            ['Dipertahankan', $kept],
        ]);

        if ($dryRun) {
            $this->comment('Mode dry-run: tidak ada data yang dihapus.');
        }

        return self::SUCCESS;
    }
}
