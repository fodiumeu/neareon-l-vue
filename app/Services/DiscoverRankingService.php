<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DiscoverRankingService
{
    public function applyToQuery(
        Builder $query,
        User $viewer,
        Profile $viewerProfile,
        array $viewerLanguageIds,
        array $viewerInterestIds,
    ): Builder {
        $following = $this->followingExistsSql($viewer->id);
        $followedBy = $this->followedByExistsSql($viewer->id);
        $mutual = "({$following} AND {$followedBy})";
        $regionVisible = $this->fieldVisibleSql(
            'profiles.region_visibility',
            $following,
            $mutual,
        );
        $languagesVisible = $this->fieldVisibleSql(
            'profiles.languages_visibility',
            $following,
            $mutual,
        );
        $interestsVisible = $this->fieldVisibleSql(
            'profiles.interests_visibility',
            $following,
            $mutual,
        );
        $region = mb_strtolower(trim((string) $viewerProfile->region));
        $languageScore = $this->optionScoreSql(
            'profile_languages',
            'language_option_id',
            $viewerLanguageIds,
            40,
        );
        $interestScore = $this->optionScoreSql(
            'profile_interests',
            'interest_option_id',
            $viewerInterestIds,
            15,
        );

        return $query->selectRaw(
            "(
                CASE
                    WHEN ? <> ''
                        AND {$regionVisible}
                        AND LOWER(TRIM(profiles.region)) = ?
                    THEN 100 ELSE 0
                END
                + CASE WHEN {$languagesVisible} THEN {$languageScore} ELSE 0 END
                + CASE WHEN {$interestsVisible} THEN {$interestScore} ELSE 0 END
                + CASE WHEN {$mutual} THEN 10 ELSE 0 END
            ) AS discover_score",
            [$region, $region],
        );
    }

    /**
     * Calculate a discover score from visible profile data.
     *
     * @param  array<string, mixed>  $visibleData
     * @param  list<int>  $viewerLanguageIds
     * @param  list<int>  $viewerInterestIds
     */
    public function score(
        Profile $viewerProfile,
        Profile $profile,
        array $visibleData,
        array $viewerLanguageIds,
        array $viewerInterestIds,
    ): int {
        $score = 0;

        if (isset($visibleData['region'])
            && $this->sameRegion($viewerProfile->region, $profile->region)) {
            $score += 100;
        }

        if (array_key_exists('languages', $visibleData)) {
            $score += $profile->languageOptions
                ->whereIn('id', $viewerLanguageIds)
                ->count() * 40;
        }

        if (array_key_exists('interests', $visibleData)) {
            $score += $profile->interestOptions
                ->whereIn('id', $viewerInterestIds)
                ->count() * 15;
        }

        if ($visibleData['is_mutual']) {
            $score += 10;
        }

        return $score;
    }

    private function sameRegion(?string $viewerRegion, ?string $profileRegion): bool
    {
        if ($viewerRegion === null || $profileRegion === null) {
            return false;
        }

        return mb_strtolower(trim($viewerRegion))
            === mb_strtolower(trim($profileRegion));
    }

    private function followingExistsSql(int $viewerId): string
    {
        return "EXISTS (
            SELECT 1 FROM follows
            WHERE follows.follower_id = {$viewerId}
              AND follows.followed_id = profiles.user_id
        )";
    }

    private function followedByExistsSql(int $viewerId): string
    {
        return "EXISTS (
            SELECT 1 FROM follows
            WHERE follows.follower_id = profiles.user_id
              AND follows.followed_id = {$viewerId}
        )";
    }

    private function fieldVisibleSql(
        string $column,
        string $following,
        string $mutual,
    ): string {
        return "(
            {$column} IN ('public', 'members')
            OR ({$column} = 'followers' AND {$following})
            OR ({$column} IN ('contacts', 'mutuals') AND {$mutual})
        )";
    }

    /**
     * @param  list<int>  $optionIds
     */
    private function optionScoreSql(
        string $table,
        string $optionColumn,
        array $optionIds,
        int $weight,
    ): string {
        if ($optionIds === []) {
            return '0';
        }

        $ids = implode(',', array_map('intval', $optionIds));

        return "(
            SELECT COUNT(*) * {$weight}
            FROM {$table}
            WHERE {$table}.profile_id = profiles.id
              AND {$table}.{$optionColumn} IN ({$ids})
        )";
    }
}
