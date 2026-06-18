<?php

use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Attach managed language and interest options to a profile for feature tests.
 *
 * @param  list<array{code: string, label: string, position: int}>  $languages
 * @param  list<array{slug: string, label: string, sort_order?: int}>  $interests
 */
function attachManagedProfileOptions(
    Profile $profile,
    array $languages = [],
    array $interests = [],
): void {
    foreach ($languages as $language) {
        $option = LanguageOption::query()->firstOrCreate(
            ['code' => $language['code']],
            [
                'label' => $language['label'],
                'native_label' => $language['label'],
                'sort_order' => $language['position'],
                'is_active' => true,
            ],
        );

        $profile->languageOptions()->attach($option, [
            'position' => $language['position'],
        ]);
    }

    foreach ($interests as $index => $interest) {
        $option = InterestOption::query()->firstOrCreate(
            ['slug' => $interest['slug']],
            [
                'label' => $interest['label'],
                'sort_order' => $interest['sort_order'] ?? $index + 1,
                'is_active' => true,
            ],
        );

        $profile->interestOptions()->attach($option);
    }
}

/**
 * Mirror existing JSON option values into pivots for legacy test fixtures.
 */
function attachManagedProfileOptionsFromJson(Profile $profile): void
{
    $languageOptions = LanguageOption::query()
        ->whereIn('code', $profile->languages ?? [])
        ->orWhereIn('label', $profile->languages ?? [])
        ->get();
    $interestOptions = InterestOption::query()
        ->whereIn('slug', $profile->interests ?? [])
        ->orWhereIn('label', $profile->interests ?? [])
        ->get();

    foreach ($profile->languages ?? [] as $index => $value) {
        $option = $languageOptions->first(
            fn (LanguageOption $option): bool => $option->code === $value
                || $option->label === $value,
        );

        if ($option !== null) {
            $profile->languageOptions()->syncWithoutDetaching([
                $option->id => ['position' => $index + 1],
            ]);
        }
    }

    foreach ($profile->interests ?? [] as $value) {
        $option = $interestOptions->first(
            fn (InterestOption $option): bool => $option->slug === $value
                || $option->label === $value,
        );

        if ($option !== null) {
            $profile->interestOptions()->syncWithoutDetaching([$option->id]);
        }
    }
}

/**
 * Mark a test profile as onboarded through managed option pivots.
 */
function completeManagedProfile(Profile $profile): Profile
{
    attachManagedProfileOptions(
        $profile,
        [['code' => 'de', 'label' => 'Deutsch', 'position' => 1]],
        [['slug' => 'community', 'label' => 'Community']],
    );

    return $profile;
}

/**
 * Create a test profile with completed managed-option onboarding.
 *
 * @param  array<string, mixed>  $attributes
 */
function createOnboardedProfile(User $user, array $attributes = []): Profile
{
    return completeManagedProfile(
        Profile::factory()->for($user)->create($attributes),
    );
}
