<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fjp_id', 'category', 'original_name', 'path', 'mime', 'size', 'uploaded_by'])]
class FjpAttachment extends Model
{
    public function fjp(): BelongsTo
    {
        return $this->belongsTo(Fjp::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
