<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionQuestionTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private User $otherBuyer;

    private Auction $auction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::query()->create([
            'username' => 'seller@example.com',
            'password' => 'password123',
        ]);

        $this->buyer = User::query()->create([
            'username' => 'buyer@example.com',
            'password' => 'password123',
        ]);

        $this->otherBuyer = User::query()->create([
            'username' => 'other-buyer@example.com',
            'password' => 'password123',
        ]);

        $this->auction = $this->seller->auctions()->create([
            'title' => 'Rare Console',
            'description' => 'Collector condition.',
            'starting_price' => 10,
            'quantity' => 1,
            'max_per_bidder' => 1,
            'ends_at' => now()->addDay(),
            'status' => 'active',
        ]);
    }

    public function test_authenticated_user_can_ask_question(): void
    {
        $this->actingAs($this->buyer)
            ->postJson("/api/auctions/{$this->auction->id}/questions", [
                'question' => 'Does it include the original cables?',
            ])
            ->assertCreated()
            ->assertJsonPath('question.question', 'Does it include the original cables?')
            ->assertJsonPath('question.answer', null)
            ->assertJsonPath('question.user.id', $this->buyer->id);

        $this->assertDatabaseHas('auction_questions', [
            'auction_id' => $this->auction->id,
            'user_id' => $this->buyer->id,
            'question' => 'Does it include the original cables?',
            'answer' => null,
        ]);
    }

    public function test_seller_can_answer_question(): void
    {
        $question = $this->createQuestion();

        $this->actingAs($this->seller)
            ->putJson("/api/questions/{$question->id}", [
                'answer' => 'Yes, all original cables are included.',
            ])
            ->assertOk()
            ->assertJsonPath('question.answer', 'Yes, all original cables are included.');

        $this->assertDatabaseHas('auction_questions', [
            'id' => $question->id,
            'answer' => 'Yes, all original cables are included.',
        ]);

        $this->assertNotNull($question->fresh()->answered_at);
    }

    public function test_non_seller_cannot_answer_question(): void
    {
        $question = $this->createQuestion();

        $this->actingAs($this->otherBuyer)
            ->putJson("/api/questions/{$question->id}", [
                'answer' => 'I should not be able to do this.',
            ])
            ->assertForbidden();
    }

    public function test_seller_can_delete_question(): void
    {
        $question = $this->createQuestion();

        $this->actingAs($this->seller)
            ->deleteJson("/api/questions/{$question->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Question deleted.');

        $this->assertDatabaseMissing('auction_questions', [
            'id' => $question->id,
        ]);
    }

    public function test_auction_detail_includes_questions_with_answered_items_first(): void
    {
        $answered = $this->auction->questions()->create([
            'user_id' => $this->buyer->id,
            'question' => 'What is the battery life?',
            'answer' => 'Roughly six hours.',
            'answered_at' => now()->subHour(),
        ]);

        $unanswered = $this->auction->questions()->create([
            'user_id' => $this->otherBuyer->id,
            'question' => 'Are there any scratches?',
        ]);

        $this->getJson("/api/auctions/{$this->auction->id}")
            ->assertOk()
            ->assertJsonPath('auction.questions.0.id', $answered->id)
            ->assertJsonPath('auction.questions.0.answer', 'Roughly six hours.')
            ->assertJsonPath('auction.questions.1.id', $unanswered->id)
            ->assertJsonPath('auction.questions.1.answer', null);
    }

    public function test_unauthenticated_user_cannot_ask_question(): void
    {
        $this->postJson("/api/auctions/{$this->auction->id}/questions", [
            'question' => 'Can I pick this up locally?',
        ])->assertUnauthorized();
    }

    private function createQuestion(): AuctionQuestion
    {
        return $this->auction->questions()->create([
            'user_id' => $this->buyer->id,
            'question' => 'Does it include the original box?',
        ]);
    }
}
