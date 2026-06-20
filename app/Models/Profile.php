<?php

namespace App\Models;

use App\Enums\ContactPermission;
use App\Enums\FollowPermission;
use App\Enums\MessagePermission;
use App\Enums\OnlineStatusVisibility;
use App\Enums\ProfileVisibility;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id',
    'username',
    'display_name',
    'bio',
    'profile_photo_path',
    'region',
    'profile_visibility',
    'follow_permission',
    'contact_permission',
    'message_permission',
    'online_status_visibility',
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
            'contact_permission' => ContactPermission::class,
            'follow_permission' => FollowPermission::class,
            'interests_visibility' => ProfileVisibility::class,
            'languages_visibility' => ProfileVisibility::class,
            'message_permission' => MessagePermission::class,
            'online_status_visibility' => OnlineStatusVisibility::class,
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

    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo_path === null
            ? null
            : Storage::disk('public')->url($this->profile_photo_path);
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
