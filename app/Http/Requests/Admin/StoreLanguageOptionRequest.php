<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLanguageOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() === true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code'))
                ? strtolower(trim($this->input('code')))
                : $this->input('code'),
            'label' => $this->normalizeNullableString($this->input('label')),
            'native_label' => $this->normalizeNullableString($this->input('native_label')),
            'is_active' => $this->boolean('is_active'),
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
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('language_options', 'code')],
            'label' => ['required', 'string', 'max:80', Rule::unique('language_options', 'label')],
            'native_label' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
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
            'code.required' => 'Bitte gib einen Sprachcode ein.',
            'code.alpha_dash' => 'Der Sprachcode darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
            'code.unique' => 'Dieser Sprachcode ist bereits vorhanden.',
            'label.required' => 'Bitte gib eine Sprachbezeichnung ein.',
            'label.unique' => 'Diese Sprachbezeichnung ist bereits vorhanden.',
            'sort_order.required' => 'Bitte gib eine Sortierung ein.',
            'sort_order.integer' => 'Die Sortierung muss eine ganze Zahl sein.',
            'sort_order.min' => 'Die Sortierung darf nicht negativ sein.',
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
}
