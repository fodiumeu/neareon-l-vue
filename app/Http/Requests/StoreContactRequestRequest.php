<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequestRequest extends FormRequest
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
        $message = $this->input('message');

        if (is_string($message)) {
            $message = trim($message);
            $message = $message === '' ? null : $message;
        }

        $this->merge([
            'message' => $message,
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
            'receiver_id' => ['required', Rule::exists(User::class, 'id')],
            'message' => ['nullable', 'string', 'max:250'],
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
            'receiver_id.required' => 'Bitte wähle einen Empfänger aus.',
            'receiver_id.exists' => 'Der ausgewählte Empfänger ist nicht verfügbar.',
            'message.string' => 'Die Nachricht muss Text sein.',
            'message.max' => 'Die Nachricht darf maximal 250 Zeichen lang sein.',
        ];
    }
}
