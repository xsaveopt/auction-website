<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuctionRound extends Model
{
    protected $fillable = ['name', 'status', 'ends_at'];

    protected $casts = [
        'ends_at' => 'datetime',
    ];

    /** @return HasMany<Auction, $this> */
    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class);
    }
}
