<?php

namespace App\Models;

use App\Enums\ContactRequestStatus;
use Database\Factories\ContactRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sender_id',
    'receiver_id',
    'message',
    'status',
    'responded_at',
])]
class ContactRequest extends Model
{
    /** @use HasFactory<ContactRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'status' => ContactRequestStatus::class,
        ];
    }

    /**
     * Restrict the query to requests between exactly two users.
     *
     * @param  Builder<ContactRequest>  $query
     * @return Builder<ContactRequest>
     */
    public function scopeBetweenUsers(
        Builder $query,
        User $userA,
        User $userB,
    ): Builder {
        return $query->where(function (Builder $pairQuery) use ($userA, $userB): void {
            $pairQuery
                ->where(function (Builder $directionQuery) use ($userA, $userB): void {
                    $directionQuery
                        ->where('sender_id', $userA->id)
                        ->where('receiver_id', $userB->id);
                })
                ->orWhere(function (Builder $directionQuery) use ($userA, $userB): void {
                    $directionQuery
                        ->where('sender_id', $userB->id)
                        ->where('receiver_id', $userA->id);
                });
        });
    }

    /**
     * Get the user who sent the contact request.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the user who received the contact request.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
