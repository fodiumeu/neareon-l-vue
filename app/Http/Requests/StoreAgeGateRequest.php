<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreAgeGateRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $birthdate = $this->input('birthdate');

        if (! is_string($birthdate)) {
            return;
        }

        $normalizedBirthdate = $this->normalizeBirthdate($birthdate);

        if ($normalizedBirthdate !== null) {
            $this->merge([
                'birthdate' => $normalizedBirthdate,
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'birthdate' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->has('birthdate')) {
                return;
            }

            $birthdate = Carbon::parse($this->input('birthdate'))->startOfDay();
            $minimumBirthdate = now()->subYears(14)->startOfDay();

            if ($birthdate->greaterThan($minimumBirthdate)) {
                $validator->errors()->add(
                    'birthdate',
                    'NEAREON kann aktuell erst ab 14 Jahren genutzt werden.',
                );
            }
        });
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birthdate.required' => 'Bitte gib dein Geburtsdatum ein.',
            'birthdate.date' => 'Bitte gib ein gültiges Geburtsdatum ein.',
            'birthdate.before_or_equal' => 'Bitte gib ein gültiges Geburtsdatum ein.',
        ];
    }

    private function normalizeBirthdate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $matches) === 1) {
            return $this->datePartsToIso(
                (int) $matches[1],
                (int) $matches[2],
                (int) $matches[3],
            );
        }

        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $matches) === 1) {
            return $this->datePartsToIso(
                (int) $matches[1],
                (int) $matches[2],
                (int) $matches[3],
            );
        }

        return null;
    }

    private function datePartsToIso(int $day, int $month, int $year): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
