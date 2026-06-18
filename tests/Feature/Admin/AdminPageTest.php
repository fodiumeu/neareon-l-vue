<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Middleware\EnsureOnboardingIsComplete;
use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware(EnsureOnboardingIsComplete::class);
});

uses(RefreshDatabase::class);

function completeOnboardingFor(User $user): Profile
{
    return Profile::factory()->for($user)->create();
}

test('guests are redirected to the login page for the admin area', function () {
    $this->get('/admin')
        ->assertRedirect(route('login'));
});

test('guests are redirected to the login page for admin user details', function () {
    $user = User::factory()->create();

    $this->get("/admin/users/{$user->id}")
        ->assertRedirect(route('login'));
});

test('guests are redirected to the login page for the admin project overview', function () {
    $this->get('/admin/project')
        ->assertRedirect(route('login'));
});

test('guests are redirected to the login page for the admin system status page', function () {
    $this->get('/admin/system')
        ->assertRedirect(route('login'));
});

test('guests are redirected to the login page for admin role updates', function () {
    $user = User::factory()->create();

    $this->patch("/admin/users/{$user->id}/role", [
        'role' => 'admin',
    ])->assertRedirect(route('login'));
});

test('guests are redirected from admin option pages', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
})->with([
    '/admin/options',
    '/admin/options/languages',
    '/admin/options/interests',
]);

test('members cannot access the admin area', function () {
    $user = User::factory()->create();
    completeOnboardingFor($user);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('moderators cannot access the admin area', function () {
    $moderator = User::factory()->moderator()->create();
    completeOnboardingFor($moderator);

    $this->actingAs($moderator)
        ->get('/admin')
        ->assertForbidden();
});

test('members and moderators cannot access admin option pages', function (string $factoryState, string $uri) {
    $userFactory = User::factory();
    $user = $factoryState === 'moderator'
        ? $userFactory->moderator()->create()
        : $userFactory->create();
    completeOnboardingFor($user);

    $this->actingAs($user)
        ->get($uri)
        ->assertForbidden();
})->with([
    'member options' => ['member', '/admin/options'],
    'member languages' => ['member', '/admin/options/languages'],
    'member interests' => ['member', '/admin/options/interests'],
    'moderator options' => ['moderator', '/admin/options'],
    'moderator languages' => ['moderator', '/admin/options/languages'],
    'moderator interests' => ['moderator', '/admin/options/interests'],
]);

test('members cannot access admin user details', function () {
    $member = User::factory()->create();
    $otherUser = User::factory()->create();
    completeOnboardingFor($member);

    $this->actingAs($member)
        ->get("/admin/users/{$otherUser->id}")
        ->assertForbidden();
});

test('members cannot access the admin project overview', function () {
    $member = User::factory()->create();
    completeOnboardingFor($member);

    $this->actingAs($member)
        ->get('/admin/project')
        ->assertForbidden();
});

test('members cannot access the admin system status page', function () {
    $member = User::factory()->create();
    completeOnboardingFor($member);

    $this->actingAs($member)
        ->get('/admin/system')
        ->assertForbidden();
});

test('members cannot update user roles', function () {
    $member = User::factory()->create();
    $otherUser = User::factory()->create();
    completeOnboardingFor($member);

    $this->actingAs($member)
        ->patch("/admin/users/{$otherUser->id}/role", [
            'role' => 'admin',
        ])
        ->assertForbidden();
});

test('admins can still access the admin area when the project hides the admin navigation entry', function () {
    config([
        'app.project.show_admin_area' => false,
    ]);

    $admin = User::factory()->admin()->create();
    Profile::factory()->for($admin)->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.showAdminArea', false)
            ->where('auth.user.role', 'admin'),
        );
});

test('owners can access admin routes protected by the admin role middleware', function () {
    $owner = User::factory()->owner()->create();
    completeOnboardingFor($owner);

    $this->actingAs($owner)
        ->get('/admin')
        ->assertOk();
});

test('admins and owners can access admin option pages', function (string $factoryState, string $uri, string $component) {
    $userFactory = User::factory();
    $user = $factoryState === 'owner'
        ? $userFactory->owner()->create()
        : $userFactory->admin()->create();
    completeOnboardingFor($user);

    $this->actingAs($user)
        ->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'admin options' => ['admin', '/admin/options', 'admin/Options/Index'],
    'admin languages' => ['admin', '/admin/options/languages', 'admin/Options/Languages'],
    'admin interests' => ['admin', '/admin/options/interests', 'admin/Options/Interests'],
    'owner options' => ['owner', '/admin/options', 'admin/Options/Index'],
    'owner languages' => ['owner', '/admin/options/languages', 'admin/Options/Languages'],
    'owner interests' => ['owner', '/admin/options/interests', 'admin/Options/Interests'],
]);

test('language option page loads ordered read-only language data', function () {
    $admin = User::factory()->admin()->create();
    completeOnboardingFor($admin);

    LanguageOption::query()->create([
        'code' => 'en',
        'label' => 'Englisch',
        'native_label' => 'English',
        'sort_order' => 20,
        'is_active' => true,
    ]);
    LanguageOption::query()->create([
        'code' => 'de',
        'label' => 'Deutsch',
        'native_label' => 'Deutsch',
        'sort_order' => 10,
        'is_active' => true,
    ]);
    LanguageOption::query()->create([
        'code' => 'la',
        'label' => 'Latein',
        'native_label' => null,
        'sort_order' => 30,
        'is_active' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.options.languages'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Options/Languages')
            ->has('languages', 3)
            ->where('languages.0.code', 'de')
            ->where('languages.0.label', 'Deutsch')
            ->where('languages.0.native_label', 'Deutsch')
            ->where('languages.0.sort_order', 10)
            ->where('languages.0.is_active', true)
            ->where('languages.1.code', 'en')
            ->where('languages.2.code', 'la')
            ->where('languages.2.native_label', null)
            ->where('languages.2.is_active', false),
        );
});

test('language option page returns an empty list without language data', function () {
    $owner = User::factory()->owner()->create();
    completeOnboardingFor($owner);

    $this->actingAs($owner)
        ->get(route('admin.options.languages'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Options/Languages')
            ->has('languages', 0),
        );
});

test('interest option page loads ordered read-only interest data', function () {
    $admin = User::factory()->admin()->create();
    completeOnboardingFor($admin);

    InterestOption::query()->create([
        'slug' => 'events',
        'label' => 'Events',
        'sort_order' => 20,
        'is_active' => true,
    ]);
    InterestOption::query()->create([
        'slug' => 'music',
        'label' => 'Musik',
        'sort_order' => 10,
        'is_active' => true,
    ]);
    InterestOption::query()->create([
        'slug' => 'former-topic',
        'label' => 'Ehemaliges Thema',
        'sort_order' => 30,
        'is_active' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.options.interests'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Options/Interests')
            ->has('interests', 3)
            ->where('interests.0.slug', 'music')
            ->where('interests.0.label', 'Musik')
            ->where('interests.0.sort_order', 10)
            ->where('interests.0.is_active', true)
            ->where('interests.1.slug', 'events')
            ->where('interests.2.slug', 'former-topic')
            ->where('interests.2.is_active', false),
        );
});

test('interest option page returns an empty list without interest data', function () {
    $owner = User::factory()->owner()->create();
    completeOnboardingFor($owner);

    $this->actingAs($owner)
        ->get(route('admin.options.interests'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Options/Interests')
            ->has('interests', 0),
        );
});

test('members never receive admin navigation access and remain blocked from the admin area', function () {
    config([
        'app.project.show_admin_area' => true,
    ]);

    $member = User::factory()->create();
    Profile::factory()->for($member)->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.showAdminArea', true)
            ->where('auth.user.role', 'member'),
        );

    $this->actingAs($member)
        ->get('/admin')
        ->assertForbidden();
});

test('guests do not receive authenticated admin navigation access and are redirected from the admin area', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user', null),
        );

    $this->get('/admin')
        ->assertRedirect(route('login'));
});

