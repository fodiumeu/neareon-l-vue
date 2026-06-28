<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'label',
    'sort_order',
    'is_active',
])]
class InterestOption extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the profiles that selected this interest option.
     */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'profile_interests')
            ->withTimestamps();
    }

    /**
     * Get the groups that use this interest option as their main category.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'category_interest_option_id');
    }

    /**
     * Get the events that use this interest option as their main category.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_interest_option_id');
    }
}
