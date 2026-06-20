<?php

namespace App\Services;

use App\Models\Profile;

class DiscoverRankingService
{
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
}
