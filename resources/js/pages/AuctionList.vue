<script setup>
import { ref, computed, onMounted, inject, watch } from "vue";
import { api } from "../api.js";

const auctions = ref([]);
const stats = ref(null);
const loading = ref(true);
const heartbeatData = inject("heartbeatData");
const currencySymbol = inject("currencySymbol");
const user = inject("user");

const announcement = ref(null);
const editingAnnouncement = ref(false);
const announcementDraft = ref("");
const announcementSaving = ref(false);

async function loadAnnouncement() {
    const data = await api("/announcement").catch(() => null);
    if (data) announcement.value = data.announcement;
}

async function saveAnnouncement() {
    if (!announcementDraft.value.trim()) return;
    announcementSaving.value = true;
    const data = await api("/announcement", {
        method: "POST",
        body: JSON.stringify({ message: announcementDraft.value.trim() }),
    }).catch(() => null);
    announcementSaving.value = false;
    if (data) {
        announcement.value = data.announcement;
        editingAnnouncement.value = false;
        announcementDraft.value = "";
    }
}

async function removeAnnouncement() {
    if (!announcement.value) return;
    await api(`/announcements/${announcement.value.id}`, { method: "DELETE" }).catch(() => null);
    announcement.value = null;
}

function startEditAnnouncement() {
    announcementDraft.value = announcement.value?.message || "";
    editingAnnouncement.value = true;
}

async function load() {
    const [auctionData, statsData] = await Promise.all([
        api("/auctions"),
        api("/stats").catch(() => null),
        loadAnnouncement(),
    ]);

    auctions.value = auctionData.auctions;
    if (statsData) stats.value = statsData.stats;
    loading.value = false;
}

watch(heartbeatData, (data) => {
    if (data?.auctions) {
        auctions.value = data.auctions;
    }
    if (data?.stats) {
        stats.value = data.stats;
    }
});

function watchingText(count) {
    return `${count} currently watching`;
}

