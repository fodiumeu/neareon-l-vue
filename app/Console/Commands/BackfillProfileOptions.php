<?php

namespace App\Console\Commands;

use App\Services\ProfileOptionBackfillService;
use Illuminate\Console\Command;

class BackfillProfileOptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'neareon:backfill-profile-options';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill managed profile option pivots from the existing JSON fields';

    public function handle(ProfileOptionBackfillService $backfill): int
    {
        $this->info('Backfilling profile option pivots from JSON values...');

        $result = $backfill->backfill();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Profiles processed', $result->profilesProcessed],
                ['Languages attached', $result->languagesAttached],
                ['Language positions updated', $result->languagesUpdated],
                ['Interests attached', $result->interestsAttached],
                ['Unknown values', count($result->unknownValues)],
            ],
        );

        foreach ($result->unknownValues as $unknown) {
            $this->warn(sprintf(
                'Unknown %s value "%s" for profile %d.',
                $unknown['type'],
                $unknown['value'],
                $unknown['profile_id'],
            ));
        }

        $this->info('Profile option backfill finished.');

        return self::SUCCESS;
    }
}
