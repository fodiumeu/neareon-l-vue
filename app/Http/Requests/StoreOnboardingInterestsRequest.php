<?php

namespace App\Http\Requests;

use App\Models\InterestOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingInterestsRequest extends FormRequest
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
        $this->merge([
            'interests' => $this->normalizeList($this->input('interests')),
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
            'interests' => ['required', 'array', 'min:1', 'max:20'],
            'interests.*' => [
                'required',
                'string',
                Rule::exists(InterestOption::class, 'label')
                    ->where('is_active', true),
            ],
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
            'interests.required' => 'Bitte wähle mindestens ein Interesse aus.',
            'interests.min' => 'Bitte wähle mindestens ein Interesse aus.',
            'interests.max' => 'Bitte wähle maximal 20 Interessen aus.',
            'interests.*.exists' => 'Bitte wähle nur verfügbare Interessen aus.',
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
            ->unique()
            ->values()
            ->all();
    }
}
