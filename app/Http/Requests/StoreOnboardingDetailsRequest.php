<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOnboardingDetailsRequest extends FormRequest
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
            'username' => $this->normalizeNullableString($this->input('username'), lowercase: true),
            'display_name' => $this->normalizeNullableString($this->input('display_name')),
            'region' => $this->normalizeNullableString($this->input('region')),
            'bio' => $this->normalizeNullableString($this->input('bio')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_-]+$/', 'unique:profiles,username'],
            'display_name' => ['required', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:280'],
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
            'username.required' => 'Bitte wähle einen Benutzernamen.',
            'username.min' => 'Der Benutzername muss mindestens 3 Zeichen lang sein.',
            'username.max' => 'Der Benutzername darf maximal 30 Zeichen lang sein.',
            'username.regex' => 'Der Benutzername darf nur Kleinbuchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
            'username.unique' => 'Dieser Benutzername ist bereits vergeben.',
            'display_name.required' => 'Bitte gib einen Anzeigenamen ein.',
            'display_name.max' => 'Der Anzeigename darf maximal 80 Zeichen lang sein.',
            'region.max' => 'Die Region darf maximal 120 Zeichen lang sein.',
            'bio.max' => 'Die Bio darf maximal 280 Zeichen lang sein.',
        ];
    }

    private function normalizeNullableString(mixed $value, bool $lowercase = false): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $lowercase ? strtolower($value) : $value;
    }
}
