<?php

namespace App\Models;

use App\Support\Presence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresenceHeartbeat extends Model
{
    protected $primaryKey = 'page_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'page_id',
        'client_id',
        'page_type',
        'auction_id',
        'last_seen_at',
    ];

    /** @return BelongsTo<\App\Models\Auction, $this> */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /** @param Builder<PresenceHeartbeat> $query */
    public function scopeRecent(Builder $query): void
    {
        $query->where('last_seen_at', '>=', Presence::cutoff());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
