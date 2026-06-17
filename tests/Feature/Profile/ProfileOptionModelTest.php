<?php

use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
use Database\Seeders\InterestOptionSeeder;
use Database\Seeders\LanguageOptionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('profile option tables exist with the expected columns', function () {
    expect(Schema::hasColumns('language_options', [
        'id',
        'code',
        'label',
        'native_label',
        'sort_order',
        'is_active',
        'created_at',
        'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('interest_options', [
            'id',
            'slug',
            'label',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('profile_languages', [
            'id',
            'profile_id',
            'language_option_id',
            'position',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('profile_interests', [
            'id',
            'profile_id',
            'interest_option_id',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('language option defaults and casts are applied', function () {
    $language = LanguageOption::query()->create([
        'code' => 'de',
        'label' => 'Deutsch',
    ])->fresh();

    expect($language->native_label)->toBeNull()
        ->and($language->sort_order)->toBe(0)
        ->and($language->is_active)->toBeTrue();
});

test('interest option defaults and casts are applied', function () {
    $interest = InterestOption::query()->create([
        'slug' => 'music',
        'label' => 'Musik',
    ])->fresh();

    expect($interest->sort_order)->toBe(0)
        ->and($interest->is_active)->toBeTrue();
});

test('language option codes are unique', function () {
    LanguageOption::query()->create([
        'code' => 'de',
        'label' => 'Deutsch',
    ]);

    LanguageOption::query()->create([
        'code' => 'de',
        'label' => 'Deutsch erneut',
    ]);
})->throws(QueryException::class);

test('interest option slugs are unique', function () {
    InterestOption::query()->create([
        'slug' => 'music',
        'label' => 'Musik',
    ]);

    InterestOption::query()->create([
        'slug' => 'music',
        'label' => 'Musik erneut',
    ]);
})->throws(QueryException::class);

test('profiles can be related to language options with positions', function () {
    $profile = Profile::factory()->create();
    $german = LanguageOption::query()->create([
        'code' => 'de',
        'label' => 'Deutsch',
    ]);
    $english = LanguageOption::query()->create([
        'code' => 'en',
        'label' => 'Englisch',
    ]);

    $profile->languageOptions()->attach($english, ['position' => 2]);
    $profile->languageOptions()->attach($german, ['position' => 1]);

    $languages = $profile->fresh()->languageOptions;

    expect($languages)->toHaveCount(2)
        ->and($languages->pluck('code')->all())->toBe(['de', 'en'])
        ->and($languages->first()->pivot->position)->toBe(1);
});

test('profiles can be related to interest options', function () {
    $profile = Profile::factory()->create();
    $music = InterestOption::query()->create([
        'slug' => 'music',
        'label' => 'Musik',
    ]);
    $events = InterestOption::query()->create([
        'slug' => 'events',
        'label' => 'Events',
    ]);

    $profile->interestOptions()->attach([$music->id, $events->id]);

    expect($profile->fresh()->interestOptions)
        ->toHaveCount(2)
        ->pluck('slug')
        ->all()
        ->toBe(['music', 'events']);
});

test('profile language pairs are unique', function () {
    $profile = Profile::factory()->create();
    $language = LanguageOption::query()->create([
        'code' => 'de',
        'label' => 'Deutsch',
    ]);

    $profile->languageOptions()->attach($language, ['position' => 1]);
    $profile->languageOptions()->attach($language, ['position' => 2]);
})->throws(QueryException::class);

test('profile interest pairs are unique', function () {
    $profile = Profile::factory()->create();
    $interest = InterestOption::query()->create([
        'slug' => 'music',
        'label' => 'Musik',
    ]);

    $profile->interestOptions()->attach($interest);
    $profile->interestOptions()->attach($interest);
})->throws(QueryException::class);

test('language option seeder creates the mvp catalog', function () {
    $this->seed(LanguageOptionSeeder::class);

    expect(LanguageOption::query()->count())->toBeGreaterThan(0)
        ->and(LanguageOption::query()->where('code', 'de')->where('label', 'Deutsch')->exists())->toBeTrue()
        ->and(LanguageOption::query()->where('code', 'nl')->where('label', 'Niederländisch')->exists())->toBeTrue();
});

test('interest option seeder creates the mvp catalog', function () {
    $this->seed(InterestOptionSeeder::class);

    expect(InterestOption::query()->count())->toBeGreaterThan(0)
        ->and(InterestOption::query()->where('slug', 'music')->where('label', 'Musik')->exists())->toBeTrue()
        ->and(InterestOption::query()->where('slug', 'health')->where('label', 'Gesundheit')->exists())->toBeTrue();
});
