<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $seller_id
 * @property string $title
 * @property string $description
 * @property string $starting_price
 * @property int $quantity
 * @property int $max_per_bidder
 * @property string $status
 * @property int|null $category_id
 * @property \Illuminate\Support\Carbon $ends_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read int $watcher_count
 */
class Auction extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'seller_id',
        'title',
        'description',
        'starting_price',
        'quantity',
        'max_per_bidder',
        'ends_at',
        'status',
        'category_id',
    ];

    /** @return BelongsTo<\App\Models\User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<\App\Models\Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<\App\Models\AuctionImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(AuctionImage::class)->orderBy('sort_order');
    }

    /** @return HasMany<\App\Models\Bid, $this> */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /** @return HasMany<\App\Models\AuctionQuestion, $this> */
    public function questions(): HasMany
    {
        return $this
            ->hasMany(AuctionQuestion::class)
            ->orderByRaw('CASE WHEN answer IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('answered_at')
            ->orderBy('created_at');
    }

    public function currentPrice(): float
    {
        /** @var string|null $maxBid */
        $maxBid = $this->bids()->max('amount');

        return floatval($maxBid ?? $this->starting_price);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at > now();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starting_price' => 'decimal:2',
            'ends_at' => 'datetime',
        ];
    }
}
