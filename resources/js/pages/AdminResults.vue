<script setup>
import { computed, ref, inject, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const user = inject("user");
const currencySymbol = inject("currencySymbol");
const auctions = ref([]);
const summary = ref(null);
const loading = ref(true);
const expanded = ref({});
const view = ref("auctions");
const expandedUsers = ref({});
const expandedOfferAuctions = ref({});

if (!user.value?.is_admin) {
    router.push("/");
}

onMounted(async () => {
    try {
        const data = await api("/auctions/ended");
        auctions.value = data.auctions;
        summary.value = data.summary;
    } finally {
        loading.value = false;
    }
});

function toggle(id) {
    expanded.value[id] = !expanded.value[id];
}

function winners(auction) {
    return auction.bids.filter((b) => b.won_quantity > 0);
}

function formatDate(d) {
    if (!d) return "";
    return d.slice(0, 16).replace("T", " ");
}

function quoteUrl(auctionId, bidId) {
    return `/api/auctions/${auctionId}/quotes/${bidId}`;
}

function downloadAllQuotes(auction) {
    for (const bid of winners(auction)) {
        window.open(quoteUrl(auction.id, bid.id), "_blank");
    }
}

function downloadEveryQuote() {
    for (const auction of auctions.value) {
        for (const bid of winners(auction)) {
            window.open(quoteUrl(auction.id, bid.id), "_blank");
        }
    }
}

function hasAnyWinners() {
    return auctions.value.some((a) => winners(a).length > 0);
}

function formatMoney(amount) {
    return `${currencySymbol.value}${Number(amount ?? 0).toFixed(2)}`;
}

function toggleUser(username) {
    expandedUsers.value[username] = !expandedUsers.value[username];
}

function toggleOfferAuction(id) {
    expandedOfferAuctions.value[id] = !expandedOfferAuctions.value[id];
}

const auctionsWithOffers = computed(() => {
    return auctions.value.filter((a) => a.leftover_price_offers?.length > 0);
});

const userSummaries = computed(() => {
    /** @type {Map<string, {username: string, userId: number, items: Array, totalItems: number, totalOwed: number}>} */
    const map = new Map();

    for (const auction of auctions.value) {
        for (const bid of auction.bids ?? []) {
            if (bid.won_quantity <= 0) continue;
            const username = bid.user.username;
            if (!map.has(username)) {
                map.set(username, {
                    username,
                    userId: bid.user.id,
                    items: [],
                    totalItems: 0,
                    totalOwed: 0,
                });
            }
            const entry = map.get(username);
            const owed = bid.won_quantity * Number(bid.price ?? bid.amount);
            entry.items.push({
                auctionId: auction.id,
                auctionTitle: auction.title,
                auctionImages: auction.images,
                wonQuantity: bid.won_quantity,
                totalOwed: owed,
                bidId: bid.id,
                isLeftover: false,
            });
            entry.totalItems += bid.won_quantity;
            entry.totalOwed += owed;
        }

        for (const purchase of auction.leftover_purchases ?? []) {
            const username = purchase.user.username;
            if (!map.has(username)) {
                map.set(username, {
                    username,
                    userId: purchase.user.id,
                    items: [],
                    totalItems: 0,
                    totalOwed: 0,
                });
            }
            const entry = map.get(username);
            const owed = purchase.quantity * Number(purchase.price_per_item);
            entry.items.push({
                auctionId: auction.id,
                auctionTitle: auction.title,
                auctionImages: auction.images,
                wonQuantity: purchase.quantity,
                totalOwed: owed,
                bidId: null,
                isLeftover: true,
            });
            entry.totalItems += purchase.quantity;
            entry.totalOwed += owed;
        }
    }

    return [...map.values()].sort((a, b) => b.totalOwed - a.totalOwed);
});

const statsCards = computed(() => {
    if (!summary.value) {
        return [];
    }

    const afterTaxRatio = summary.value.total_value_after_tax
        ? (summary.value.revenue_after_tax / summary.value.total_value_after_tax) * 100
        : 0;
    const beforeTaxRatio = summary.value.total_value_before_tax
        ? (summary.value.revenue_before_tax / summary.value.total_value_before_tax) * 100
        : 0;

    return [
        {
            label: "Revenue after tax",
            value: `${formatMoney(summary.value.revenue_after_tax)} of ${formatMoney(summary.value.total_value_after_tax)} earned`,
            accent: "text-green-700 dark:text-green-400",
            detail: `${afterTaxRatio.toFixed(1)}% of all auction value`,
            progress: afterTaxRatio,
        },
        {
            label: "Revenue before tax",
            value: `${formatMoney(summary.value.revenue_before_tax)} of ${formatMoney(summary.value.total_value_before_tax)} earned`,
            accent: "text-blue-700 dark:text-blue-400",
            detail: `${beforeTaxRatio.toFixed(1)}% of all auction value`,
            progress: beforeTaxRatio,
        },
    ];
});
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-bold">Ended Auctions — Results</h1>
            <button
                v-if="!loading && hasAnyWinners() && view === 'auctions'"
                @click="downloadEveryQuote"
                class="text-sm text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
            >
                <svg
                    class="w-4 h-4"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                </svg>
                Download all quotes
            </button>
        </div>
        <div class="flex gap-1 mb-4 border-b dark:border-gray-700">
            <button
                @click="view = 'auctions'"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="
                    view === 'auctions'
                        ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                "
            >
                By Auction
            </button>
            <button
                @click="view = 'users'"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="
                    view === 'users'
                        ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                "
            >
                By User
            </button>
            <button
                @click="view = 'offers'"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="
                    view === 'offers'
                        ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                "
            >
                Price Offers
            </button>
        </div>
        <div
            v-if="!loading && summary"
            class="mb-6 bg-white dark:bg-gray-800 rounded shadow p-4 space-y-4"
        >
            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="card in statsCards"
                    :key="card.label"
                    class="rounded border border-gray-200 dark:border-gray-700 px-4 py-3"
                >
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ card.label }}
                    </p>
                    <p class="mt-1 text-xl font-bold leading-snug" :class="card.accent">
                        {{ card.value }}
                    </p>
                    <div class="mt-3 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div
                            class="h-full rounded-full bg-current transition-[width]"
                            :class="card.accent"
                            :style="{ width: `${Math.min(card.progress, 100)}%` }"
                        ></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ card.detail }}
                    </p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Across {{ summary.ended_auctions }} ended auction{{
                    summary.ended_auctions !== 1 ? "s" : ""
                }}, {{ summary.auctions_with_sales }} with winner{{
                    summary.auctions_with_sales !== 1 ? "s" : ""
                }}, {{ summary.sold_items }} item{{ summary.sold_items !== 1 ? "s" : "" }} sold.
                Totals are measured against all auctions and all items.
            </p>
        </div>
        <template v-if="view === 'auctions'">
            <p v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</p>
            <p v-else-if="auctions.length === 0" class="text-gray-500 dark:text-gray-400">
                No ended auctions yet, but the totals above show the current earning potential.
            </p>
            <div v-else class="space-y-3">
                <div
                    v-for="auction in auctions"
                    :key="auction.id"
                    class="bg-white dark:bg-gray-800 rounded shadow overflow-hidden"
                >
                    <button
                        @click="toggle(auction.id)"
                        class="w-full text-left px-5 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <img
                                v-if="auction.images.length"
                                :src="auction.images[0].url"
                                class="w-10 h-10 rounded object-cover shrink-0"
                            />
                            <div class="min-w-0">
                                <h2 class="font-semibold truncate">
                                    {{ auction.title }}
                                </h2>
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    Ended {{ formatDate(auction.ends_at) }} ·
                                    {{ auction.quantity }} item{{
                                        auction.quantity !== 1 ? "s" : ""
                                    }}
                                    · {{ auction.bid_count }} bid{{
                                        auction.bid_count !== 1 ? "s" : ""
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0 ml-4">
                            <span
                                v-if="winners(auction).length > 0"
                                class="text-sm font-medium text-green-700 dark:text-green-400"
                            >
                                {{ winners(auction).reduce((s, b) => s + b.won_quantity, 0) }}
                                sold · {{ currencySymbol
                                }}{{
                                    winners(auction)
                                        .reduce(
                                            (s, b) =>
                                                s + b.won_quantity * Number(b.price ?? b.amount),
                                            0,
                                        )
                                        .toFixed(2)
                                }}
                            </span>
                            <span v-else class="text-sm text-gray-400 dark:text-gray-500"
                                >No bids</span
                            >
                            <svg
                                class="w-4 h-4 text-gray-400 dark:text-gray-500 transition-transform"
                                :class="expanded[auction.id] ? 'rotate-180' : ''"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"
                                />
                            </svg>
                        </div>
                    </button>

                    <div
                        v-if="expanded[auction.id]"
                        class="border-t dark:border-gray-700 px-5 py-4"
                    >
                        <div
                            v-if="winners(auction).length === 0"
                            class="text-gray-400 dark:text-gray-500 text-sm"
                        >
                            No winners — auction ended without bids.
                        </div>
                        <div v-else>
                            <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">
                                Winners
                            </h3>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="text-left text-gray-500 dark:text-gray-400 border-b dark:border-gray-700"
                                    >
                                        <th class="pb-1 font-medium">User</th>
                                        <th class="pb-1 font-medium">Bid</th>
                                        <th class="pb-1 font-medium">Won</th>
                                        <th class="pb-1 font-medium text-right">Total Owed</th>
                                        <th class="pb-1 font-medium w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="bid in winners(auction)"
                                        :key="bid.id"
                                        class="border-b dark:border-gray-700 last:border-0"
                                    >
                                        <td class="py-2 font-medium">
                                            {{ bid.user.username }}
                                        </td>
                                        <td class="py-2">
                                            {{ currencySymbol }}{{ Number(bid.amount).toFixed(2) }}
                                        </td>
                                        <td class="py-2">
                                            {{ bid.won_quantity }} item{{
                                                bid.won_quantity !== 1 ? "s" : ""
                                            }}
                                        </td>
                                        <td
                                            class="py-2 text-right font-bold text-green-700 dark:text-green-400"
                                        >
                                            {{ currencySymbol
                                            }}{{
                                                (
                                                    bid.won_quantity *
                                                    Number(bid.price ?? bid.amount)
                                                ).toFixed(2)
                                            }}
                                        </td>
                                        <td class="py-2 text-right">
                                            <a
                                                :href="quoteUrl(auction.id, bid.id)"
                                                target="_blank"
                                                class="text-gray-400 dark:text-gray-500 hover:text-blue-600 dark:hover:text-blue-400"
                                                title="Download quote PDF"
                                            >
                                                <svg
                                                    class="w-4 h-4 inline"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                    />
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="text-gray-500 dark:text-gray-400">
                                        <td class="pt-2" colspan="2">Total</td>
                                        <td class="pt-2">
                                            {{
                                                winners(auction).reduce(
                                                    (s, b) => s + b.won_quantity,
                                                    0,
                                                )
                                            }}
                                            items
                                        </td>
                                        <td class="pt-2 text-right font-bold">
                                            {{ currencySymbol
                                            }}{{
                                                winners(auction)
                                                    .reduce(
                                                        (s, b) =>
                                                            s +
                                                            b.won_quantity *
                                                                Number(b.price ?? b.amount),
                                                        0,
                                                    )
                                                    .toFixed(2)
                                            }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div
                            v-if="auction.bids.filter((b) => b.won_quantity === 0).length > 0"
                            class="mt-4"
                        >
                            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 mb-1">
                                Unsuccessful bids
                            </h3>
                            <div class="text-xs text-gray-400 dark:text-gray-500 space-y-0.5">
                                <div
                                    v-for="bid in auction.bids.filter((b) => b.won_quantity === 0)"
                                    :key="bid.id"
                                >
                                    {{ bid.user.username }} — {{ currencySymbol
                                    }}{{ Number(bid.amount).toFixed(2) }}
                                    <span v-if="bid.quantity > 1">for {{ bid.quantity }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="auction.leftover_purchases?.length > 0" class="mt-4">
                            <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">
                                Leftover purchases
                            </h3>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="text-left text-gray-500 dark:text-gray-400 border-b dark:border-gray-700"
                                    >
                                        <th class="pb-1 font-medium">User</th>
                                        <th class="pb-1 font-medium">Qty</th>
                                        <th class="pb-1 font-medium">Price / item</th>
                                        <th class="pb-1 font-medium text-right">Total Owed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="purchase in auction.leftover_purchases"
                                        :key="purchase.id"
                                        class="border-b dark:border-gray-700 last:border-0"
                                    >
                                        <td class="py-2 font-medium">
                                            {{ purchase.user.username }}
                                        </td>
                                        <td class="py-2">{{ purchase.quantity }}</td>
                                        <td class="py-2">
                                            {{ currencySymbol
                                            }}{{ Number(purchase.price_per_item).toFixed(2) }}
                                        </td>
                                        <td
                                            class="py-2 text-right font-bold text-green-700 dark:text-green-400"
                                        >
                                            {{ currencySymbol
                                            }}{{
                                                (
                                                    purchase.quantity *
                                                    Number(purchase.price_per_item)
                                                ).toFixed(2)
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="text-gray-500 dark:text-gray-400">
                                        <td class="pt-2" colspan="2">Total</td>
                                        <td class="pt-2">
                                            {{
                                                auction.leftover_purchases.reduce(
                                                    (s, p) => s + p.quantity,
                                                    0,
                                                )
                                            }}
                                            items
                                        </td>
                                        <td class="pt-2 text-right font-bold">
                                            {{ currencySymbol
                                            }}{{
                                                auction.leftover_purchases
                                                    .reduce(
                                                        (s, p) =>
                                                            s +
                                                            p.quantity * Number(p.price_per_item),
                                                        0,
                                                    )
                                                    .toFixed(2)
                                            }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-if="auction.leftover_price_offers?.length > 0" class="mt-4">
                            <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">
                                Price offers
                            </h3>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="text-left text-gray-500 dark:text-gray-400 border-b dark:border-gray-700"
                                    >
                                        <th class="pb-1 font-medium">User</th>
                                        <th class="pb-1 font-medium">Qty</th>
                                        <th class="pb-1 font-medium">Offered / item</th>
                                        <th class="pb-1 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="offer in auction.leftover_price_offers"
                                        :key="offer.id"
                                        class="border-b dark:border-gray-700 last:border-0"
                                    >
                                        <td class="py-2 font-medium">{{ offer.user.username }}</td>
                                        <td class="py-2">{{ offer.quantity }}</td>
                                        <td class="py-2">
                                            {{ currencySymbol
                                            }}{{ Number(offer.offered_price_per_item).toFixed(2) }}
                                        </td>
                                        <td class="py-2">
                                            <span
                                                class="inline-block px-2 py-0.5 rounded text-xs font-medium"
                                                :class="{
                                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300':
                                                        offer.status === 'pending',
                                                    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300':
                                                        offer.status === 'accepted',
                                                    'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300':
                                                        offer.status === 'rejected',
                                                }"
                                            >
                                                {{ offer.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <router-link
                                :to="`/auctions/${auction.id}`"
                                class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                >View auction</router-link
                            >
                            <button
                                v-if="winners(auction).length > 0"
                                @click.stop="downloadAllQuotes(auction)"
                                class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
                            >
                                <svg
                                    class="w-3.5 h-3.5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                Download all quotes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template v-else>
            <p v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</p>
            <p v-else-if="userSummaries.length === 0" class="text-gray-500 dark:text-gray-400">
                No winners yet.
            </p>
            <div v-else class="space-y-3">
                <div
                    v-for="u in userSummaries"
                    :key="u.username"
                    class="bg-white dark:bg-gray-800 rounded shadow overflow-hidden"
                >
                    <button
                        @click="toggleUser(u.username)"
                        class="w-full text-left px-5 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <div class="font-semibold">{{ u.username }}</div>
                        <div class="flex items-center gap-3 shrink-0 ml-4">
                            <span class="text-sm font-medium text-green-700 dark:text-green-400">
                                {{ u.totalItems }} item{{ u.totalItems !== 1 ? "s" : "" }} ·
                                {{ formatMoney(u.totalOwed) }}
                            </span>
                            <svg
                                class="w-4 h-4 text-gray-400 dark:text-gray-500 transition-transform"
                                :class="expandedUsers[u.username] ? 'rotate-180' : ''"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"
                                />
                            </svg>
                        </div>
                    </button>
                    <div
                        v-if="expandedUsers[u.username]"
                        class="border-t dark:border-gray-700 px-5 py-4"
                    >
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="text-left text-gray-500 dark:text-gray-400 border-b dark:border-gray-700"
                                >
                                    <th class="pb-1 font-medium">Auction</th>
                                    <th class="pb-1 font-medium">Type</th>
                                    <th class="pb-1 font-medium">Qty</th>
                                    <th class="pb-1 font-medium text-right">Total Owed</th>
                                    <th class="pb-1 font-medium w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, idx) in u.items"
                                    :key="idx"
                                    class="border-b dark:border-gray-700 last:border-0"
                                >
                                    <td class="py-2">
                                        <div class="flex items-center gap-2">
                                            <img
                                                v-if="item.auctionImages.length"
                                                :src="item.auctionImages[0].url"
                                                class="w-7 h-7 rounded object-cover shrink-0"
                                            />
                                            <router-link
                                                :to="`/auctions/${item.auctionId}`"
                                                class="hover:underline text-blue-600 dark:text-blue-400"
                                                >{{ item.auctionTitle }}</router-link
                                            >
                                        </div>
                                    </td>
                                    <td class="py-2 text-gray-500 dark:text-gray-400">
                                        {{ item.isLeftover ? "Leftover buy" : "Bid win" }}
                                    </td>
                                    <td class="py-2">{{ item.wonQuantity }}</td>
                                    <td
                                        class="py-2 text-right font-bold text-green-700 dark:text-green-400"
                                    >
                                        {{ formatMoney(item.totalOwed) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        <a
                                            v-if="!item.isLeftover && item.bidId"
                                            :href="quoteUrl(item.auctionId, item.bidId)"
                                            target="_blank"
                                            class="text-gray-400 dark:text-gray-500 hover:text-blue-600 dark:hover:text-blue-400"
                                            title="Download quote PDF"
                                        >
                                            <svg
                                                class="w-4 h-4 inline"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <td class="pt-2" colspan="2">Total</td>
                                    <td class="pt-2">{{ u.totalItems }} items</td>
                                    <td class="pt-2 text-right font-bold">
                                        {{ formatMoney(u.totalOwed) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <template v-if="view === 'offers'">
            <p v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</p>
            <p v-else-if="auctionsWithOffers.length === 0" class="text-gray-500 dark:text-gray-400">
                No price offers submitted yet.
            </p>
            <div v-else class="space-y-3">
                <div
                    v-for="auction in auctionsWithOffers"
                    :key="auction.id"
                    class="bg-white dark:bg-gray-800 rounded shadow overflow-hidden"
                >
                    <button
                        @click="toggleOfferAuction(auction.id)"
                        class="w-full text-left px-5 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <img
                                v-if="auction.images.length"
                                :src="auction.images[0].url"
                                class="w-10 h-10 rounded object-cover shrink-0"
                            />
                            <div class="min-w-0">
                                <h2 class="font-semibold truncate">{{ auction.title }}</h2>
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ auction.leftover_price_offers.length }} offer{{
                                        auction.leftover_price_offers.length !== 1 ? "s" : ""
                                    }}
                                    ·
                                    {{
                                        auction.leftover_price_offers.filter(
                                            (o) => o.status === "pending",
                                        ).length
                                    }}
                                    pending
                                </p>
                            </div>
                        </div>
                        <svg
                            class="w-4 h-4 text-gray-400 dark:text-gray-500 transition-transform shrink-0"
                            :class="expandedOfferAuctions[auction.id] ? 'rotate-180' : ''"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <div
                        v-if="expandedOfferAuctions[auction.id]"
                        class="border-t dark:border-gray-700 px-5 py-4"
                    >
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="text-left text-gray-500 dark:text-gray-400 border-b dark:border-gray-700"
                                >
                                    <th class="pb-1 font-medium">User</th>
                                    <th class="pb-1 font-medium">Qty</th>
                                    <th class="pb-1 font-medium">Offered / item</th>
                                    <th class="pb-1 font-medium">Total offered</th>
                                    <th class="pb-1 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="offer in auction.leftover_price_offers"
                                    :key="offer.id"
                                    class="border-b dark:border-gray-700 last:border-0"
                                >
                                    <td class="py-2 font-medium">{{ offer.user.username }}</td>
                                    <td class="py-2">{{ offer.quantity }}</td>
                                    <td class="py-2">
                                        {{ currencySymbol
                                        }}{{ Number(offer.offered_price_per_item).toFixed(2) }}
                                    </td>
                                    <td class="py-2 font-bold text-green-700 dark:text-green-400">
                                        {{ currencySymbol
                                        }}{{
                                            (
                                                offer.quantity *
                                                Number(offer.offered_price_per_item)
                                            ).toFixed(2)
                                        }}
                                    </td>
                                    <td class="py-2">
                                        <span
                                            class="inline-block px-2 py-0.5 rounded text-xs font-medium"
                                            :class="{
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300':
                                                    offer.status === 'pending',
                                                'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300':
                                                    offer.status === 'accepted',
                                                'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300':
                                                    offer.status === 'rejected',
                                            }"
                                        >
                                            {{ offer.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-3">
                            <router-link
                                :to="`/auctions/${auction.id}`"
                                class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                >View auction</router-link
                            >
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
