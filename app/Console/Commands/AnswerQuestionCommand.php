<?php

namespace App\Console\Commands;

use App\Models\AuctionQuestion;
use Illuminate\Console\Command;

class AnswerQuestionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:answer-question
                            {id : The question ID}
                            {answer? : The answer text (prompted if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Answer an auction question';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $question = AuctionQuestion::with(['user', 'auction'])->find($this->argument('id'));

        if (!$question) {
            $this->error("Question with ID {$this->argument('id')} not found.");
            return;
        }

        $this->info('Auction: ' . ($question->auction->title ?? 'Unknown'));
        $this->info('Asked by: ' . ($question->user->username ?? 'Unknown'));
        $this->info('Question: ' . $question->question);

        if ($question->answered_at) {
            $this->warn("Already answered: {$question->answer}");
            if (!$this->confirm('Overwrite the existing answer?')) {
                return;
            }
        }

        $this->newLine();

        /** @var string|null $answer */
        $answer = $this->argument('answer') ?? $this->ask('Your answer');

        if (!$answer) {
            $this->error('Answer cannot be empty.');
            return;
        }

        $question->answer = $answer;
        $question->answered_at = now();
        $question->save();

        $this->info('Question answered.');
    }
}
