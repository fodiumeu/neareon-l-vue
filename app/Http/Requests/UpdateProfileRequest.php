<?php

namespace App\Http\Requests;

use App\Enums\ProfileVisibility;
use App\Models\InterestOption;
use App\Models\LanguageOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $profileData = [
            'display_name' => $this->normalizeNullableString($this->input('display_name')),
            'region' => $this->normalizeNullableString($this->input('region')),
            'languages' => $this->normalizeList($this->input('languages')),
            'interests' => $this->normalizeList($this->input('interests')),
        ];

        if ($this->has('bio')) {
            $profileData['bio'] = $this->normalizeNullableString($this->input('bio'));
        }

        $this->merge($profileData);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $profile = $this->user()?->profile;
        $storedLanguages = $profile?->languages ?? [];
        $storedInterests = $profile?->interests ?? [];
        $selectedLanguageCodes = LanguageOption::query()
            ->whereIn('code', $storedLanguages)
            ->orWhereIn('label', $storedLanguages)
            ->pluck('code')
            ->all();
        $selectedInterestSlugs = InterestOption::query()
            ->whereIn('slug', $storedInterests)
            ->orWhereIn('label', $storedInterests)
            ->pluck('slug')
            ->all();
        $profileVisibility = [
            ProfileVisibility::Public->value,
            ProfileVisibility::Mutuals->value,
            ProfileVisibility::Private->value,
        ];
        $fieldVisibility = [
            ProfileVisibility::Public->value,
            ProfileVisibility::Followers->value,
            ProfileVisibility::Mutuals->value,
            ProfileVisibility::Private->value,
        ];

        return [
            'display_name' => ['required', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:280'],
            'region' => ['nullable', 'string', 'max:120'],
            'languages' => ['nullable', 'array', 'max:20'],
            'languages.*' => [
                'string',
                'max:40',
                Rule::exists(LanguageOption::class, 'code')
                    ->where(fn ($query) => $query->where('is_active', true)
                        ->when(
                            $selectedLanguageCodes !== [],
                            fn ($query) => $query->orWhereIn('code', $selectedLanguageCodes),
                        )),
            ],
            'interests' => ['nullable', 'array', 'max:20'],
            'interests.*' => [
                'string',
                'max:40',
                Rule::exists(InterestOption::class, 'slug')
                    ->where(fn ($query) => $query->where('is_active', true)
                        ->when(
                            $selectedInterestSlugs !== [],
                            fn ($query) => $query->orWhereIn('slug', $selectedInterestSlugs),
                        )),
            ],
            'profile_visibility' => ['required', Rule::in($profileVisibility)],
            'interests_visibility' => ['required', Rule::in($fieldVisibility)],
            'languages_visibility' => ['required', Rule::in($fieldVisibility)],
            'region_visibility' => ['required', Rule::in($fieldVisibility)],
            'social_counts_visibility' => ['required', Rule::in($fieldVisibility)],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.required' => 'Bitte gib einen Anzeigenamen ein.',
            'display_name.max' => 'Der Anzeigename darf maximal 80 Zeichen lang sein.',
            'bio.max' => 'Die Bio darf maximal 280 Zeichen lang sein.',
            'region.max' => 'Die Region darf maximal 120 Zeichen lang sein.',
            'languages.max' => 'Bitte gib maximal 20 Sprachen an.',
            'languages.*.max' => 'Ein Spracheintrag darf maximal 40 Zeichen lang sein.',
            'languages.*.exists' => 'Bitte wähle eine verfügbare Sprache aus.',
            'interests.max' => 'Bitte gib maximal 20 Interessen an.',
            'interests.*.max' => 'Ein Interesse darf maximal 40 Zeichen lang sein.',
            'interests.*.exists' => 'Bitte wähle ein verfügbares Interesse aus.',
            '*.required' => 'Bitte wähle eine Sichtbarkeit aus.',
            '*.in' => 'Bitte wähle eine gültige Sichtbarkeit aus.',
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>|null
     */
    private function normalizeList(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return null;
        }

        $items = collect($value)
            ->filter(fn (mixed $item): bool => is_string($item))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }
}
