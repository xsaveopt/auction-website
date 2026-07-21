<script setup lang="ts">
import { useAdminResults } from "../composables/useAdminResults";

const props = withDefaults(defineProps<{ active?: boolean }>(), { active: false });

const {
    user,
    currencySymbol,
    allRounds,
    summary,
    loading,
    expanded,
    view,
    expandedUsers,
    selectedRoundId,
    auctions,
    toggle,
    winners,
    formatDate,
    quoteUrl,
    userQuoteUrl,
    leftoverQuoteUrl,
    priceOfferQuoteUrl,
    downloadAllQuotes,
    downloadEveryQuote,
    downloadAllUserQuotes,
    hasAnyWinners,
    formatMoney,
    toggleUser,
    userSummaries,
    auctionsWithSales,
    statsCards,
} = useAdminResults(props);
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
            <button
                v-if="!loading && userSummaries.length > 0 && view === 'users'"
                @click="downloadAllUserQuotes"
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
        <!-- Round filter -->
        <div v-if="allRounds.length > 0" class="flex items-center gap-2 mb-4">
            <label class="text-sm text-gray-500 dark:text-gray-400 shrink-0">Round:</label>
            <select
                v-model="selectedRoundId"
                class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200"
            >
                <option :value="null">All rounds</option>
                <option v-for="r in allRounds" :key="r.id" :value="r.id">
                    {{ r.name }}
                </option>
            </select>
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
            <p v-else-if="auctionsWithSales.length === 0" class="text-gray-500 dark:text-gray-400">
                No auctions with sales yet.
            </p>
            <div v-else class="space-y-3">
                <div
                    v-for="auction in auctionsWithSales"
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
                            <span class="text-sm font-medium text-green-700 dark:text-green-400">
                                {{
                                    winners(auction).reduce(
                                        (s, b) => s + (b.won_quantity ?? 0),
                                        0,
                                    ) +
                                    (auction.leftover_purchases ?? []).reduce(
                                        (s, p) => s + p.quantity,
                                        0,
                                    )
                                }}
                                sold · {{ currencySymbol
                                }}{{
                                    (
                                        winners(auction).reduce(
                                            (s, b) =>
                                                s +
                                                (b.won_quantity ?? 0) * Number(b.price ?? b.amount),
                                            0,
                                        ) +
                                        (auction.leftover_purchases ?? []).reduce(
                                            (s, p) => s + p.quantity * Number(p.price_per_item),
                                            0,
                                        )
                                    ).toFixed(2)
                                }}
                            </span>
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
                        <div v-if="winners(auction).length > 0">
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
                                            {{ bid.user?.username }}
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
                                                    (bid.won_quantity ?? 0) *
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
                                                    (s, b) => s + (b.won_quantity ?? 0),
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
                                                            (b.won_quantity ?? 0) *
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
                            v-if="
                                (auction.bids ?? []).filter((b) => (b.won_quantity ?? 0) === 0)
                                    .length > 0
                            "
                            class="mt-4"
                        >
                            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 mb-1">
                                Unsuccessful bids
                            </h3>
                            <div class="text-xs text-gray-400 dark:text-gray-500 space-y-0.5">
                                <div
                                    v-for="bid in (auction.bids ?? []).filter(
                                        (b) => (b.won_quantity ?? 0) === 0,
                                    )"
                                    :key="bid.id"
                                >
                                    {{ bid.user?.username }} — {{ currencySymbol
                                    }}{{ Number(bid.amount).toFixed(2) }}
                                    <span v-if="bid.quantity > 1">for {{ bid.quantity }}</span>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="
                                (auction.leftover_purchases?.length ?? 0) > 0 ||
                                (auction.leftover_price_offers ?? []).some(
                                    (o) => o.status === 'accepted',
                                )
                            "
                            class="mt-4"
                        >
                            <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">
                                Leftover purchases
                            </h3>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="text-left text-gray-500 dark:text-gray-400 border-b dark:border-gray-700"
                                    >
                                        <th class="pb-1 font-medium">User</th>
                                        <th class="pb-1 font-medium">Type</th>
                                        <th class="pb-1 font-medium">Qty</th>
                                        <th class="pb-1 font-medium">Price / item</th>
                                        <th class="pb-1 font-medium text-right">Total Owed</th>
                                        <th class="pb-1 font-medium w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="purchase in auction.leftover_purchases"
                                        :key="`purchase-${purchase.id}`"
                                        class="border-b dark:border-gray-700 last:border-0"
                                    >
                                        <td class="py-2 font-medium">
                                            {{ purchase.user?.username }}
                                        </td>
                                        <td class="py-2 text-gray-500 dark:text-gray-400">
                                            Leftover buy
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
                                        <td class="py-2 text-right">
                                            <a
                                                :href="leftoverQuoteUrl(auction.id, purchase.id)"
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
                                    <tr
                                        v-for="offer in (
                                            auction.leftover_price_offers ?? []
                                        ).filter((o) => o.status === 'accepted')"
                                        :key="`offer-${offer.id}`"
                                        class="border-b dark:border-gray-700 last:border-0"
                                    >
                                        <td class="py-2 font-medium">
                                            {{ offer.user?.username }}
                                        </td>
                                        <td class="py-2 text-gray-500 dark:text-gray-400">
                                            Price offer
                                        </td>
                                        <td class="py-2">{{ offer.quantity }}</td>
                                        <td class="py-2">
                                            {{ currencySymbol
                                            }}{{ Number(offer.offered_price_per_item).toFixed(2) }}
                                        </td>
                                        <td
                                            class="py-2 text-right font-bold text-green-700 dark:text-green-400"
                                        >
                                            {{ currencySymbol
                                            }}{{
                                                (
                                                    offer.quantity *
                                                    Number(offer.offered_price_per_item)
                                                ).toFixed(2)
                                            }}
                                        </td>
                                        <td class="py-2 text-right">
                                            <a
                                                :href="priceOfferQuoteUrl(auction.id, offer.id)"
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
                                        <td class="pt-2" colspan="3">Total</td>
                                        <td class="pt-2">
                                            {{
                                                (auction.leftover_purchases ?? []).reduce(
                                                    (s, p) => s + p.quantity,
                                                    0,
                                                ) +
                                                (auction.leftover_price_offers ?? [])
                                                    .filter((o) => o.status === "accepted")
                                                    .reduce((s, o) => s + o.quantity, 0)
                                            }}
                                            items
                                        </td>
                                        <td class="pt-2 text-right font-bold">
                                            {{ currencySymbol
                                            }}{{
                                                (
                                                    (auction.leftover_purchases ?? []).reduce(
                                                        (s, p) =>
                                                            s +
                                                            p.quantity * Number(p.price_per_item),
                                                        0,
                                                    ) +
                                                    (auction.leftover_price_offers ?? [])
                                                        .filter((o) => o.status === "accepted")
                                                        .reduce(
                                                            (s, o) =>
                                                                s +
                                                                o.quantity *
                                                                    Number(
                                                                        o.offered_price_per_item,
                                                                    ),
                                                            0,
                                                        )
                                                ).toFixed(2)
                                            }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
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
                            <a
                                :href="userQuoteUrl(u.userId)"
                                target="_blank"
                                @click.stop
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
                                                v-if="item.auctionImages?.length"
                                                :src="item.auctionImages?.[0].url"
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
                                        {{
                                            !item.isLeftover
                                                ? "Bid win"
                                                : item.fromPriceOffer
                                                  ? "Price offer"
                                                  : "Leftover buy"
                                        }}
                                    </td>
                                    <td class="py-2">{{ item.wonQuantity }}</td>
                                    <td
                                        class="py-2 text-right font-bold text-green-700 dark:text-green-400"
                                    >
                                        {{ formatMoney(item.totalOwed) }}
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
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
