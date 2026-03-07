<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { api } from "../api.js";
import { HEARTBEAT_INTERVAL_MS } from "../presence.js";

const auctions = ref([]);
const stats = ref(null);
const loading = ref(true);

async function load() {
    const [auctionData, statsData] = await Promise.all([
        api("/auctions"),
        api("/stats").catch(() => null),
    ]);

    auctions.value = auctionData.auctions;
    if (statsData) stats.value = statsData.stats;
    loading.value = false;
}

function watchingText(count) {
    return `${count} currently watching`;
}

let refreshInterval;
onMounted(async () => {
    await load();
    refreshInterval = setInterval(load, HEARTBEAT_INTERVAL_MS);
});
onUnmounted(() => clearInterval(refreshInterval));

const bidsMax = computed(() => {
    if (!stats.value) return 1;
    return Math.max(1, ...stats.value.bids_per_day.map((d) => d.count));
});

function timeLeft(endsAt) {
    const diff = new Date(endsAt) - Date.now();
    if (diff <= 0) return "Ended";
    const hours = Math.floor(diff / 3600000);
    const minutes = Math.floor((diff % 3600000) / 60000);
    if (hours > 24) return `${Math.floor(hours / 24)}d ${hours % 24}h left`;
    return `${hours}h ${minutes}m left`;
}
</script>

