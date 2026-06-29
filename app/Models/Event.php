<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id',
    'category_interest_option_id',
    'title',
    'slug',
    'description',
    'starts_at',
    'ends_at',
    'region',
    'postal_code',
    'country_code',
    'visibility',
    'status',
    'max_attendees',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_REQUEST = 'request';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'max_attendees' => 'integer',
            'starts_at' => 'datetime',
        ];
    }

    /**
     * Get the user who owns the event.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the managed interest option used as this event's main category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InterestOption::class, 'category_interest_option_id');
    }

    /**
     * Get all event attendee records.
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    /**
     * Get active event attendee records.
     */
    public function activeAttendees(): HasMany
    {
        return $this->attendees()->where('status', EventAttendee::STATUS_ACTIVE);
    }

    /**
     * Get pending event attendee records.
     */
    public function pendingAttendees(): HasMany
    {
        return $this->attendees()->where('status', EventAttendee::STATUS_PENDING);
    }

    /**
     * Restrict the query to active events.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Restrict the query to events that have not started yet.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now());
    }

    /**
     * Restrict the query to events that are upcoming, ongoing, or undated.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeNotPast(Builder $query): Builder
    {
        return $query->where(function (Builder $timeQuery): void {
            $timeQuery
                ->whereNull('starts_at')
                ->orWhere('starts_at', '>=', now())
                ->orWhere(function (Builder $ongoingQuery): void {
                    $ongoingQuery
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '>=', now());
                });
        });
    }

    public function isPast(): bool
    {
        if ($this->starts_at === null) {
            return false;
        }

        if ($this->ends_at !== null) {
            return $this->ends_at->isPast();
        }

        return $this->starts_at->isPast();
    }

    /**
     * Restrict the query to active current public/request events.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeVisibleForDiscover(Builder $query): Builder
    {
        return $query
            ->active()
            ->notPast()
            ->whereIn('visibility', [
                self::VISIBILITY_PUBLIC,
                self::VISIBILITY_REQUEST,
            ]);
    }
}
