<?php

namespace App\Console\Commands;

use App\Services\DraftCheckoutService;
use Illuminate\Console\Command;

class CleanupExpiredDraftsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'draft:cleanup {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup expired draft checkouts';

    /**
     * Execute the console command.
     */
    public function handle(DraftCheckoutService $draftService): int
    {
        $this->info('Starting cleanup of expired draft checkouts...');

        $cleanedCount = $draftService->cleanupExpiredDrafts();

        if ($cleanedCount > 0) {
            $this->info("Successfully cleaned up {$cleanedCount} expired draft checkouts.");
        } else {
            $this->info('No expired draft checkouts found.');
        }

        // Hiển thị thống kê
        $stats = $draftService->getDraftStats();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Drafts', $stats['total_drafts']],
                ['Active Drafts', $stats['active_drafts']],
                ['Expired Drafts', $stats['expired_drafts']],
                ['Completed Drafts', $stats['completed_drafts']],
            ]
        );

        return Command::SUCCESS;
    }
}

