<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreAgeGateRequest extends FormRequest
{
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
}
