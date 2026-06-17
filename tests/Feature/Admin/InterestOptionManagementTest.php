<?php

use App\Models\InterestOption;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function completeInterestOptionAdminProfile(User $user): void
{
    Profile::factory()->for($user)->create();
}

test('admins can create interest options with normalized slugs', function () {
    $admin = User::factory()->admin()->create();
    completeInterestOptionAdminProfile($admin);

    $this->actingAs($admin)
        ->post(route('admin.options.interests.store'), [
            'slug' => ' PODCASTS ',
            'label' => 'Podcasts',
            'sort_order' => 50,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.options.interests'))
        ->assertSessionHas('success', 'Das Interesse wurde angelegt.');

    $interest = InterestOption::query()->where('slug', 'podcasts')->firstOrFail();

    expect($interest->label)->toBe('Podcasts')
        ->and($interest->sort_order)->toBe(50)
        ->and($interest->is_active)->toBeTrue();
});

test('owners can create inactive interest options', function () {
    $owner = User::factory()->owner()->create();
    completeInterestOptionAdminProfile($owner);

    $this->actingAs($owner)
        ->post(route('admin.options.interests.store'), [
            'slug' => 'podcasts',
            'label' => 'Podcasts',
            'sort_order' => 60,
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.options.interests'));

    expect(InterestOption::query()->where('slug', 'podcasts')->firstOrFail()->is_active)
        ->toBeFalse();
});

test('admins can update interest options', function () {
    $admin = User::factory()->admin()->create();
    completeInterestOptionAdminProfile($admin);
    $interest = InterestOption::query()->create([
        'slug' => 'podcasts',
        'label' => 'Podcasts',
        'sort_order' => 50,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.options.interests.update', $interest), [
            'slug' => ' AUDIO-PODCASTS ',
            'label' => 'Audio-Podcasts',
            'sort_order' => 15,
        ])
        ->assertRedirect(route('admin.options.interests'))
        ->assertSessionHas('success', 'Das Interesse wurde aktualisiert.');

    $interest->refresh();

    expect($interest->slug)->toBe('audio-podcasts')
        ->and($interest->label)->toBe('Audio-Podcasts')
        ->and($interest->sort_order)->toBe(15)
        ->and($interest->is_active)->toBeTrue();
});

test('admins and owners can toggle interest option status', function (string $role) {
    $userFactory = User::factory();
    $user = $role === 'owner'
        ? $userFactory->owner()->create()
        : $userFactory->admin()->create();
    completeInterestOptionAdminProfile($user);
    $interest = InterestOption::query()->create([
        'slug' => 'podcasts',
        'label' => 'Podcasts',
        'sort_order' => 50,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('admin.options.interests.status', $interest))
        ->assertRedirect(route('admin.options.interests'))
        ->assertSessionHas('success', 'Das Interesse wurde deaktiviert.');

    expect($interest->refresh()->is_active)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('admin.options.interests.status', $interest))
        ->assertRedirect(route('admin.options.interests'))
        ->assertSessionHas('success', 'Das Interesse wurde aktiviert.');

    expect($interest->refresh()->is_active)->toBeTrue();
})->with(['admin', 'owner']);

test('interest option validation requires unique slug and label', function () {
    $admin = User::factory()->admin()->create();
    completeInterestOptionAdminProfile($admin);
    InterestOption::query()->create([
        'slug' => 'music',
        'label' => 'Musik',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.options.interests'))
        ->post(route('admin.options.interests.store'), [
            'slug' => ' MUSIC ',
            'label' => 'Musik',
            'sort_order' => 2,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.options.interests'))
        ->assertSessionHasErrors(['slug', 'label']);
});

test('interest option validation requires numeric non-negative sorting', function () {
    $admin = User::factory()->admin()->create();
    completeInterestOptionAdminProfile($admin);

    $this->actingAs($admin)
        ->from(route('admin.options.interests'))
        ->post(route('admin.options.interests.store'), [
            'slug' => 'podcasts',
            'label' => 'Podcasts',
            'sort_order' => 'not-a-number',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.options.interests'))
        ->assertSessionHasErrors('sort_order');
});

test('members and moderators cannot manage interest options', function (string $role, string $method) {
    $userFactory = User::factory();
    $user = $role === 'moderator'
        ? $userFactory->moderator()->create()
        : $userFactory->create();
    completeInterestOptionAdminProfile($user);
    $interest = InterestOption::query()->create([
        'slug' => 'podcasts',
        'label' => 'Podcasts',
        'sort_order' => 50,
        'is_active' => true,
    ]);

    $response = match ($method) {
        'store' => $this->actingAs($user)->post(route('admin.options.interests.store'), [
            'slug' => 'books',
            'label' => 'Bücher',
            'sort_order' => 60,
            'is_active' => true,
        ]),
        'update' => $this->actingAs($user)->patch(route('admin.options.interests.update', $interest), [
            'slug' => 'podcasts',
            'label' => 'Podcasts geändert',
            'sort_order' => 10,
        ]),
        'status' => $this->actingAs($user)->patch(route('admin.options.interests.status', $interest)),
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

test('guests cannot manage interest options', function (string $method) {
    $interest = InterestOption::query()->create([
        'slug' => 'podcasts',
        'label' => 'Podcasts',
        'sort_order' => 50,
        'is_active' => true,
    ]);

    $response = match ($method) {
        'store' => $this->post(route('admin.options.interests.store'), [
            'slug' => 'books',
            'label' => 'Bücher',
            'sort_order' => 60,
            'is_active' => true,
        ]),
        'update' => $this->patch(route('admin.options.interests.update', $interest), [
            'slug' => 'podcasts',
            'label' => 'Podcasts geändert',
            'sort_order' => 10,
        ]),
        'status' => $this->patch(route('admin.options.interests.status', $interest)),
    };

    $response->assertRedirect(route('login'));
})->with(['store', 'update', 'status']);

test('interest management routes are protected and no delete route exists', function () {
    foreach ([
        'admin.options.interests.store',
        'admin.options.interests.update',
        'admin.options.interests.status',
    ] as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('role:admin');
    }

    expect(Route::getRoutes()->getByName('admin.options.interests.destroy'))
        ->toBeNull();
});
