<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $auction_id
 * @property int $user_id
 * @property int $quantity
 * @property string $price_per_item
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class LeftoverPurchase extends Model
{
    /** @var list<string> */
    protected $fillable = ['auction_id', 'user_id', 'quantity', 'price_per_item'];

    /** @return BelongsTo<\App\Models\Auction, $this> */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
