<?php

namespace App\Console\Commands\Warehouse;

use Database\Seeders\WarehouseTrainingDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('warehouse:replace-training-data {--apply : Ganti seluruh data warehouse yang ada} {--confirm= : Ketik REPLACE-WAREHOUSE-DATA:<nama-database> untuk melanjutkan}')]
#[Description('Ganti data warehouse lokal dengan data latihan sintetis.')]
class ReplaceWithTrainingData extends Command
{
    public function handle(WarehouseTrainingDataSeeder $seeder): int
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
        $confirmation = "REPLACE-WAREHOUSE-DATA:{$database}";

        if ($this->option('confirm') !== $confirmation) {
            $this->error("Konfirmasi tidak cocok. Untuk database {$database}, ketik --confirm={$confirmation}.");

            return self::FAILURE;
        }

        $this->warn("Seluruh data transaksi dan produk warehouse pada database [{$database}] akan diganti.");

        $counts = $seeder->replace($confirmation);

        $this->table(['Jenis data', 'Jumlah'], [
            ['Produk latihan', $counts['products']],
            ['MRS latihan', $counts['mrs']],
            ['MRV latihan', $counts['mrv']],
            ['Pergerakan stok', $counts['stock_movements']],
            ['Retur', $counts['stock_returns']],
        ]);
        $this->info('Data latihan berhasil dibuat. Pengguna, master brand/kategori, event, dan audit log lama dipertahankan.');

        return self::SUCCESS;
    }
}
