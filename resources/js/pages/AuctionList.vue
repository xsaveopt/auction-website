<script setup>
import { ref, computed, onMounted, inject, watch } from "vue";
import { api } from "../api.js";
import {
    getItemLabel,
    getLeftoverDiscountPercent,
    hasAvailableLeftovers,
} from "../auctionPresentation.js";

const auctions = ref([]);
const categories = ref([]);
const loading = ref(true);
const heartbeatData = inject("heartbeatData");
const currencySymbol = inject("currencySymbol");
const user = inject("user");
const now = inject("now");

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
    const [auctionData, categoryData] = await Promise.all([
        api("/auctions"),
        api("/categories"),
        loadAnnouncement(),
    ]);

    auctions.value = auctionData.auctions;
    if (categoryData) categories.value = categoryData.categories;
    loading.value = false;
}

watch(heartbeatData, (data) => {
    if (loading.value || !data?.auction_updates) return;

    // Detect new or removed auctions — re-fetch full data
    const currentIds = new Set(auctions.value.map((a) => a.id));
    const serverIds = new Set(data.auction_ids ?? []);
    if (currentIds.size !== serverIds.size || [...serverIds].some((id) => !currentIds.has(id))) {
        load();
        return;
    }

    // Merge lightweight updates into existing auction objects
    const updateMap = new Map(data.auction_updates.map((u) => [u.id, u]));
    for (const auction of auctions.value) {
        const update = updateMap.get(auction.id);
        if (update) {
            Object.assign(auction, update);
        }
    }
});

function watchingText(count) {
    return `${count} currently watching`;
}

function isLeftoverSale(auction) {
    return !auction.is_active && hasAvailableLeftovers(auction);
}

function priceLabel(auction) {
    if (isLeftoverSale(auction)) return "Buy now";
    if (auction.is_active) return "Current price";
    if (auction.bid_count > 0) return "Final price";

    return "Starting price";
}

function priceValue(auction) {
    return isLeftoverSale(auction) ? auction.leftover_price : auction.current_price;
}

function leftoverDiscountText(auction) {
    const discountPercent = getLeftoverDiscountPercent(auction);

    return discountPercent > 0 ? `${discountPercent}% off` : null;
}

function statusText(auction) {
    if (auction.is_active) return "Live";
    if (isLeftoverSale(auction)) return "Leftover sale";
    if (auction.leftover_enabled && auction.leftover_quantity === 0) return "Sold out";

    return "Ended";
}

onMounted(async () => {
    await load();
});

const showSoldOut = ref(false);

function shouldHideEnded(auction) {
    return !auction.is_active && !(auction.leftover_enabled && auction.leftover_quantity > 0);
}

const hiddenEndedCount = computed(() => auctions.value.filter((a) => shouldHideEnded(a)).length);

const groupedAuctions = computed(() => {
    const groups = [];
    const categoryMap = new Map();

    for (const cat of categories.value) {
        const group = {
            id: cat.id,
            name: cat.name,
            slug: cat.slug,
            auctions: [],
        };
        categoryMap.set(cat.id, group);
        groups.push(group);
    }

    const uncategorized = [];

    for (const auction of auctions.value) {
        if (!showSoldOut.value && shouldHideEnded(auction)) continue;
        if (auction.category_id && categoryMap.has(auction.category_id)) {
            categoryMap.get(auction.category_id).auctions.push(auction);
        } else {
            uncategorized.push(auction);
        }
    }

    // Filter out empty categories
    const result = groups.filter((g) => g.auctions.length > 0);

    if (uncategorized.length > 0) {
        result.push({
            id: null,
            name: "Other",
            slug: "other",
            auctions: uncategorized,
        });
    }

    return result;
});

