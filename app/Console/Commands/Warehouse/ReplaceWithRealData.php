<?php

namespace App\Console\Commands\Warehouse;

use Database\Seeders\WarehouseRealDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('warehouse:replace-real-data {--apply : Ganti seluruh data warehouse yang ada} {--confirm= : Ketik REPLACE-WAREHOUSE-REAL-DATA:<nama-database> untuk melanjutkan}')]
#[Description('Ganti data warehouse lokal dengan dataset operasional baru.')]
class ReplaceWithRealData extends Command
{
    public function handle(WarehouseRealDataSeeder $seeder): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Penggantian data warehouse hanya diizinkan pada environment local atau testing.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->error('Tidak ada perubahan. Tambahkan --apply dan konfirmasi nama database untuk melanjutkan.');

            return self::FAILURE;
        }

        $database = DB::connection()->getDatabaseName();
        $confirmation = "REPLACE-WAREHOUSE-REAL-DATA:{$database}";

        if ($this->option('confirm') !== $confirmation) {
            $this->error("Konfirmasi tidak cocok. Untuk database {$database}, ketik --confirm={$confirmation}.");

            return self::FAILURE;
        }

        $this->warn("Seluruh data transaksi dan produk warehouse pada database [{$database}] akan diganti.");

        $counts = $seeder->replace($confirmation);

        $this->table(['Jenis data', 'Jumlah'], [
            ['Produk', $counts['products']],
            ['MRS', $counts['mrs']],
            ['MRV', $counts['mrv']],
            ['OMF', $counts['omf']],
            ['Pergerakan stok', $counts['stock_movements']],
        ]);
        $this->info('Data operasional berhasil dibuat. Pengguna, master brand/kategori, event, dan audit log lama dipertahankan.');

        return self::SUCCESS;
    }
}
