<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInterestOptionRequest extends FormRequest
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
            'slug' => is_string($this->input('slug'))
                ? strtolower(trim($this->input('slug')))
                : $this->input('slug'),
            'label' => $this->normalizeNullableString($this->input('label')),
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
            'slug' => ['required', 'string', 'max:80', 'alpha_dash:ascii', Rule::unique('interest_options', 'slug')],
            'label' => ['required', 'string', 'max:80', Rule::unique('interest_options', 'label')],
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
            'slug.required' => 'Bitte gib einen Slug ein.',
            'slug.alpha_dash' => 'Der Slug darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
            'slug.unique' => 'Dieser Slug ist bereits vorhanden.',
            'label.required' => 'Bitte gib eine Interessenbezeichnung ein.',
            'label.unique' => 'Diese Interessenbezeichnung ist bereits vorhanden.',
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
