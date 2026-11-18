<?php

namespace App\Models;

use App\Enums\RoleSlug;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'phone', 'is_active', 'active_role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function activeRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'active_role_id');
    }

    public function assignedEvents(): HasMany
    {
        return $this->hasMany(EventTeamMember::class);
    }

    public function personnel(): HasOne
    {
        return $this->hasOne(Personnel::class);
    }

    public function permissions(): Collection
    {
        $roleIds = $this->roles->pluck('id');

        return Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
            ->pluck('slug');
    }

    public function hasRole(RoleSlug|string $slug): bool
    {
        $slug = $slug instanceof RoleSlug ? $slug->value : $slug;

        return $this->roles->contains('slug', $slug);
    }

    public function hasAnyRole(array $slugs): bool
    {
        $values = array_map(fn ($slug) => $slug instanceof RoleSlug ? $slug->value : $slug, $slugs);

        return $this->roles->whereIn('slug', $values)->isNotEmpty();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->contains($slug);
    }

    public function isSystemAdmin(): bool
    {
        return $this->hasRole(RoleSlug::SYSTEM_ADMIN);
    }

    public function isDeveloper(): bool
    {
        return $this->hasRole(RoleSlug::DEVELOPER);
    }

    /**
     * Roles that bypass role-based data scoping (development and system admin).
     */
    public function hasFullAccess(): bool
    {
        return $this->isSystemAdmin() || $this->isDeveloper();
    }

    public function activeRoleSlug(): ?string
    {
        return $this->activeRole?->slug ?? $this->roles->first()?->slug;
    }
}
