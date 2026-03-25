<?php

namespace App\Console\Commands;

use App\Models\AuctionImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-images
                            {--dry-run : Show what would be deleted without deleting}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and remove orphaned auction images';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $disk = Storage::disk('public');
        $dryRun = $this->option('dry-run');

        // Find DB records pointing to missing files
        $missingFiles = [];
        AuctionImage::chunk(200, function ($images) use ($disk, &$missingFiles) {
            foreach ($images as $image) {
                if (!$disk->exists($image->path)) {
                    $missingFiles[] = $image;
                }
            }
        });

        // Find files on disk not referenced by any DB record
        $dbPaths = AuctionImage::pluck('path');
        $allDiskFiles = array_filter($disk->allFiles('auctions'), 'is_string');
        $orphanedFiles = array_values(array_filter($allDiskFiles, fn(string $file) => !$dbPaths->contains($file)));

        if ($missingFiles === [] && $orphanedFiles === []) {
            $this->info('No orphaned images found. Everything is clean.');
            return;
        }

        if ($missingFiles !== []) {
            $this->warn(count($missingFiles) . ' DB record(s) with missing files:');
            $rows = array_map(fn(AuctionImage $img) => [$img->id, $img->auction_id, $img->path], $missingFiles);
            $this->table(['Image ID', 'Auction ID', 'Path'], $rows);
        }

        if ($orphanedFiles !== []) {
            $this->warn(count($orphanedFiles) . ' file(s) on disk not in database:');
            foreach ($orphanedFiles as $file) {
                $this->line("  {$file}");
            }
        }

        if ($dryRun) {
            $this->info('Dry run — no changes made.');
            return;
        }

        if (!$this->option('force') && !$this->confirm('Delete these orphaned records and files?')) {
            return;
        }

        foreach ($missingFiles as $image) {
            $image->delete();
        }
        if ($missingFiles !== []) {
            $this->info('Deleted ' . count($missingFiles) . ' orphaned DB record(s).');
        }

        foreach ($orphanedFiles as $file) {
            $disk->delete($file);
        }
        if ($orphanedFiles !== []) {
            $this->info('Deleted ' . count($orphanedFiles) . ' orphaned file(s) from disk.');
        }
    }
}
