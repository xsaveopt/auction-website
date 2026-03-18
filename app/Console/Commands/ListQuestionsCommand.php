<?php

namespace App\Console\Commands;

use App\Models\AuctionQuestion;
use Illuminate\Console\Command;

class ListQuestionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:list-questions
                            {--unanswered : Show only unanswered questions}
                            {--auction= : Filter by auction ID}
                            {--limit=20 : Number of questions to show}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List auction questions';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $query = AuctionQuestion::with(['user', 'auction'])->orderByRaw('answered_at IS NOT NULL')->latest();

        if ($this->option('unanswered')) {
            $query->whereNull('answered_at');
        }

        $auctionId = $this->option('auction');
        if ($auctionId !== null) {
            $query->where('auction_id', $auctionId);
        }

        $questions = $query->limit((int) $this->option('limit'))->get();

        if ($questions->isEmpty()) {
            $this->info('No questions found.');
            return;
        }

        $rows = $questions->map(fn(AuctionQuestion $q) => [
            $q->id,
            $q->auction->title ?? 'Unknown',
            $q->user->username ?? 'Unknown',
            mb_strimwidth($q->question, 0, 50, '...'),
            $q->answered_at ? 'Yes' : 'No',
            $q->created_at?->toDateTimeString() ?? '',
        ]);

        $this->table(['ID', 'Auction', 'User', 'Question', 'Answered', 'Date'], $rows);
    }
}
