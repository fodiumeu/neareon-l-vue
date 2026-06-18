<?php

namespace App\Support;

class ProfileOptionBackfillResult
{
    public int $profilesProcessed = 0;

    public int $languagesAttached = 0;

    public int $languagesUpdated = 0;

    public int $interestsAttached = 0;

    /**
     * @var list<array{profile_id: int, type: 'language'|'interest', value: string}>
     */
    public array $unknownValues = [];

    public function addUnknown(int $profileId, string $type, string $value): void
    {
        $unknown = [
            'profile_id' => $profileId,
            'type' => $type,
            'value' => $value,
        ];

        if (! in_array($unknown, $this->unknownValues, true)) {
            $this->unknownValues[] = $unknown;
        }
    }
}
