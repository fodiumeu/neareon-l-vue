<?php

namespace App\Http\Requests;

use App\Models\Group;
use App\Models\InterestOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
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
            'name' => $this->normalizeNullableString($this->input('name')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'region' => $this->normalizeNullableString($this->input('region')),
            'postal_code' => $this->normalizeNullableString($this->input('postal_code')),
            'country_code' => $this->normalizeNullableString($this->input('country_code'), uppercase: true),
            'category_interest_option_id' => $this->normalizeNullableInteger($this->input('category_interest_option_id')),
            'visibility' => $this->normalizeNullableString($this->input('visibility')),
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
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'category_interest_option_id' => [
                'nullable',
                'integer',
                Rule::exists(InterestOption::class, 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'visibility' => [
                'required',
                Rule::in([
                    Group::VISIBILITY_PUBLIC,
                    Group::VISIBILITY_REQUEST,
                    Group::VISIBILITY_PRIVATE,
                ]),
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
            'name.required' => 'Bitte gib einen Gruppennamen ein.',
            'name.max' => 'Der Gruppenname darf maximal 80 Zeichen lang sein.',
            'description.max' => 'Die Beschreibung darf maximal 1000 Zeichen lang sein.',
            'region.max' => 'Die Region darf maximal 120 Zeichen lang sein.',
            'postal_code.max' => 'Die Postleitzahl darf maximal 20 Zeichen lang sein.',
            'country_code.size' => 'Der Ländercode muss aus zwei Zeichen bestehen.',
            'category_interest_option_id.integer' => 'Bitte wähle eine gültige Kategorie aus.',
            'category_interest_option_id.exists' => 'Bitte wähle eine verfügbare Kategorie aus.',
            'visibility.required' => 'Bitte wähle eine Sichtbarkeit aus.',
            'visibility.in' => 'Bitte wähle eine gültige Sichtbarkeit aus.',
        ];
    }

    private function normalizeNullableString(
        mixed $value,
        bool $uppercase = false,
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $uppercase ? strtoupper($value) : $value;
    }

    private function normalizeNullableInteger(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }
        }

        return is_numeric($value) ? (int) $value : $value;
    }
}
