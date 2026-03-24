<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_ask_a_question_but_not_on_their_own_auction(): void
    {
        $seller = $this->createUser();
        $bidder = $this->createUser();
        $auction = $this->createAuction($seller);

        $this
            ->actingAs($bidder)
            ->postJson("/api/auctions/{$auction->id}/questions", [
                'question' => 'Can this be picked up tomorrow?',
            ])
            ->assertCreated()
            ->assertJsonPath('question.question', 'Can this be picked up tomorrow?');

        $this
            ->actingAs($seller)
            ->postJson("/api/auctions/{$auction->id}/questions", [
                'question' => 'Can I ask myself this?',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot ask a question on your own auction.');
    }

    public function test_only_the_seller_or_an_admin_can_answer_a_question(): void
    {
        $seller = $this->createUser();
        $bidder = $this->createUser();
        $otherUser = $this->createUser();
        $auction = $this->createAuction($seller);
        $question = $this->createQuestion($auction, $bidder);

        $this
            ->actingAs($otherUser)
            ->putJson("/api/questions/{$question->id}", [
                'answer' => 'Nope',
            ])
            ->assertForbidden();

        $this
            ->actingAs($seller)
            ->putJson("/api/questions/{$question->id}", [
                'answer' => 'Yes, pickup works.',
            ])
            ->assertOk()
            ->assertJsonPath('question.answer', 'Yes, pickup works.');
    }

    public function test_admin_can_list_and_delete_questions(): void
    {
        $admin = $this->createAdmin();
        $question = $this->createQuestion($this->createAuction());

        $this
            ->actingAs($admin)
            ->getJson('/api/questions')
            ->assertOk()
            ->assertJsonPath('questions.0.id', $question->id);

        $this
            ->actingAs($admin)
            ->deleteJson("/api/questions/{$question->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Question deleted.');

        $this->assertSoftDeleted('auction_questions', ['id' => $question->id]);
    }
}
