<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'omf_number', 'event_id', 'brand_id', 'issued_date', 'department', 'recipient', 'notes',
    'received_by_name', 'received_by_role', 'approved_by_name', 'approved_by_role',
    'operational_manager_by_name', 'operational_manager_by_role', 'recorded_by',
])]
class OutMaterialForm extends Model
{
    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OutMaterialFormItem::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function suggestNumber(?string $brandCode = null, ?CarbonInterface $date = null): string
    {
        return DocumentNumber::suggest(
            $brandCode,
            $date,
            fn (string $number) => static::where('omf_number', $number)->exists(),
        );
    }
}
