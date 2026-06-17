<?php

use App\Models\LanguageOption;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function completeLanguageOptionAdminProfile(User $user): void
{
    Profile::factory()->for($user)->create();
}

test('admins can create language options', function () {
    $admin = User::factory()->admin()->create();
    completeLanguageOptionAdminProfile($admin);

    $this->actingAs($admin)
        ->post(route('admin.options.languages.store'), [
            'code' => ' SV ',
            'label' => 'Schwedisch',
            'native_label' => 'Svenska',
            'sort_order' => 50,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.options.languages'))
        ->assertSessionHas('success', 'Die Sprache wurde angelegt.');

    $language = LanguageOption::query()->where('code', 'sv')->firstOrFail();

    expect($language->label)->toBe('Schwedisch')
        ->and($language->native_label)->toBe('Svenska')
        ->and($language->sort_order)->toBe(50)
        ->and($language->is_active)->toBeTrue();
});

test('owners can create inactive language options', function () {
    $owner = User::factory()->owner()->create();
    completeLanguageOptionAdminProfile($owner);

    $this->actingAs($owner)
        ->post(route('admin.options.languages.store'), [
            'code' => 'no',
            'label' => 'Norwegisch',
            'native_label' => 'Norsk',
            'sort_order' => 60,
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.options.languages'));

    expect(LanguageOption::query()->where('code', 'no')->firstOrFail()->is_active)
        ->toBeFalse();
});

test('admins can update language options', function () {
    $admin = User::factory()->admin()->create();
    completeLanguageOptionAdminProfile($admin);
    $language = LanguageOption::query()->create([
        'code' => 'sv',
        'label' => 'Schwedisch',
        'native_label' => null,
        'sort_order' => 50,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.options.languages.update', $language), [
            'code' => 'sv',
            'label' => 'Schwedisch',
            'native_label' => 'Svenska',
            'sort_order' => 15,
        ])
        ->assertRedirect(route('admin.options.languages'))
        ->assertSessionHas('success', 'Die Sprache wurde aktualisiert.');

    $language->refresh();

    expect($language->native_label)->toBe('Svenska')
        ->and($language->sort_order)->toBe(15)
        ->and($language->is_active)->toBeTrue();
});

test('admins and owners can toggle language option status', function (string $role) {
    $userFactory = User::factory();
    $user = $role === 'owner'
        ? $userFactory->owner()->create()
        : $userFactory->admin()->create();
    completeLanguageOptionAdminProfile($user);
    $language = LanguageOption::query()->create([
        'code' => 'sv',
        'label' => 'Schwedisch',
        'native_label' => 'Svenska',
        'sort_order' => 50,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('admin.options.languages.status', $language))
        ->assertRedirect(route('admin.options.languages'))
        ->assertSessionHas('success', 'Die Sprache wurde deaktiviert.');

    expect($language->refresh()->is_active)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('admin.options.languages.status', $language))
        ->assertRedirect(route('admin.options.languages'))
        ->assertSessionHas('success', 'Die Sprache wurde aktiviert.');

    expect($language->refresh()->is_active)->toBeTrue();
})->with(['admin', 'owner']);

test('language option validation requires unique code and label', function () {
    $admin = User::factory()->admin()->create();
    completeLanguageOptionAdminProfile($admin);
    LanguageOption::query()->create([
        'code' => 'de',
        'label' => 'Deutsch',
        'native_label' => 'Deutsch',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.options.languages'))
        ->post(route('admin.options.languages.store'), [
            'code' => 'de',
            'label' => 'Deutsch',
            'native_label' => 'German',
            'sort_order' => 2,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.options.languages'))
        ->assertSessionHasErrors(['code', 'label']);
});

test('language option validation requires numeric non-negative sorting', function () {
    $admin = User::factory()->admin()->create();
    completeLanguageOptionAdminProfile($admin);

    $this->actingAs($admin)
        ->from(route('admin.options.languages'))
        ->post(route('admin.options.languages.store'), [
            'code' => 'sv',
            'label' => 'Schwedisch',
            'native_label' => 'Svenska',
            'sort_order' => 'not-a-number',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.options.languages'))
        ->assertSessionHasErrors('sort_order');
});

test('members and moderators cannot manage language options', function (string $role, string $method) {
    $userFactory = User::factory();
    $user = $role === 'moderator'
        ? $userFactory->moderator()->create()
        : $userFactory->create();
    completeLanguageOptionAdminProfile($user);
    $language = LanguageOption::query()->create([
        'code' => 'sv',
        'label' => 'Schwedisch',
        'native_label' => 'Svenska',
        'sort_order' => 50,
        'is_active' => true,
    ]);

    $response = match ($method) {
        'store' => $this->actingAs($user)->post(route('admin.options.languages.store'), [
            'code' => 'no',
            'label' => 'Norwegisch',
            'native_label' => 'Norsk',
            'sort_order' => 60,
            'is_active' => true,
        ]),
        'update' => $this->actingAs($user)->patch(route('admin.options.languages.update', $language), [
            'code' => 'sv',
            'label' => 'Schwedisch geändert',
            'native_label' => 'Svenska',
            'sort_order' => 10,
        ]),
        'status' => $this->actingAs($user)->patch(route('admin.options.languages.status', $language)),
    };

    $response->assertForbidden();
})->with([
    'member store' => ['member', 'store'],
    'member update' => ['member', 'update'],
    'member status' => ['member', 'status'],
    'moderator store' => ['moderator', 'store'],
    'moderator update' => ['moderator', 'update'],
    'moderator status' => ['moderator', 'status'],
]);

test('guests cannot manage language options', function (string $method) {
    $language = LanguageOption::query()->create([
        'code' => 'sv',
        'label' => 'Schwedisch',
        'native_label' => 'Svenska',
        'sort_order' => 50,
        'is_active' => true,
    ]);

    $response = match ($method) {
        'store' => $this->post(route('admin.options.languages.store'), [
            'code' => 'no',
            'label' => 'Norwegisch',
            'native_label' => 'Norsk',
            'sort_order' => 60,
            'is_active' => true,
        ]),
        'update' => $this->patch(route('admin.options.languages.update', $language), [
            'code' => 'sv',
            'label' => 'Schwedisch geändert',
            'native_label' => 'Svenska',
            'sort_order' => 10,
        ]),
        'status' => $this->patch(route('admin.options.languages.status', $language)),
    };

    $response->assertRedirect(route('login'));
})->with(['store', 'update', 'status']);

test('language management routes are protected and no delete route exists', function () {
    foreach ([
        'admin.options.languages.store',
        'admin.options.languages.update',
        'admin.options.languages.status',
    ] as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('role:admin');
    }

    expect(Route::getRoutes()->getByName('admin.options.languages.destroy'))
        ->toBeNull();
});
