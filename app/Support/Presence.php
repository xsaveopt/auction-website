<?php

namespace App\Support;

use App\Models\PresenceHeartbeat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        string $path,
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
                    'path' => $path,
                    'auction_id' => $auctionId,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['page_id'],
            ['client_id', 'user_id', 'page_type', 'path', 'auction_id', 'last_seen_at', 'updated_at'],
        );

        if ($auctionId !== null) {
            DB::table('auction_total_views')->insertOrIgnore([
                'auction_id' => $auctionId,
                'client_id' => $clientId,
                'created_at' => $now,
            ]);
        }
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
     * @return list<array{username: string, path: string, last_seen_at: int}>
     */
    public static function onlineUserDetails(): array
    {
        /** @var list<object{username: string, path: string|null, page_type: string, last_seen_at: string|null}> $rows */
        $rows = PresenceHeartbeat::query()
            ->recent()
            ->whereNotNull('user_id')
            ->join('users', 'users.id', '=', 'presence_heartbeats.user_id')
            ->where('users.is_admin', false)
            ->selectRaw(
                'users.username, COALESCE(presence_heartbeats.path, presence_heartbeats.page_type) as path, MAX(presence_heartbeats.last_seen_at) as last_seen_at',
            )
            ->groupBy('users.username', 'path')
            ->get()
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'username' => $row->username,
                'path' => $row->path ?? $row->page_type,
                'last_seen_at' => $row->last_seen_at !== null ? (int) (strtotime($row->last_seen_at) * 1000) : 0,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{auction_id: int, title: string, view_count: int}>
     */
    public static function totalViewsByAuction(): array
    {
        /** @var list<object{auction_id: int, title: string, view_count: int}> $rows */
        $rows = DB::table('auction_total_views')
            ->join('auctions', 'auctions.id', '=', 'auction_total_views.auction_id')
            ->selectRaw('auction_total_views.auction_id, auctions.title, COUNT(*) as view_count')
            ->groupBy('auction_total_views.auction_id', 'auctions.title')
            ->get()
            ->all();

        return array_map(fn($row) => [
            'auction_id' => $row->auction_id,
            'title' => $row->title,
            'view_count' => (int) $row->view_count,
        ], $rows);
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