onMounted(async () => {
    await load();
});

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
        <p v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</p>
        <template v-else>
            <!-- Announcement Board -->
            <div
                v-if="announcement && !editingAnnouncement"
                class="mb-6 bg-white dark:bg-gray-800 rounded shadow border-l-4 border-blue-500 dark:border-blue-400 p-4"
            >
                <div class="flex items-start gap-3">
                    <svg
                        class="w-5 h-5 text-blue-500 dark:text-blue-400 mt-0.5 shrink-0"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"
                        />
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">
                            {{ announcement.message }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Posted by {{ announcement.author }}
                        </p>
                    </div>
                    <div v-if="user?.is_admin" class="flex gap-1 shrink-0">
                        <button
                            @click="startEditAnnouncement"
                            class="text-gray-400 dark:text-gray-500 hover:text-blue-600 dark:hover:text-blue-400 p-1"
                            title="Edit"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                />
                            </svg>
                        </button>
                        <button
                            @click="removeAnnouncement"
                            class="text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 p-1"
                            title="Remove"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Announcement Editor (admin) -->
            <div
                v-if="editingAnnouncement && user?.is_admin"
                class="mb-6 bg-white dark:bg-gray-800 rounded shadow border-l-4 border-blue-500 dark:border-blue-400 p-4"
            >
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                    >Announcement</label
                >
                <textarea
                    v-model="announcementDraft"
                    rows="3"
                    maxlength="1000"
                    class="w-full rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Write an announcement for all users..."
                ></textarea>
                <div class="flex items-center justify-between mt-2">
                    <span class="text-xs text-gray-400 dark:text-gray-500"
                        >{{ announcementDraft.length }}/1000</span
                    >
                    <div class="flex gap-2">
                        <button
                            @click="editingAnnouncement = false"
                            class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                        >
                            Cancel
                        </button>
                        <button
                            @click="saveAnnouncement"
                            :disabled="!announcementDraft.trim() || announcementSaving"
                            class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ announcementSaving ? "Saving..." : "Publish" }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Admin: Add announcement button (when none exists) -->
            <div v-if="!announcement && !editingAnnouncement && user?.is_admin" class="mb-6">
                <button
                    @click="startEditAnnouncement"
                    class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 flex items-center gap-1"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    Add announcement
                </button>
            </div>

            <!-- Stats Dashboard -->
            <div v-if="stats" class="mb-8 space-y-4">
                <!-- Number cards -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div class="bg-white dark:bg-gray-800 rounded shadow p-4 text-center">
                        <div class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                            {{ stats.active_auctions }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Active Auctions
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded shadow p-4 text-center">
                        <div class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                            {{ stats.total_items }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Items Up for Grabs
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded shadow p-4 text-center">
                        <div class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                            {{ stats.total_bids }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Bids Placed</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded shadow p-4 text-center">
                        <div class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                            {{ stats.online_users }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Online Right Now
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded shadow p-4 text-center">
                        <div class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                            {{ stats.total_users }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Registered Users
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <!-- Bidding activity chart -->
                    <div class="bg-white dark:bg-gray-800 rounded shadow p-4">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
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
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                    >{{ day.count }}</span
                                >
                                <div
                                    class="w-full bg-blue-400 dark:bg-blue-500 rounded-t transition-all"
                                    :style="{
                                        height: (day.count / bidsMax) * 100 + '%',
                                        minHeight: day.count > 0 ? '4px' : '2px',
                                    }"
                                    :class="
                                        day.count === 0
                                            ? 'bg-gray-200 dark:bg-gray-700'
                                            : 'bg-blue-400 dark:bg-blue-500'
                                    "
                                ></div>
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{
                                    day.label
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hot auctions -->
                    <div class="bg-white dark:bg-gray-800 rounded shadow p-4">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            Most Competitive
                        </h3>
                        <div
                            v-if="stats.hot_auctions.length === 0"
                            class="text-xs text-gray-400 dark:text-gray-500"
                        >
                            No active auctions
                        </div>
                        <ul v-else class="space-y-2">
                            <li v-for="a in stats.hot_auctions" :key="a.id">
                                <router-link
                                    :to="`/auctions/${a.id}`"
                                    class="flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700 rounded px-1 -mx-1 transition-colors"
                                >
                                    <span class="text-sm truncate">{{ a.title }}</span>
                                    <span
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 ml-2 shrink-0"
                                        >{{ a.bid_count }} bids</span
                                    >
                                </router-link>
                            </li>
                        </ul>
                    </div>

                    <!-- Top bidders -->
                    <div class="bg-white dark:bg-gray-800 rounded shadow p-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                Top Bidders
                            </h3>
                            <div
                                v-if="stats.top_bidders.length === 0"
                                class="text-xs text-gray-400 dark:text-gray-500"
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
                                        class="text-xs font-bold w-4 text-gray-400 dark:text-gray-500"
                                        >{{ i + 1 }}</span
                                    >
                                    <span class="text-sm">{{ b.username }}</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto"
                                        >{{ b.auction_count }} auctions</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auction grid -->
            <h2 class="text-2xl font-bold mb-4">Auctions</h2>
            <p v-if="auctions.length === 0" class="text-gray-500 dark:text-gray-400">
                No auctions yet.
            </p>
            <div v-else class="grid gap-4 sm:grid-cols-2">
                <router-link
                    v-for="auction in auctions"
                    :key="auction.id"
                    :to="`/auctions/${auction.id}`"
                    class="block bg-white dark:bg-gray-800 rounded shadow overflow-hidden hover:shadow-md dark:hover:shadow-gray-900 transition-shadow"
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
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1 line-clamp-2">
                            {{ auction.description }}
                        </p>
                        <div class="mt-2 text-sm font-medium text-amber-600 dark:text-amber-400">
                            {{ watchingText(auction.watcher_count) }}
                        </div>
                        <div class="mt-3 flex items-center justify-between text-sm">
                            <span class="font-bold text-gray-800 dark:text-gray-100"
                                >{{ currencySymbol
                                }}{{ Number(auction.current_price).toFixed(2) }}</span
                            >
                            <span
                                :class="
                                    auction.is_active
                                        ? 'text-gray-600 dark:text-gray-300'
                                        : 'text-gray-400 dark:text-gray-500'
                                "
                            >
                                {{ auction.is_active ? timeLeft(auction.ends_at) : "Ended" }}
                            </span>
                        </div>
                        <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                            <span
                                v-if="auction.quantity > 1"
                                class="text-gray-500 dark:text-gray-400 font-medium"
                                >{{ auction.quantity }} items ·
                            </span>
                            {{ auction.bid_count }} bid{{ auction.bid_count !== 1 ? "s" : "" }}
                        </div>
                    </div>
                </router-link>
            </div>
        </template>
    </div>
</template>