test('admin route is protected by the admin role middleware', function () {
    $route = Route::getRoutes()->getByName('admin');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('admin');
    expect($route->gatherMiddleware())->toContain('role:admin');
});

test('admin user detail route is protected by the admin role middleware', function () {
    $route = Route::getRoutes()->getByName('admin.users.show');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('admin/users/{user}');
    expect($route->gatherMiddleware())->toContain('role:admin');
});

test('admin project route is protected by the admin role middleware', function () {
    $route = Route::getRoutes()->getByName('admin.project');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('admin/project');
    expect($route->gatherMiddleware())->toContain('role:admin');
});

test('admin system route is protected by the admin role middleware', function () {
    $route = Route::getRoutes()->getByName('admin.system');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('admin/system');
    expect($route->gatherMiddleware())->toContain('role:admin');
});

test('admin user role update route is protected by the admin role middleware', function () {
    $route = Route::getRoutes()->getByName('admin.users.role.update');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('admin/users/{user}/role');
    expect($route->gatherMiddleware())->toContain('role:admin');
});

test('admin option routes use the admin role middleware', function (string $routeName, string $uri) {
    $route = Route::getRoutes()->getByName($routeName);

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe($uri);
    expect($route->gatherMiddleware())->toContain('role:admin');
})->with([
    ['admin.options', 'admin/options'],
    ['admin.options.languages', 'admin/options/languages'],
    ['admin.options.interests', 'admin/options/interests'],
]);

