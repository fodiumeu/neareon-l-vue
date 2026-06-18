<?php

namespace App\Models;

use App\Enums\ProfileVisibility;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'user_id',
    'username',
    'display_name',
    'bio',
    'region',
    'profile_visibility',
    'interests_visibility',
    'languages_visibility',
    'region_visibility',
    'social_counts_visibility',
])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interests_visibility' => ProfileVisibility::class,
            'languages_visibility' => ProfileVisibility::class,
            'profile_visibility' => ProfileVisibility::class,
            'region_visibility' => ProfileVisibility::class,
            'social_counts_visibility' => ProfileVisibility::class,
        ];
    }

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the managed language options selected for the profile.
     */
    public function languageOptions(): BelongsToMany
    {
        return $this->belongsToMany(LanguageOption::class, 'profile_languages')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * Get the managed interest options selected for the profile.
     */
    public function interestOptions(): BelongsToMany
    {
        return $this->belongsToMany(InterestOption::class, 'profile_interests')
            ->withTimestamps();
    }
}
