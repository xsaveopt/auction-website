<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionImage extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'path',
        'sort_order',
    ];

    /** @return BelongsTo<\App\Models\Auction, $this> */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }
}