test('admin navigation contains the expected sections', function () {
    $navigation = file_get_contents(resource_path('js/components/AdminNavigation.vue'));

    expect($navigation)
        ->toContain("title: 'Übersicht'")
        ->toContain("title: 'Benutzer'")
        ->toContain("title: 'Stammdaten'")
        ->toContain("href: '/admin/options/languages'")
        ->toContain("href: '/admin/options/interests'")
        ->toContain("title: 'System'")
        ->toContain("title: 'Projekt'");
});

test('admin page includes a minimal user overview payload', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]);

    $member = User::factory()->create([
        'name' => 'Member User',
        'email' => 'member@example.com',
    ]);

    $request = Request::create('/admin', 'GET');
    $request->headers->set('X-Inertia', 'true');

    $response = app(AdminController::class)->index()->toResponse($request);
    $payload = $response->getData(true);

    expect($payload['component'])->toBe('Admin');
    expect($payload['props']['users'])->toHaveCount(2);
    expect($payload['props']['users'][0]['name'])->toBe('Admin User');
    expect($payload['props']['users'][0]['email'])->toBe('admin@example.com');
    expect($payload['props']['users'][0]['role'])->toBe('admin');
    expect($payload['props']['users'][1]['name'])->toBe('Member User');
    expect($payload['props']['users'][1]['email'])->toBe('member@example.com');
    expect($payload['props']['users'][1]['role'])->toBe('member');
});

test('admin user detail page includes a minimal user detail payload', function () {
    $user = User::factory()->create([
        'name' => 'Detail User',
        'email' => 'detail@example.com',
    ]);

    $request = Request::create("/admin/users/{$user->id}", 'GET');
    $request->headers->set('X-Inertia', 'true');

    $response = app(AdminController::class)->show($user)->toResponse($request);
    $payload = $response->getData(true);

    expect($payload['component'])->toBe('admin/Users/Show');
    expect($payload['props']['user']['name'])->toBe('Detail User');
    expect($payload['props']['user']['email'])->toBe('detail@example.com');
    expect($payload['props']['user']['role'])->toBe('member');
    expect($payload['props']['user']['email_verified_at'])->not->toBeNull();
    expect($payload['props']['user']['created_at'])->not->toBeNull();
    expect($payload['props']['user']['updated_at'])->not->toBeNull();
});

test('admin project overview page includes the project configuration payload', function () {
    config([
        'app.name' => 'Project Test App',
        'app.branding.logo' => 'Project Logo',
        'app.project.admin_label' => 'Platform',
        'app.project.tagline' => 'Starter baseline',
        'app.project.show_admin_area' => true,
        'app.project.show_appearance_settings' => false,
    ]);

    $request = Request::create('/admin/project', 'GET');
    $request->headers->set('X-Inertia', 'true');

    $response = app(AdminController::class)->project()->toResponse($request);
    $payload = $response->getData(true);

    expect($payload['component'])->toBe('admin/Project');
    expect($payload['props']['overview']['appName'])->toBe('Project Test App');
    expect($payload['props']['overview']['logo'])->toBe('Project Logo');
    expect($payload['props']['overview']['adminLabel'])->toBe('Platform');
    expect($payload['props']['overview']['tagline'])->toBe('Starter baseline');
    expect($payload['props']['overview']['showAdminArea'])->toBeTrue();
    expect($payload['props']['overview']['showAppearanceSettings'])->toBeFalse();
});

test('admin can open the system status page and receive key system values', function () {
    $admin = User::factory()->admin()->create();
    completeOnboardingFor($admin);

    $this->actingAs($admin)
        ->get('/admin/system')
        ->assertOk();

    config([
        'app.name' => 'System Test App',
    ]);

    $request = Request::create('/admin/system', 'GET');
    $request->headers->set('X-Inertia', 'true');

    $response = app(AdminController::class)->system()->toResponse($request);
    $payload = $response->getData(true);

    expect($payload['component'])->toBe('admin/System');
    expect($payload['props']['system']['app_name'])->toBe('System Test App');
    expect($payload['props']['system']['environment'])->toBe(app()->environment());
    expect($payload['props']['system']['laravel_version'])->toBe(app()->version());
    expect($payload['props']['system']['php_version'])->toBe(PHP_VERSION);
    expect($payload['props']['system']['default_fields'])->toContain('tagline');
    expect($payload['props']['system']['default_fields'])->toContain('welcome_title');
    expect($payload['props']['system']['default_fields'])->toContain('dashboard_title');
    expect($payload['props']['system']['default_fields'])->not->toContain('app_name');
});

