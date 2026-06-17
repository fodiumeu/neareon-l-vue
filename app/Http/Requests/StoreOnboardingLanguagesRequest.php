<?php

namespace App\Http\Requests;

use App\Support\OnboardingOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingLanguagesRequest extends FormRequest
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
        $languages = $this->normalizeList($this->input('languages'));

        $this->merge([
            'languages' => $languages,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'languages' => ['required', 'array', 'min:1', 'max:5'],
            'languages.*' => ['required', 'string', 'distinct', Rule::in(OnboardingOptions::languages())],
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
            'languages.required' => 'Bitte wähle deine Hauptsprache aus.',
            'languages.min' => 'Bitte wähle deine Hauptsprache aus.',
            'languages.max' => 'Bitte wähle maximal 5 Sprachen aus.',
            'languages.*.distinct' => 'Bitte wähle jede Sprache nur einmal aus.',
            'languages.*.in' => 'Bitte wähle nur vorgeschlagene Sprachen aus.',
        ];
    }

    /**
     * @return list<string>|null
     */
    private function normalizeList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
