<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'role', 'birthdate', 'age_gate_passed_at', 'password'])]
#[Hidden(['age_gate_passed_at', 'birthdate', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age_gate_passed_at' => 'datetime',
            'birthdate' => 'date',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the user has the given role.
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Determine whether the user has at least the given role level.
     */
    public function hasAtLeastRole(UserRole $role): bool
    {
        return $this->role->level() >= $role->level();
    }

    /**
     * Determine whether the user is a moderator.
     */
    public function isModerator(): bool
    {
        return $this->hasRole(UserRole::Moderator);
    }

    /**
     * Determine whether the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user is an owner.
     */
    public function isOwner(): bool
    {
        return $this->hasRole(UserRole::Owner);
    }

    /**
     * Determine whether the user can access admin-only areas.
     */
    public function canAccessAdmin(): bool
    {
        return $this->hasAtLeastRole(UserRole::Admin);
    }

    /**
     * Get the profile associated with the user.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Get the follow relationships started by the user.
     */
    public function followingRelationships(): HasMany
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    /**
     * Get the follow relationships targeting the user.
     */
    public function followerRelationships(): HasMany
    {
        return $this->hasMany(Follow::class, 'followed_id');
    }

    /**
     * Get the contact requests sent by the user.
     */
    public function sentContactRequests(): HasMany
    {
        return $this->hasMany(ContactRequest::class, 'sender_id');
    }

    /**
     * Get the contact requests received by the user.
     */
    public function receivedContactRequests(): HasMany
    {
        return $this->hasMany(ContactRequest::class, 'receiver_id');
    }

    /**
     * Get the user's conversation participant records.
     */
    public function conversationParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Get the conversations the user participates in.
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * Get the messages sent by the user.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get the users this user follows.
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')
            ->withTimestamps();
    }

    /**
     * Get the users following this user.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * Determine whether this user follows the given user.
     */
    public function isFollowing(User $user): bool
    {
        return $this->followingRelationships()
            ->where('followed_id', $user->id)
            ->exists();
    }

    /**
     * Determine whether this user has a mutual follow with the given user.
     */
    public function isMutualWith(User $user): bool
    {
        return $this->isFollowing($user) && $user->isFollowing($this);
    }
}
