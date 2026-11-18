<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Support\BrandContext;
use App\Support\EventAccess;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'roles' => $user->roles->map(fn ($role) => [
                        'id' => $role->id,
                        'slug' => $role->slug,
                        'name' => $role->name,
                    ])->values(),
                    'active_role' => $user->activeRole ? [
                        'slug' => $user->activeRole->slug,
                        'name' => $user->activeRole->name,
                    ] : null,
                ] : null,
                'permissions' => $user ? $user->permissions()->values() : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'eventBrands' => fn () => $user && $user->hasPermission('events.view')
                ? Brand::query()
                    ->select('id', 'name', 'slug')
                    ->where('is_active', true)
                    ->whereHas('events', fn ($query) => EventAccess::scope($query, $user, 'id'))
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Brand $brand) => [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'slug' => $brand->slug,
                    ])
                    ->values()
                    ->all()
                : [],
            'brandOptions' => fn () => $user
                ? Brand::query()
                    ->select('id', 'name', 'slug')
                    ->where('is_active', true)
                    ->when(
                        $user->hasPermission('events.view') && ! $user->hasFullAccess(),
                        fn ($query) => $query->whereHas(
                            'events',
                            fn ($events) => EventAccess::scope($events, $user, 'id'),
                        ),
                    )
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Brand $brand) => [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'slug' => $brand->slug,
                    ])
                    ->values()
                    ->all()
                : [],
            'brandContext' => function () use ($user) {
                if (! $user) {
                    return null;
                }

                $brand = BrandContext::brand();

                if (! $brand) {
                    return null;
                }

                $allowed = Brand::query()
                    ->whereKey($brand->id)
                    ->where('is_active', true)
                    ->when(
                        $user->hasPermission('events.view') && ! $user->hasFullAccess(),
                        fn ($query) => $query->whereHas(
                            'events',
                            fn ($events) => EventAccess::scope($events, $user, 'id'),
                        ),
                    )
                    ->exists();

                return $allowed ? [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                ] : null;
            },
            'app' => [
                'name' => config('app.name'),
            ],
        ];
    }
}
