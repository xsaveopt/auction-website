<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $auction_id
 * @property int $user_id
 * @property int $quantity
 * @property string $offered_price_per_item
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $rebid_requested_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class LeftoverPriceOffer extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'auction_id',
        'user_id',
        'quantity',
        'offered_price_per_item',
        'status',
        'rebid_requested_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rebid_requested_at' => 'datetime',
    ];

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
