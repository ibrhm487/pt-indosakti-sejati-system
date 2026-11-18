<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'mrs_number', 'receipt_type', 'do_number', 'shipper', 'supplier', 'received_date',
    'release_date', 'release_time', 'authorized_by', 'quantity_released', 'quantity_assurance',
    'received_by_name', 'received_by_role', 'approved_by_name', 'approved_by_role',
    'operational_manager_by_name', 'operational_manager_by_role',
    'quality_assurance_by_name', 'quality_assurance_by_role', 'notes', 'document_status', 'recorded_by',
])]
class StockReceiptSlip extends Model
{
    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'release_date' => 'date',
            'quantity_released' => 'integer',
            'quantity_assurance' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReceiptSlipItem::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function generateNumber(): string
    {
        return static::suggestNumber();
    }

    public static function suggestNumber(?string $brandCode = null, ?CarbonInterface $date = null): string
    {
        return DocumentNumber::suggest(
            $brandCode,
            $date,
            fn (string $number) => static::where('mrs_number', $number)->exists(),
        );
    }
}
