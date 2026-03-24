<?php

namespace App\Console\Commands;

use App\Models\Auction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BulkUpdateAuctionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-auctions
                            {--ids= : Comma-separated list of auction IDs to update}
                            {--all-active : Update all active auctions}
                            {--add-time= : Add time to ends_at (e.g., "+1 hour", "-30 minutes")}
                            {--set-end= : Set specific end time (Y-m-d H:i:s)}
                            {--status= : Update status (active, closed, draft)}
                            {--append-description= : Text to append to each description (supports \n for newlines)}
                            {--prepend-description= : Text to prepend to each description (supports \n for newlines)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk update auctions (end time, status, description)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $ids = $this->option('ids');
        $allActive = $this->option('all-active');

        if (!$ids && !$allActive) {
            $this->error('You must specify either --ids or --all-active.');
            return;
        }

        $query = Auction::query();

        if ($ids) {
            $idList = explode(',', $ids);
            $query->whereIn('id', $idList);
        } elseif ($allActive) {
            $query->where('status', 'active')->where('ends_at', '>', now());
        }

        $count = $query->count();
        if ($count === 0) {
            $this->info('No auctions found matching criteria.');
            return;
        }

        if (!$this->confirm("You are about to update {$count} auctions. Continue?")) {
            return;
        }

        $addTime = $this->option('add-time');
        $setEnd = $this->option('set-end');
        $status = $this->option('status');
        $appendDesc = $this->option('append-description') !== null
            ? str_replace('\n', "\n", $this->option('append-description'))
            : null;
        $prependDesc = $this->option('prepend-description') !== null
            ? str_replace('\n', "\n", $this->option('prepend-description'))
            : null;

        $updatedCount = 0;

        $query->chunkById(100, function ($auctions) use (
            $addTime,
            $setEnd,
            $status,
            $appendDesc,
            $prependDesc,
            &$updatedCount,
        ) {
            foreach ($auctions as $auction) {
                $updated = false;

                if ($addTime) {
                    try {
                        // Use modify directly with user input (e.g., "+1 hour")
                        // If user omits sign (e.g., "1 hour"), prepend '+'
                        $modifier = trim($addTime);
                        if (!str_starts_with($modifier, '+') && !str_starts_with($modifier, '-')) {
                            $modifier = "+{$modifier}";
                        }

                        $auction->ends_at = $auction->ends_at->modify($modifier);
                        $updated = true;
                    } catch (\Exception $e) {
                        $this->error("Invalid time format for auction {$auction->id}: {$addTime}");
                    }
                }

                if ($setEnd) {
                    try {
                        $auction->ends_at = Carbon::parse($setEnd);
                        $updated = true;
                    } catch (\Exception $e) {
                        $this->error("Invalid date format for auction {$auction->id}: {$setEnd}");
                    }
                }

                if ($status) {
                    $auction->status = $status;
                    $updated = true;
                }

                if ($appendDesc !== null) {
                    $auction->description .= $appendDesc;
                    $updated = true;
                }

                if ($prependDesc !== null) {
                    $auction->description = $prependDesc . $auction->description;
                    $updated = true;
                }

                if ($updated) {
                    $auction->save();
                    $updatedCount++;
                }
            }
        });

        $this->info("Successfully updated {$updatedCount} auctions.");
    }
}
