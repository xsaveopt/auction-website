<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sellers_cannot_bid_ask_buy_or_offer_on_their_own_auction(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $auction = $this->createAuction($seller);

        foreach (['bid', 'askQuestion', 'purchaseLeftover', 'offerOnLeftover'] as $ability) {
            $this->assertTrue(Gate::forUser($seller)->denies($ability, $auction), $ability);
            $this->assertTrue(Gate::forUser($buyer)->allows($ability, $auction), $ability);
        }

        $this->assertSame(422, Gate::forUser($seller)->inspect('bid', $auction)->status());
        $this->assertSame(422, Gate::forUser($seller)->inspect('askQuestion', $auction)->status());
        $this->assertNull(Gate::forUser($seller)->inspect('purchaseLeftover', $auction)->status());
        $this->assertNull(Gate::forUser($seller)->inspect('offerOnLeftover', $auction)->status());
    }

    public function test_questions_can_be_answered_and_deleted_by_the_seller_or_an_admin_only(): void
    {
        $seller = $this->createUser();
        $asker = $this->createUser();
        $auction = $this->createAuction($seller);
        $question = $this->createQuestion($auction, $asker);

        foreach (['answer', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($seller)->allows($ability, $question), $ability);
            $this->assertTrue(Gate::forUser($this->createAdmin())->allows($ability, $question), $ability);
            $this->assertTrue(Gate::forUser($asker)->denies($ability, $question), $ability);
            $this->assertTrue(Gate::forUser($this->createUser())->denies($ability, $question), $ability);
        }
    }

    public function test_audit_log_comments_are_limited_to_the_entry_author(): void
    {
        $author = $this->createAdmin();
        $other = $this->createAdmin();
        AuditLog::record($author, 'auction.create');
        $log = AuditLog::query()->sole();

        $this->assertTrue(Gate::forUser($author)->allows('comment', $log));
        $this->assertTrue(Gate::forUser($other)->denies('comment', $log));
    }
}
