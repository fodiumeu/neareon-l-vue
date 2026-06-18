<?php

namespace App\Services;

use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
use App\Support\ProfileOptionBackfillResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileOptionBackfillService
{
    public function backfill(): ProfileOptionBackfillResult
    {
        $result = new ProfileOptionBackfillResult;
        $languageIndex = $this->languageIndex();
        $interestIndex = $this->interestIndex();
        $languageMappings = $this->mappingIndex('languages');
        $interestMappings = $this->mappingIndex('interests');

        Profile::query()
            ->select(['id', 'languages', 'interests'])
            ->chunkById(200, function (Collection $profiles) use (
                $result,
                $languageIndex,
                $interestIndex,
                $languageMappings,
                $interestMappings,
            ): void {
                foreach ($profiles as $profile) {
                    DB::transaction(function () use (
                        $profile,
                        $result,
                        $languageIndex,
                        $interestIndex,
                        $languageMappings,
                        $interestMappings,
                    ): void {
                        $result->profilesProcessed++;

                        $this->backfillLanguages(
                            $profile,
                            $languageIndex,
                            $languageMappings,
                            $result,
                        );
                        $this->backfillInterests(
                            $profile,
                            $interestIndex,
                            $interestMappings,
                            $result,
                        );
                    });
                }
            });

        return $result;
    }

    /**
     * @param  array<string, LanguageOption>  $options
     * @param  array<string, string>  $mappings
     */
    private function backfillLanguages(
        Profile $profile,
        array $options,
        array $mappings,
        ProfileOptionBackfillResult $result,
    ): void {
        $attachedOptionIds = [];
        $currentPositions = $profile->languageOptions()
            ->get()
            ->mapWithKeys(fn (LanguageOption $option): array => [
                $option->id => (int) $option->pivot->position,
            ])
            ->all();

        foreach ($profile->languages ?? [] as $index => $value) {
            $option = $this->resolveOption($value, $options, $mappings);

            if (! $option instanceof LanguageOption) {
                $this->recordUnknown($profile, 'language', $value, $result);

                continue;
            }

            if (isset($attachedOptionIds[$option->id])) {
                continue;
            }

            $position = $index + 1;

            if (! array_key_exists($option->id, $currentPositions)) {
                $profile->languageOptions()->attach($option->id, ['position' => $position]);
                $result->languagesAttached++;
            } elseif ($currentPositions[$option->id] !== $position) {
                $profile->languageOptions()->updateExistingPivot($option->id, [
                    'position' => $position,
                ]);
                $result->languagesUpdated++;
            }

            $currentPositions[$option->id] = $position;
            $attachedOptionIds[$option->id] = true;
        }
    }

    /**
     * @param  array<string, InterestOption>  $options
     * @param  array<string, string>  $mappings
     */
    private function backfillInterests(
        Profile $profile,
        array $options,
        array $mappings,
        ProfileOptionBackfillResult $result,
    ): void {
        $attachedOptionIds = [];
        $currentOptionIds = $profile->interestOptions()
            ->pluck('interest_options.id')
            ->flip()
            ->all();

        foreach ($profile->interests ?? [] as $value) {
            $option = $this->resolveOption($value, $options, $mappings);

            if (! $option instanceof InterestOption) {
                $this->recordUnknown($profile, 'interest', $value, $result);

                continue;
            }

            if (isset($attachedOptionIds[$option->id])) {
                continue;
            }

            if (! array_key_exists($option->id, $currentOptionIds)) {
                $profile->interestOptions()->attach($option->id);
                $result->interestsAttached++;
            }

            $currentOptionIds[$option->id] = true;
            $attachedOptionIds[$option->id] = true;
        }
    }

    /**
     * @return array<string, LanguageOption>
     */
    private function languageIndex(): array
    {
        $index = [];

        foreach (LanguageOption::query()->get() as $option) {
            foreach ([$option->code, $option->label, $option->native_label] as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $index[$this->normalize($value)] = $option;
                }
            }
        }

        return $index;
    }

    /**
     * @return array<string, InterestOption>
     */
    private function interestIndex(): array
    {
        $index = [];

        foreach (InterestOption::query()->get() as $option) {
            foreach ([$option->slug, $option->label] as $value) {
                $index[$this->normalize($value)] = $option;
            }
        }

        return $index;
    }

    /**
     * @return array<string, string>
     */
    private function mappingIndex(string $type): array
    {
        $mappings = config("profile-options.backfill_mappings.{$type}", []);
        $index = [];

        foreach ($mappings as $source => $target) {
            if (is_string($source) && is_string($target)) {
                $index[$this->normalize($source)] = $this->normalize($target);
            }
        }

        return $index;
    }

    /**
     * @template TOption of LanguageOption|InterestOption
     *
     * @param  array<string, TOption>  $options
     * @param  array<string, string>  $mappings
     * @return TOption|null
     */
    private function resolveOption(mixed $value, array $options, array $mappings): LanguageOption|InterestOption|null
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalizedValue = $this->normalize($value);
        $normalizedValue = $mappings[$normalizedValue] ?? $normalizedValue;

        return $options[$normalizedValue] ?? null;
    }

    private function recordUnknown(
        Profile $profile,
        string $type,
        mixed $value,
        ProfileOptionBackfillResult $result,
    ): void {
        $displayValue = is_string($value)
            ? $value
            : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: get_debug_type($value));

        $result->addUnknown($profile->id, $type, $displayValue);

        Log::warning('Unknown profile option value during backfill.', [
            'profile_id' => $profile->id,
            'type' => $type,
            'value' => $displayValue,
        ]);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
