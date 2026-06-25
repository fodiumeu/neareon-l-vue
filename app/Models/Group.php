<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'owner_id',
    'name',
    'slug',
    'description',
    'region',
    'postal_code',
    'country_code',
    'category_interest_option_id',
    'visibility',
    'status',
    'invite_token',
    'invite_token_created_at',
])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_REQUEST = 'request';

    public const VISIBILITY_PRIVATE = 'private';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invite_token_created_at' => 'datetime',
        ];
    }

    /**
     * Get the user who owns the group.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all group membership records.
     */
    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Get active group membership records.
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', GroupMember::STATUS_ACTIVE);
    }

    /**
     * Get users attached to the group through membership records.
     */
    public function memberUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get the managed interest option used as this group's main category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InterestOption::class, 'category_interest_option_id');
    }

    /**
     * Restrict the query to active groups.
     *
     * @param  Builder<Group>  $query
     * @return Builder<Group>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function hasInviteToken(): bool
    {
        return filled($this->invite_token);
    }

    public function rotateInviteToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::query()
            ->where('invite_token', $token)
            ->whereKeyNot($this->getKey())
            ->exists());

        $this->forceFill([
            'invite_token' => $token,
            'invite_token_created_at' => now(),
        ])->save();

        return $token;
    }
}