<template>
    <div>
        <p v-if="loading" class="text-gray-500">Loading...</p>
        <template v-else>
            <!-- Stats Dashboard -->
            <div v-if="stats" class="mb-8 space-y-4">
                <!-- Number cards -->
                <div
                    class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3"
                >
                    <div class="bg-white rounded shadow p-4 text-center">
                        <div class="text-3xl font-bold text-blue-600">
                            {{ stats.active_auctions }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Active Auctions
                        </div>
                    </div>
                    <div class="bg-white rounded shadow p-4 text-center">
                        <div class="text-3xl font-bold text-purple-600">
                            {{ stats.total_items }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Items Up for Grabs
                        </div>
                    </div>
                    <div class="bg-white rounded shadow p-4 text-center">
                        <div class="text-3xl font-bold text-green-600">
                            {{ stats.total_bids }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Bids Placed
                        </div>
                    </div>
                    <div class="bg-white rounded shadow p-4 text-center">
                        <div class="text-3xl font-bold text-emerald-600">
                            {{ stats.online_users }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Online Right Now
                        </div>
                    </div>
                    <div class="bg-white rounded shadow p-4 text-center">
                        <div class="text-3xl font-bold text-amber-600">
                            {{ stats.total_users }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Registered Users
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded shadow p-5 text-center">
                    <div
                        class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                    >
                        Money Currently in Play
                    </div>
                    <div class="mt-2 text-4xl font-bold text-green-700">
                        ${{ stats.current_bid_total.toFixed(2) }}
                    </div>
                    <div class="mt-1 text-sm text-gray-500">
                        Combined value of all active bids right now
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <!-- Bidding activity chart -->
                    <div class="bg-white rounded shadow p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">
                            Bidding Activity (7 days)
                        </h3>
                        <div class="flex items-end gap-1 h-24">
                            <div
                                v-for="day in stats.bids_per_day"
                                :key="day.date"
                                class="flex-1 flex flex-col items-center gap-1"
                            >
                                <span
                                    v-if="day.count > 0"
                                    class="text-xs text-gray-500"
                                    >{{ day.count }}</span
                                >
                                <div
                                    class="w-full bg-blue-400 rounded-t transition-all"
                                    :style="{
                                        height:
                                            (day.count / bidsMax) * 100 + '%',
                                        minHeight:
                                            day.count > 0 ? '4px' : '2px',
                                    }"
                                    :class="
                                        day.count === 0
                                            ? 'bg-gray-200'
                                            : 'bg-blue-400'
                                    "
                                ></div>
                                <span class="text-xs text-gray-400">{{
                                    day.label
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hot auctions -->
                    <div class="bg-white rounded shadow p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">
                            Most Competitive
                        </h3>
                        <div
                            v-if="stats.hot_auctions.length === 0"
                            class="text-xs text-gray-400"
                        >
                            No active auctions
                        </div>
                        <ul v-else class="space-y-2">
                            <li v-for="a in stats.hot_auctions" :key="a.id">
                                <router-link
                                    :to="`/auctions/${a.id}`"
                                    class="flex items-center justify-between hover:bg-gray-50 rounded px-1 -mx-1"
                                >
                                    <span class="text-sm truncate">{{
                                        a.title
                                    }}</span>
                                    <span
                                        class="text-xs font-medium text-blue-600 ml-2 shrink-0"
                                        >{{ a.bid_count }} bids</span
                                    >
                                </router-link>
                            </li>
                        </ul>
                    </div>

                    <!-- Top bidders + ending soon -->
                    <div class="bg-white rounded shadow p-4 space-y-4">
                        <div>
                            <h3
                                class="text-sm font-semibold text-gray-700 mb-2"
                            >
                                Top Bidders
                            </h3>
                            <div
                                v-if="stats.top_bidders.length === 0"
                                class="text-xs text-gray-400"
                            >
                                No bids yet
                            </div>
                            <div v-else class="space-y-1">
                                <div
                                    v-for="(b, i) in stats.top_bidders"
                                    :key="b.username"
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        class="text-xs font-bold w-4"
                                        :class="[
                                            i === 0
                                                ? 'text-amber-500'
                                                : i === 1
                                                  ? 'text-gray-400'
                                                  : i === 2
                                                    ? 'text-amber-700'
                                                    : 'text-gray-300',
                                        ]"
                                        >{{ i + 1 }}</span
                                    >
                                    <span class="text-sm">{{
                                        b.username
                                    }}</span>
                                    <span class="text-xs text-gray-400 ml-auto"
                                        >{{ b.auction_count }} auctions</span
                                    >
                                </div>
                            </div>
                        </div>
                        <div v-if="stats.ending_soon.length > 0">
                            <h3 class="text-sm font-semibold text-red-600 mb-2">
                                Ending Soon
                            </h3>
                            <ul class="space-y-1">
                                <li v-for="a in stats.ending_soon" :key="a.id">
                                    <router-link
                                        :to="`/auctions/${a.id}`"
                                        class="text-sm text-red-600 hover:underline"
                                    >
                                        {{ a.title }} —
                                        {{ timeLeft(a.ends_at) }}
                                    </router-link>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auction grid -->
            <h2 class="text-2xl font-bold mb-4">Auctions</h2>
            <p v-if="auctions.length === 0" class="text-gray-500">
                No auctions yet.
            </p>
            <div v-else class="grid gap-4 sm:grid-cols-2">
                <router-link
                    v-for="auction in auctions"
                    :key="auction.id"
                    :to="`/auctions/${auction.id}`"
                    class="block bg-white rounded shadow overflow-hidden hover:shadow-md transition-shadow"
                >
                    <img
                        v-if="auction.images.length"
                        :src="auction.images[0].url"
                        :alt="auction.title"
                        class="w-full h-40 object-cover"
                    />
                    <div class="p-4">
                        <h2 class="text-lg font-semibold">
                            {{ auction.title }}
                        </h2>
                        <p class="text-gray-600 text-sm mt-1 line-clamp-2">
                            {{ auction.description }}
                        </p>
                        <div class="mt-2 text-sm font-medium text-amber-600">
                            {{ watchingText(auction.watcher_count) }}
                        </div>
                        <div
                            class="mt-3 flex items-center justify-between text-sm"
                        >
                            <span class="font-bold text-green-700"
                                >${{
                                    Number(auction.current_price).toFixed(2)
                                }}</span
                            >
                            <span
                                :class="
                                    auction.is_active
                                        ? 'text-blue-600'
                                        : 'text-gray-400'
                                "
                            >
                                {{
                                    auction.is_active
                                        ? timeLeft(auction.ends_at)
                                        : "Ended"
                                }}
                            </span>
                        </div>
                        <div class="mt-1 text-xs text-gray-400">
                            <span
                                v-if="auction.quantity > 1"
                                class="text-purple-600 font-medium"
                                >{{ auction.quantity }} items ·
                            </span>
                            {{ auction.bid_count }} bid{{
                                auction.bid_count !== 1 ? "s" : ""
                            }}
                            · by {{ auction.seller.username }}
                        </div>
                    </div>
                </router-link>
            </div>
        </template>
    </div>
</template>
