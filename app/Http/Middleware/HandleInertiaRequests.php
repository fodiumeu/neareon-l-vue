<?php

namespace App\Http\Middleware;

use App\Services\NavigationBadgeService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly NavigationBadgeService $badges,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $badgeCounts = function () use ($request): ?array {
            static $counts;

            if (! isset($counts)) {
                $counts = $request->user() !== null
                    ? $this->badges->countsFor($request->user())
                    : null;
            }

            return $counts;
        };
        $starterDefaults = [
            'app.name' => 'NEAREON',
            'app.project.tagline' => 'Regionale Social Web-App',
            'app.project.dashboard_title' => 'NEAREON Laravel Basis - Phase 0',
            'app.project.admin_label' => 'Admin',
        ];

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'branding' => [
                    'logo' => config('app.branding.logo'),
                ],
            ],
            'project' => [
                'showAdminArea' => config('app.project.show_admin_area'),
                'adminLabel' => config('app.project.admin_label'),
                'showAppearanceSettings' => config('app.project.show_appearance_settings'),
                'tagline' => config('app.project.tagline'),
                'welcomeTitle' => config('app.project.welcome_title'),
                'welcomeDescription' => config('app.project.welcome_description'),
                'dashboardTitle' => config('app.project.dashboard_title'),
                'dashboardDescription' => config('app.project.dashboard_description'),
                'hasStarterDefaults' => collect($starterDefaults)
                    ->contains(fn (string $value, string $key) => config($key) === $value),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'contactRequests' => [
                'pendingReceivedCount' => fn (): int => $badgeCounts()['pendingContactRequests'] ?? 0,
            ],
            'messages' => [
                'unreadCount' => fn (): int => $badgeCounts()['unreadMessages'] ?? 0,
            ],
            'notifications' => [
                'unreadCount' => fn (): int => $badgeCounts()['unreadNotifications'] ?? 0,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
