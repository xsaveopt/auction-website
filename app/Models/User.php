<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'username',
        'password',
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
