<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RequestMetric extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'method',
        'path',
        'status',
        'duration_ms',
    ];

    /** @param Builder<RequestMetric> $query */
    public function scopeRecent(Builder $query, Carbon $since): void
    {
        $query->where('created_at', '>=', $since);
    }

    public static function pruneOlderThan(Carbon $cutoff): int
    {
        /** @var int $deleted */
        $deleted = self::query()->where('created_at', '<', $cutoff)->delete();

        return $deleted;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
