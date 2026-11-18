<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Support\BrandContext;
use App\Support\EventAccess;
use Illuminate\Http\Request;

class BrandContextController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        $brandId = $data['brand_id'] ?? null;

        if ($brandId !== null) {
            $user = $request->user();

            $allowed = Brand::query()
                ->whereKey($brandId)
                ->where('is_active', true)
                ->when(
                    $user->hasPermission('events.view') && ! $user->hasFullAccess(),
                    fn ($query) => $query->whereHas(
                        'events',
                        fn ($events) => EventAccess::scope($events, $user, 'id'),
                    ),
                )
                ->exists();

            if (! $allowed) {
                return back()->with('error', 'Brand tidak tersedia untuk akun Anda.');
            }
        }

        BrandContext::set($brandId);

        return back();
    }
}