test('system status marks known starter defaults and excludes customized values', function () {
    config([
        'app.name' => 'NEAREON',
        'app.project.tagline' => 'Regionale Social Web-App',
        'app.project.admin_label' => 'Admin',
    ]);

    $admin = User::factory()->admin()->create();
    completeOnboardingFor($admin);

    $this->actingAs($admin)
        ->get('/admin/system')
        ->assertOk();

    $request = Request::create('/admin/system', 'GET');
    $request->headers->set('X-Inertia', 'true');

    $response = app(AdminController::class)->system()->toResponse($request);
    $payload = $response->getData(true);

    expect($payload['props']['system']['default_fields'])->toContain('app_name');
    expect($payload['props']['system']['default_fields'])->toContain('tagline');
    expect($payload['props']['system']['default_fields'])->toContain('admin_label');

    config([
        'app.name' => 'Custom Project Name',
    ]);

    $response = app(AdminController::class)->system()->toResponse($request);
    $payload = $response->getData(true);

    expect($payload['props']['system']['default_fields'])->not->toContain('app_name');
});

test('admin can promote another user to admin', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();
    completeOnboardingFor($admin);

    $this->actingAs($admin)
        ->from("/admin/users/{$member->id}")
        ->patch("/admin/users/{$member->id}/role", [
            'role' => 'admin',
        ])
        ->assertRedirect("/admin/users/{$member->id}")
        ->assertSessionHas('success', 'Die Benutzerrolle wurde aktualisiert.');

    expect($member->refresh()->role->value)->toBe('admin');
});

test('admin can assign moderator and owner roles', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();
    completeOnboardingFor($admin);

    $this->actingAs($admin)
        ->from("/admin/users/{$member->id}")
        ->patch("/admin/users/{$member->id}/role", [
            'role' => UserRole::Moderator->value,
        ])
        ->assertRedirect("/admin/users/{$member->id}")
        ->assertSessionHas('success', 'Die Benutzerrolle wurde aktualisiert.');

    expect($member->refresh()->role)->toBe(UserRole::Moderator);

    $this->actingAs($admin)
        ->from("/admin/users/{$member->id}")
        ->patch("/admin/users/{$member->id}/role", [
            'role' => UserRole::Owner->value,
        ])
        ->assertRedirect("/admin/users/{$member->id}")
        ->assertSessionHas('success', 'Die Benutzerrolle wurde aktualisiert.');

    expect($member->refresh()->role)->toBe(UserRole::Owner);
});

test('admin can demote another admin when another admin remains', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    completeOnboardingFor($admin);

    $this->actingAs($admin)
        ->from("/admin/users/{$otherAdmin->id}")
        ->patch("/admin/users/{$otherAdmin->id}/role", [
            'role' => 'member',
        ])
        ->assertRedirect("/admin/users/{$otherAdmin->id}")
        ->assertSessionHas('success', 'Die Benutzerrolle wurde aktualisiert.');

    expect($otherAdmin->refresh()->role->value)->toBe('member');
});

test('admin cannot change their own role', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();
    completeOnboardingFor($admin);

    $this->actingAs($admin)
        ->from("/admin/users/{$admin->id}")
        ->patch("/admin/users/{$admin->id}/role", [
            'role' => 'member',
        ])
        ->assertRedirect("/admin/users/{$admin->id}")
        ->assertSessionHas('error', 'Du kannst deine eigene Rolle nicht ändern.');

    expect($admin->refresh()->role->value)->toBe('admin');
});

test('last admin cannot be demoted to member', function () {
    $admin = User::factory()->admin()->create();
    completeOnboardingFor($admin);

    $this->actingAs($admin)
        ->from("/admin/users/{$admin->id}")
        ->patch("/admin/users/{$admin->id}/role", [
            'role' => 'member',
        ])
        ->assertRedirect("/admin/users/{$admin->id}")
        ->assertSessionHas('error', 'Die letzte administrative Rolle kann nicht entfernt werden.');

    expect($admin->refresh()->role->value)->toBe('admin');
});

test('last admin-capable user cannot be demoted below admin', function () {
    $owner = User::factory()->owner()->create();
    completeOnboardingFor($owner);

    $this->actingAs($owner)
        ->from("/admin/users/{$owner->id}")
        ->patch("/admin/users/{$owner->id}/role", [
            'role' => UserRole::Moderator->value,
        ])
        ->assertRedirect("/admin/users/{$owner->id}")
        ->assertSessionHas('error', 'Die letzte administrative Rolle kann nicht entfernt werden.');

    expect($owner->refresh()->role)->toBe(UserRole::Owner);
});

test('invalid roles are rejected', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();
    completeOnboardingFor($admin);

    $this->actingAs($admin)
        ->from("/admin/users/{$member->id}")
        ->patch("/admin/users/{$member->id}/role", [
            'role' => 'superadmin',
        ])
        ->assertRedirect("/admin/users/{$member->id}")
        ->assertSessionHasErrors('role');

    expect($member->refresh()->role->value)->toBe('member');
});
