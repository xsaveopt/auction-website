<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_questions_shows_all_questions(): void
    {
        $auction = $this->createAuction();
        $this->createQuestion($auction);

        $this->artisan('app:list-questions')->assertExitCode(0);
    }

    public function test_list_questions_filters_unanswered_and_by_auction(): void
    {
        $auction = $this->createAuction();
        $this->createQuestion($auction);

        $this->artisan('app:list-questions', [
            '--unanswered' => true,
            '--auction' => (string) $auction->id,
        ])->assertExitCode(0);
    }

    public function test_list_questions_reports_when_none_found(): void
    {
        $this->artisan('app:list-questions')->expectsOutput('No questions found.')->assertExitCode(0);
    }

    public function test_answer_question_answers_with_provided_text(): void
    {
        $auction = $this->createAuction();
        $question = $this->createQuestion($auction);

        $this->artisan('app:answer-question', [
            'id' => $question->id,
            'answer' => 'Yes, still available.',
        ])->assertExitCode(0);

        $fresh = $question->fresh();
        $this->assertSame('Yes, still available.', $fresh->answer);
        $this->assertNotNull($fresh->answered_at);
    }

    public function test_answer_question_prompts_when_answer_omitted(): void
    {
        $auction = $this->createAuction();
        $question = $this->createQuestion($auction);

        $this
            ->artisan('app:answer-question', ['id' => $question->id])
            ->expectsQuestion('Your answer', 'Prompted answer')
            ->assertExitCode(0);

        $this->assertSame('Prompted answer', $question->fresh()->answer);
    }

    public function test_answer_question_can_overwrite_existing_answer(): void
    {
        $auction = $this->createAuction();
        $question = $this->createQuestion($auction, null, [
            'answer' => 'Old answer',
            'answered_at' => now(),
        ]);

        $this
            ->artisan('app:answer-question', ['id' => $question->id, 'answer' => 'New answer'])
            ->expectsConfirmation('Overwrite the existing answer?', 'yes')
            ->assertExitCode(0);

        $this->assertSame('New answer', $question->fresh()->answer);
    }

    public function test_answer_question_declining_overwrite_keeps_old_answer(): void
    {
        $auction = $this->createAuction();
        $question = $this->createQuestion($auction, null, [
            'answer' => 'Old answer',
            'answered_at' => now(),
        ]);

        $this
            ->artisan('app:answer-question', ['id' => $question->id, 'answer' => 'New answer'])
            ->expectsConfirmation('Overwrite the existing answer?', 'no')
            ->assertExitCode(0);

        $this->assertSame('Old answer', $question->fresh()->answer);
    }

    public function test_answer_question_fails_for_unknown_question(): void
    {
        $this->artisan('app:answer-question', ['id' => 999999, 'answer' => 'irrelevant'])->assertExitCode(0);
    }

    public function test_export_results_writes_csv_for_a_specific_auction(): void
    {
        $auction = $this->createAuction(null, ['status' => 'ended', 'quantity' => 1]);
        $this->createBid($auction, null, ['amount' => '40.00', 'quantity' => 1]);

        $path = tempnam(sys_get_temp_dir(), 'export') . '.csv';

        $this->artisan('app:export-results', ['id' => $auction->id, '--output' => $path])->assertExitCode(0);

        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertStringContainsString($auction->title, $contents);

        unlink($path);
    }

    public function test_export_results_reports_when_no_ended_auctions(): void
    {
        $this->artisan('app:export-results')->expectsOutput('No ended auctions found.')->assertExitCode(0);
    }

    public function test_export_results_fails_for_unknown_auction(): void
    {
        $this->artisan('app:export-results', ['id' => 999999])->assertExitCode(0);
    }

    public function test_show_stats_displays_platform_statistics(): void
    {
        $auction = $this->createAuction(null, ['status' => 'active', 'ends_at' => now()->addHour()]);
        $this->createBid($auction);

        $this->artisan('app:stats')->assertExitCode(0);
    }
}
