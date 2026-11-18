<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $receiptSlipItemType = 'App\\Models\\StockReceiptSlipItem';
        $stockRequestType = 'App\\Models\\StockRequest';

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('lot_code', 100)->nullable()->after('notes');
            $table->date('expired_date')->nullable()->after('lot_code');
        });

        DB::table('stock_movements')
            ->whereIn('reference_type', [
                $receiptSlipItemType,
                $stockRequestType,
            ])
            ->orderBy('id')
            ->chunkById(200, function ($movements) use ($receiptSlipItemType, $stockRequestType): void {
                $mrsItemIds = $movements
                    ->where('reference_type', $receiptSlipItemType)
                    ->pluck('reference_id')
                    ->unique();
                $requestIds = $movements
                    ->where('reference_type', $stockRequestType)
                    ->pluck('reference_id')
                    ->unique();

                $mrsItems = DB::table('stock_receipt_slip_items')
                    ->whereIn('id', $mrsItemIds)
                    ->get(['id', 'lot_code', 'expired_date'])
                    ->keyBy('id');
                $requestItems = DB::table('stock_request_items')
                    ->whereIn('stock_request_id', $requestIds)
                    ->get(['stock_request_id', 'product_id', 'lot_no'])
                    ->groupBy(fn (object $item): string => $item->stock_request_id.'-'.$item->product_id);

                foreach ($movements as $movement) {
                    $lotCode = null;
                    $expiredDate = null;

                    if ($movement->reference_type === $receiptSlipItemType) {
                        $item = $mrsItems->get($movement->reference_id);
                        $lotCode = $item?->lot_code;
                        $expiredDate = $item?->expired_date;
                    } else {
                        $items = $requestItems->get($movement->reference_id.'-'.$movement->product_id);

                        if ($items?->count() === 1) {
                            $lotCode = $items->first()->lot_no;
                        }
                    }

                    if ($lotCode !== null || $expiredDate !== null) {
                        DB::table('stock_movements')
                            ->where('id', $movement->id)
                            ->update([
                                'lot_code' => $lotCode,
                                'expired_date' => $expiredDate,
                            ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['lot_code', 'expired_date']);
        });
    }
};
