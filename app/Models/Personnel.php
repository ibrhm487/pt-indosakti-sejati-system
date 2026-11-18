<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'role', 'phone', 'is_active', 'user_id'])]
class Personnel extends Model
{
    protected $table = 'personnel';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EventTeamMember::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
