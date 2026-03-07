<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Illuminate\Support\Carbon $ends_at
 */
class Auction extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'title',
        'description',
        'starting_price',
        'quantity',
        'max_per_bidder',
        'ends_at',
        'status',
    ];

    /** @return BelongsTo<\App\Models\User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
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
        return $this->hasMany(AuctionQuestion::class)
            ->orderByRaw('CASE WHEN answer IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('answered_at')
            ->orderBy('created_at');
    }

    public function currentPrice(): float
    {
        return (float) ($this->bids()->max('amount') ?? $this->starting_price);
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
