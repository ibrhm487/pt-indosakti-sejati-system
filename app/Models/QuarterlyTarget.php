<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuarterlyTarget extends Model
{
    protected $fillable = ['brand_id', 'year', 'quarter', 'start_month', 'target_cups'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'quarter' => 'integer',
            'start_month' => 'integer',
            'target_cups' => 'integer',
        ];
    }
}
