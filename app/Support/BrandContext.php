<?php

namespace App\Support;

use App\Models\Brand;
use Illuminate\Http\Request;

/**
 * Session-backed "active brand" used to keep brand grouping consistent across
 * Dashboard, Events, FJP, Reports and Inventory. An explicit `brand_id` query
 * parameter always wins; `all` / `0` / an empty value clears the filter.
 */
class BrandContext
{
    public const SESSION_KEY = 'brand_context_id';

    public static function id(): ?int
    {
        $id = session(self::SESSION_KEY);

        if (! $id) {
            return null;
        }

        $exists = Brand::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->exists();

        return $exists ? (int) $id : null;
    }

    public static function brand(): ?Brand
    {
        $id = self::id();

        return $id ? Brand::query()->find($id) : null;
    }

    public static function set(?int $id): void
    {
        if ($id === null) {
            self::clear();

            return;
        }

        session([self::SESSION_KEY => $id]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Resolve the brand filter for the current request.
     *
     * Priority: explicit query parameter (even a clearing value) then session.
     */
    public static function resolve(Request $request): ?int
    {
        if ($request->has('brand_id')) {
            $value = $request->query('brand_id');

            if ($value === null || $value === '' || $value === 'all' || (int) $value === 0) {
                return null;
            }

            return (int) $value;
        }

        return self::id();
    }
}
