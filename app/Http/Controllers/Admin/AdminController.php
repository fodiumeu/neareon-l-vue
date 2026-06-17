<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * Display a minimal admin overview with existing users.
     */
    public function index(): Response
    {
        return Inertia::render('Admin', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'role'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Display a minimal read-only user detail page.
     */
    public function show(User $user): Response
    {
        return Inertia::render('admin/Users/Show', [
            'user' => $user->only([
                'id',
                'name',
                'email',
                'role',
                'email_verified_at',
                'created_at',
                'updated_at',
            ]),
        ]);
    }

    /**
     * Display the admin option catalog overview.
     */
    public function options(): Response
    {
        return Inertia::render('admin/Options/Index');
    }

    /**
     * Update a user's role with minimal admin-only safety rules.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ], [
            'role.required' => 'Bitte wähle eine Rolle aus.',
            'role.enum' => 'Bitte wähle eine gültige Rolle aus.',
        ]);
        $targetRole = UserRole::from($validated['role']);

        if (
            $user->canAccessAdmin()
            && $targetRole->level() < UserRole::Admin->level()
            && User::query()
                ->whereIn('role', [UserRole::Admin->value, UserRole::Owner->value])
                ->count() === 1
        ) {
            return back()->with('error', 'Die letzte administrative Rolle kann nicht entfernt werden.');
        }

        if ($request->user()->is($user)) {
            return back()->with('error', 'Du kannst deine eigene Rolle nicht ändern.');
        }

        $user->update([
            'role' => $validated['role'],
        ]);

        return to_route('admin.users.show', $user)
            ->with('success', 'Die Benutzerrolle wurde aktualisiert.');
    }

    /**
     * Display a minimal read-only system status page.
     */
    public function system(): Response
    {
        $defaults = [
            'app_name' => 'NEAREON',
            'tagline' => 'Regionale Social Web-App',
            'welcome_title' => 'NEAREON',
            'dashboard_title' => 'NEAREON Laravel Basis - Phase 0',
            'admin_label' => 'Admin',
        ];

        return Inertia::render('admin/System', [
            'system' => [
                'app_name' => config('app.name'),
                'app_logo' => config('app.branding.logo'),
                'admin_label' => config('app.project.admin_label'),
                'tagline' => config('app.project.tagline'),
                'welcome_title' => config('app.project.welcome_title'),
                'dashboard_title' => config('app.project.dashboard_title'),
                'show_admin_area' => config('app.project.show_admin_area'),
                'show_appearance_settings' => config('app.project.show_appearance_settings'),
                'environment' => app()->environment(),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'default_fields' => collect($defaults)
                    ->filter(fn (string $value, string $key) => config(match ($key) {
                        'app_name' => 'app.name',
                        'tagline' => 'app.project.tagline',
                        'welcome_title' => 'app.project.welcome_title',
                        'dashboard_title' => 'app.project.dashboard_title',
                        'admin_label' => 'app.project.admin_label',
                    }) === $value)
                    ->keys()
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Display a minimal read-only project overview page.
     */
    public function project(): Response
    {
        return Inertia::render('admin/Project', [
            'overview' => [
                'appName' => config('app.name'),
                'logo' => config('app.branding.logo'),
                'adminLabel' => config('app.project.admin_label'),
                'tagline' => config('app.project.tagline'),
                'showAdminArea' => config('app.project.show_admin_area'),
                'showAppearanceSettings' => config('app.project.show_appearance_settings'),
            ],
        ]);
    }
}
