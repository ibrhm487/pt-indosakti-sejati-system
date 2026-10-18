<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'permit_request_id', 'category', 'version', 'original_name', 'path',
    'mime', 'size', 'uploaded_by',
])]
class PermitDocument extends Model
{
    public function permitRequest(): BelongsTo
    {
        return $this->belongsTo(PermitRequest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
