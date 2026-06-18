<?php

namespace App\Services;

use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class ProfileOptionSyncService
{
    /**
     * Update profile attributes and synchronize managed option pivots atomically.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Profile $profile, array $attributes): void
    {
        DB::transaction(function () use ($profile, $attributes): void {
            $profile->update($attributes);

            if (array_key_exists('languages', $attributes)) {
                $this->syncLanguages($profile, $attributes['languages']);
            }

            if (array_key_exists('interests', $attributes)) {
                $this->syncInterests($profile, $attributes['interests']);
            }
        });
    }

    /**
     * @param  list<string>|null  $values
     */
    private function syncLanguages(Profile $profile, ?array $values): void
    {
        $values ??= [];
        $options = LanguageOption::query()
            ->whereIn('code', $values)
            ->orWhereIn('label', $values)
            ->get();
        $records = [];

        foreach ($values as $index => $value) {
            $option = $options->first(
                fn (LanguageOption $option): bool => $option->code === $value
                    || $option->label === $value,
            );

            if ($option === null) {
                throw new UnexpectedValueException(
                    "Language option [{$value}] could not be synchronized.",
                );
            }

            $records[$option->id] = ['position' => $index + 1];
        }

        $profile->languageOptions()->sync($records);
    }

    /**
     * @param  list<string>|null  $values
     */
    private function syncInterests(Profile $profile, ?array $values): void
    {
        $values ??= [];
        $options = InterestOption::query()
            ->whereIn('slug', $values)
            ->orWhereIn('label', $values)
            ->get();
        $optionIds = [];

        foreach ($values as $value) {
            $option = $options->first(
                fn (InterestOption $option): bool => $option->slug === $value
                    || $option->label === $value,
            );

            if ($option === null) {
                throw new UnexpectedValueException(
                    "Interest option [{$value}] could not be synchronized.",
                );
            }

            $optionIds[] = $option->id;
        }

        $profile->interestOptions()->sync($optionIds);
    }
}
