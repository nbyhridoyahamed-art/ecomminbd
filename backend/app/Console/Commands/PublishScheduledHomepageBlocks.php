<?php

namespace App\Console\Commands;

use App\Models\HomepageBlock;
use App\Models\HomepageBlockRevision;
use Illuminate\Console\Command;

/**
 * Spec section 168 ("Scheduled content") / section 62 ("Schedule"): a
 * block with a `scheduled_at` in the past that isn't live yet goes live
 * here, the same is_active flip the manual publish action performs —
 * scheduling is just a deferred publish, not a separate state.
 */
class PublishScheduledHomepageBlocks extends Command
{
    protected $signature = 'homepage-blocks:publish-scheduled';

    protected $description = 'Publish homepage blocks whose scheduled_at time has passed';

    public function handle(): int
    {
        $due = HomepageBlock::query()
            ->where('is_active', false)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $block) {
            HomepageBlockRevision::create([
                'homepage_block_id' => $block->id,
                'store_id' => $block->store_id,
                'snapshot' => $block->toSnapshot(),
            ]);

            $block->update(['is_active' => true, 'scheduled_at' => null]);
        }

        $this->info("Published {$due->count()} scheduled homepage block(s).");

        return self::SUCCESS;
    }
}
