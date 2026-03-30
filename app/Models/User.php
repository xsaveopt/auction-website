<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $username
 * @property bool $is_admin
 * @property string|null $microsoft_id
 * @property string|null $payment_reference
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'username',
        'password',
        'microsoft_id',
        'payment_reference',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return HasMany<\App\Models\Auction, $this> */
    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class, 'seller_id');
    }

    /** @return HasMany<\App\Models\Bid, $this> */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /** @return HasMany<\App\Models\PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
