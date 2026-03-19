<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['is_locked', 'lock_message'];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    public static function instance(): self
    {
        /** @var self */
        return self::firstOrCreate(['id' => 1]);
    }

    public static function isLocked(): bool
    {
        return self::instance()->is_locked;
    }
}