function timeLeft(endsAt) {
    const diff = new Date(endsAt) - now.value;
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

            <!-- Toolbar: admin announcement link (left) + ended toggle (right) -->
            <div
                v-if="
                    (!announcement && !editingAnnouncement && user?.is_admin) ||
                    hiddenEndedCount > 0 ||
                    showSoldOut
                "
                class="mb-4 flex items-center justify-between"
            >
                <button
                    v-if="!announcement && !editingAnnouncement && user?.is_admin"
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
                <span v-else />

                <button
                    v-if="hiddenEndedCount > 0 || showSoldOut"
                    @click="showSoldOut = !showSoldOut"
                    class="ml-auto text-sm font-medium border border-gray-300 dark:border-gray-600 rounded px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    <span v-if="!showSoldOut">Show {{ hiddenEndedCount }} ended</span>
                    <span v-else>Hide ended</span>
                </button>
            </div>

            <!-- Auction groups by category — 3-column pane layout -->
            <div v-if="groupedAuctions.length === 0" class="text-gray-500 dark:text-gray-400">
                No auctions yet.
            </div>
            <div v-else class="grid gap-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
                <section v-for="group in groupedAuctions" :key="group.slug" class="min-w-0">
                    <h2
                        class="text-lg font-bold mb-3 pb-2 border-b border-gray-200 dark:border-gray-700"
                    >
                        {{ group.name }}
                    </h2>
                    <div class="space-y-4">
                        <router-link
                            v-for="auction in group.auctions"
                            :key="auction.id"
                            :to="`/auctions/${auction.id}`"
                            class="block bg-white dark:bg-gray-800 rounded shadow overflow-hidden hover:shadow-md dark:hover:shadow-gray-900 transition-shadow"
                        >
                            <img
                                v-if="auction.images.length"
                                :src="auction.images[0].url"
                                :alt="auction.title"
                                class="w-full h-36 object-cover"
                            />
                            <div class="p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-semibold truncate">
                                        {{ auction.title }}
                                    </h3>
                                    <span
                                        class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                        :class="
                                            auction.is_active
                                                ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                                : isLeftoverSale(auction)
                                                  ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                                  : auction.leftover_enabled &&
                                                      auction.leftover_quantity === 0
                                                    ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                                                    : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                        "
                                    >
                                        {{ statusText(auction) }}
                                    </span>
                                </div>
                                <p
                                    class="text-gray-600 dark:text-gray-400 text-sm mt-1 line-clamp-2"
                                >
                                    {{ auction.description }}
                                </p>
                                <div
                                    class="mt-2 text-sm font-medium text-amber-600 dark:text-amber-400"
                                >
                                    {{ watchingText(auction.watcher_count) }}
                                </div>
                                <div
                                    class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                                        >
                                            {{ priceLabel(auction) }}
                                        </p>
                                        <p class="mt-1 font-bold text-gray-800 dark:text-gray-100">
                                            {{ currencySymbol
                                            }}{{ Number(priceValue(auction)).toFixed(2) }}
                                            <span
                                                class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                            >
                                                / item
                                            </span>
                                        </p>
                                        <p
                                            v-if="isLeftoverSale(auction)"
                                            class="mt-1 text-xs text-green-700 dark:text-green-300"
                                        >
                                            <span class="font-semibold">
                                                {{ getItemLabel(auction.leftover_quantity) }} left
                                            </span>
                                            <span v-if="leftoverDiscountText(auction)">
                                                · {{ leftoverDiscountText(auction) }} off the
                                                original price
                                            </span>
                                        </p>
                                        <p
                                            v-else-if="auction.bid_count === 0"
                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            Starts at {{ currencySymbol
                                            }}{{ Number(auction.starting_price).toFixed(2) }} per
                                            item
                                        </p>
                                    </div>
                                    <span
                                        v-if="auction.is_active || isLeftoverSale(auction)"
                                        class="text-sm"
                                    >
                                        <template v-if="auction.is_active">
                                            <span class="text-gray-600 dark:text-gray-300">{{
                                                timeLeft(auction.ends_at)
                                            }}</span>
                                        </template>
                                        <template v-else>
                                            <span
                                                class="font-medium text-green-600 dark:text-green-400"
                                                >Buy now available</span
                                            >
                                        </template>
                                    </span>
                                </div>
                                <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                    <span
                                        v-if="auction.quantity > 1"
                                        class="text-gray-500 dark:text-gray-400 font-medium"
                                        >{{ auction.quantity }} items ·
                                    </span>
                                    {{ auction.bid_count }}
                                    bid{{ auction.bid_count !== 1 ? "s" : "" }}
                                </div>
                            </div>
                        </router-link>
                    </div>
                </section>
            </div>
        </template>
    </div>
</template>
