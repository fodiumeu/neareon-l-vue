<?php

use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
use App\Services\ProfileOptionBackfillService;
use Illuminate\Support\Facades\Log;

function createBackfillOptions(): array
{
    $german = LanguageOption::query()->create([
        'code' => 'de',
        'label' => 'Deutsch',
        'native_label' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $english = LanguageOption::query()->create([
        'code' => 'en',
        'label' => 'Englisch',
        'native_label' => 'English',
        'sort_order' => 2,
    ]);
    $music = InterestOption::query()->create([
        'slug' => 'music',
        'label' => 'Musik',
        'sort_order' => 1,
    ]);
    $technology = InterestOption::query()->create([
        'slug' => 'technology',
        'label' => 'Technologie',
        'sort_order' => 2,
    ]);

    return compact('german', 'english', 'music', 'technology');
}

test('profile option JSON values can be backfilled into pivots', function () {
    $options = createBackfillOptions();
    $profile = Profile::factory()->create([
        'languages' => ['Deutsch', 'en'],
        'interests' => ['music'],
    ]);

    $result = app(ProfileOptionBackfillService::class)->backfill();
    $profile->refresh();

    expect($profile->languageOptions->pluck('code')->all())->toBe(['de', 'en'])
        ->and($profile->languageOptions->pluck('pivot.position')->all())->toBe([1, 2])
        ->and($profile->interestOptions->pluck('slug')->all())->toBe(['music'])
        ->and($profile->languages)->toBe(['Deutsch', 'en'])
        ->and($profile->interests)->toBe(['music'])
        ->and($result->languagesAttached)->toBe(2)
        ->and($result->interestsAttached)->toBe(1)
        ->and($result->unknownValues)->toBeEmpty()
        ->and($options)->toHaveCount(4);
});

test('profile option backfill is idempotent', function () {
    createBackfillOptions();
    $profile = Profile::factory()->create([
        'languages' => ['de', 'en'],
        'interests' => ['music', 'technology'],
    ]);
    $service = app(ProfileOptionBackfillService::class);

    $firstResult = $service->backfill();
    $secondResult = $service->backfill();

    expect($profile->languageOptions()->count())->toBe(2)
        ->and($profile->interestOptions()->count())->toBe(2)
        ->and($firstResult->languagesAttached)->toBe(2)
        ->and($firstResult->interestsAttached)->toBe(2)
        ->and($secondResult->languagesAttached)->toBe(0)
        ->and($secondResult->languagesUpdated)->toBe(0)
        ->and($secondResult->interestsAttached)->toBe(0);
});

test('configured legacy values are mapped to managed options', function () {
    createBackfillOptions();
    $profile = Profile::factory()->create([
        'languages' => ['de'],
        'interests' => ['Technik'],
    ]);

    app(ProfileOptionBackfillService::class)->backfill();

    expect($profile->interestOptions()->pluck('slug')->all())
        ->toBe(['technology']);
});

test('unknown values are reported and logged without changing JSON', function () {
    createBackfillOptions();
    $profile = Profile::factory()->create([
        'languages' => ['de', 'Klingonisch'],
        'interests' => ['Unbekannt'],
    ]);
    Log::spy();

    $result = app(ProfileOptionBackfillService::class)->backfill();

    expect($result->unknownValues)->toBe([
        ['profile_id' => $profile->id, 'type' => 'language', 'value' => 'Klingonisch'],
        ['profile_id' => $profile->id, 'type' => 'interest', 'value' => 'Unbekannt'],
    ])->and($profile->refresh()->languages)->toBe(['de', 'Klingonisch'])
        ->and($profile->interests)->toBe(['Unbekannt']);

    Log::shouldHaveReceived('warning')->twice();
});

test('backfill artisan command is registered and reports unknown values', function () {
    createBackfillOptions();
    $profile = Profile::factory()->create([
        'languages' => ['Unbekannte Sprache'],
        'interests' => ['music'],
    ]);

    $this->artisan('neareon:backfill-profile-options')
        ->expectsOutputToContain('Unknown language value "Unbekannte Sprache"')
        ->expectsOutputToContain('Profile option backfill finished.')
        ->assertSuccessful();

    expect($profile->interestOptions()->pluck('slug')->all())->toBe(['music']);
});
