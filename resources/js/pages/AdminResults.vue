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
    return new Date(d).toLocaleString();
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
                v-if="!loading && hasAnyWinners()"
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
                                {{ auction.quantity }} item{{ auction.quantity !== 1 ? "s" : "" }} ·
                                {{ auction.bid_count }} bid{{ auction.bid_count !== 1 ? "s" : "" }}
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
                                        (s, b) => s + b.won_quantity * Number(b.price ?? b.amount),
                                        0,
                                    )
                                    .toFixed(2)
                            }}
                        </span>
                        <span v-else class="text-sm text-gray-400 dark:text-gray-500">No bids</span>
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

                <div v-if="expanded[auction.id]" class="border-t dark:border-gray-700 px-5 py-4">
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
                                                bid.won_quantity * Number(bid.price ?? bid.amount)
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
                                            winners(auction).reduce((s, b) => s + b.won_quantity, 0)
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
    </div>
</template>
