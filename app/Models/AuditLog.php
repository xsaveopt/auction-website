<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $action
 * @property string|null $target_type
 * @property int|null $target_id
 * @property array<string, mixed>|null $data
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon $created_at
 */
class AuditLog extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['user_id', 'action', 'target_type', 'target_id', 'data', 'comment', 'created_at'];

    /** @var array<string, string> */
    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function record(User $admin, string $action, ?Model $target = null, array $data = []): void
    {
        static::create([
            'user_id' => $admin->id,
            'action' => $action,
            'target_type' => $target !== null ? class_basename($target) : null,
            'target_id' => $target?->getKey(),
            'data' => empty($data) ? null : $data,
            'created_at' => now(),
        ]);
    }
}
