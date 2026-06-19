<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the authenticated user participates in the conversation.
     */
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');
        $user = $this->user();

        if ($user === null || ! $conversation instanceof Conversation) {
            return false;
        }

        $participantUserIds = $conversation->participants()
            ->pluck('user_id');

        if (! $participantUserIds->contains($user->id)) {
            return false;
        }

        $otherUserId = $participantUserIds->first(
            fn (int $userId): bool => $userId !== $user->id,
        );

        if ($otherUserId === null) {
            return false;
        }

        $otherUser = User::query()->findOrFail($otherUserId);

        return app(PrivacyService::class)
            ->canSendMessage($user, $otherUser, $conversation);
    }

    /**
     * Trim the message before validation and persistence.
     */
    protected function prepareForValidation(): void
    {
        $message = $this->input('message');

        if (is_string($message)) {
            $message = trim($message);
        }

        $this->merge([
            'message' => $message,
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
            'message' => ['required', 'string', 'min:1', 'max:5000'],
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
            'message.required' => 'Bitte gib eine Nachricht ein.',
            'message.string' => 'Die Nachricht muss Text sein.',
            'message.min' => 'Bitte gib eine Nachricht ein.',
            'message.max' => 'Die Nachricht darf maximal 5000 Zeichen lang sein.',
        ];
    }
}
