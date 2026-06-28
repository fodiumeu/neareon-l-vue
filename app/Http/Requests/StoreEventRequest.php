<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\InterestOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
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
            'title' => $this->normalizeNullableString($this->input('title')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'region' => $this->normalizeNullableString($this->input('region')),
            'postal_code' => $this->normalizeNullableString($this->input('postal_code')),
            'country_code' => $this->normalizeNullableString($this->input('country_code'), uppercase: true),
            'category_interest_option_id' => $this->normalizeNullableInteger($this->input('category_interest_option_id')),
            'visibility' => $this->normalizeNullableString($this->input('visibility')),
            'max_attendees' => $this->normalizeNullableInteger($this->input('max_attendees')),
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
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
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
                    Event::VISIBILITY_PUBLIC,
                    Event::VISIBILITY_REQUEST,
                ]),
            ],
            'max_attendees' => ['nullable', 'integer', 'min:1', 'max:100000'],
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
            'title.required' => 'Bitte gib einen Eventnamen ein.',
            'title.max' => 'Der Eventname darf maximal 120 Zeichen lang sein.',
            'description.max' => 'Die Beschreibung darf maximal 5000 Zeichen lang sein.',
            'starts_at.required' => 'Bitte gib einen Startzeitpunkt ein.',
            'starts_at.date' => 'Bitte gib einen gültigen Startzeitpunkt ein.',
            'ends_at.date' => 'Bitte gib einen gültigen Endzeitpunkt ein.',
            'ends_at.after' => 'Das Ende muss nach dem Start liegen.',
            'region.max' => 'Die Region darf maximal 120 Zeichen lang sein.',
            'postal_code.max' => 'Die Postleitzahl darf maximal 20 Zeichen lang sein.',
            'country_code.size' => 'Der Ländercode muss aus zwei Zeichen bestehen.',
            'category_interest_option_id.integer' => 'Bitte wähle eine gültige Kategorie aus.',
            'category_interest_option_id.exists' => 'Bitte wähle eine verfügbare Kategorie aus.',
            'visibility.required' => 'Bitte wähle eine Sichtbarkeit aus.',
            'visibility.in' => 'Bitte wähle eine gültige Sichtbarkeit aus.',
            'max_attendees.integer' => 'Bitte gib eine gültige maximale Teilnehmerzahl ein.',
            'max_attendees.min' => 'Die maximale Teilnehmerzahl muss mindestens 1 sein.',
            'max_attendees.max' => 'Die maximale Teilnehmerzahl ist zu hoch.',
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
