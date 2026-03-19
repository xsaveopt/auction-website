<?php

namespace App\Support;

use App\Models\PresenceHeartbeat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Presence
{
    public const HEARTBEAT_INTERVAL_SECONDS = 3;

    public const HEARTBEAT_TTL_SECONDS = 9;

    public static function cutoff(): Carbon
    {
        return now()->subSeconds(self::HEARTBEAT_TTL_SECONDS);
    }

    private const CLEANUP_INTERVAL_SECONDS = 30;

    public static function heartbeat(
        string $pageId,
        string $clientId,
        string $pageType,
        ?int $auctionId = null,
        ?int $userId = null,
    ): void {
        $now = now();

        if (!apcu_exists('presence_cleanup') || apcu_fetch('presence_cleanup') < time()) {
            PresenceHeartbeat::query()->where('last_seen_at', '<', self::cutoff())->delete();
            apcu_store('presence_cleanup', time() + self::CLEANUP_INTERVAL_SECONDS);
        }

        PresenceHeartbeat::query()->upsert(
            [
                [
                    'page_id' => $pageId,
                    'client_id' => $clientId,
                    'user_id' => $userId,
                    'page_type' => $pageType,
                    'auction_id' => $auctionId,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['page_id'],
            ['client_id', 'user_id', 'page_type', 'auction_id', 'last_seen_at', 'updated_at'],
        );
    }

    public static function onlineUsers(): int
    {
        return PresenceHeartbeat::query()
            ->recent()
            ->select('client_id')
            ->distinct()
            ->count('client_id');
    }

    /**
     * @param array<int, int> $auctionIds
     * @return array<int, int>
     */
    public static function watcherCountsForAuctions(array $auctionIds): array
    {
        if (empty($auctionIds)) {
            return [];
        }

        /** @var array<int, int> */
        return PresenceHeartbeat::query()
            ->selectRaw('auction_id, COUNT(DISTINCT client_id) as watcher_count')
            ->whereIn('auction_id', $auctionIds)
            ->where('last_seen_at', '>=', self::cutoff())
            ->groupBy('auction_id')
            ->pluck('watcher_count', 'auction_id')
            ->all();
    }

    public static function watchersForAuction(int $auctionId): int
    {
        return PresenceHeartbeat::query()
            ->recent()
            ->where('auction_id', $auctionId)
            ->select('client_id')
            ->distinct()
            ->count('client_id');
    }

    /**
     * @return list<array{username: string, page_type: string, last_seen_at: int}>
     */
    public static function onlineUserDetails(): array
    {
        /** @var list<object{username: string, page_type: string, last_seen_at: string|null}> $rows */
        $rows = PresenceHeartbeat::query()
            ->recent()
            ->whereNotNull('user_id')
            ->join('users', 'users.id', '=', 'presence_heartbeats.user_id')
            ->selectRaw(
                'users.username, presence_heartbeats.page_type, MAX(presence_heartbeats.last_seen_at) as last_seen_at',
            )
            ->groupBy('users.username', 'presence_heartbeats.page_type')
            ->get()
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'username' => $row->username,
                'page_type' => $row->page_type,
                'last_seen_at' => $row->last_seen_at !== null ? (int) (strtotime($row->last_seen_at) * 1000) : 0,
            ];
        }

        return $result;
    }

    /** @return Builder<PresenceHeartbeat> */
    public static function watcherCountSubquery(string $auctionColumn = 'auctions.id'): Builder
    {
        return PresenceHeartbeat::query()
            ->selectRaw('COUNT(DISTINCT client_id)')
            ->whereColumn('auction_id', $auctionColumn)
            ->where('last_seen_at', '>=', self::cutoff());
    }
}
