<script setup lang="ts">
import { getItemLabel, watchingText } from "../../lib/auctionPresentation";
import type { AuctionStatusBadge } from "../../composables/useAuctionDetail";
import { formatDate, formatMoney } from "../../lib/format";
import { injectCurrencySymbol, injectUser } from "../../lib/injection";
import type { Auction, Money } from "../../lib/types";
import AuctionImageGallery from "./AuctionImageGallery.vue";

defineProps<{
    auction: Auction;
    status: AuctionStatusBadge | null;
    priceLabel: string;
    priceValue: Money | null | undefined;
    leftoverSold: number;
}>();
const emit = defineEmits<{ delete: [] }>();
const activeImage = defineModel<number>("activeImage", { required: true });

const user = injectUser();
const currencySymbol = injectCurrencySymbol();
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="status?.tone"
                    >
                        {{ status?.label }}
                    </span>
                    <span
                        class="rounded-full bg-amber-50 dark:bg-amber-900/30 px-3 py-1 text-xs font-semibold text-amber-700 dark:text-amber-400"
                    >
                        {{ watchingText(auction.watcher_count ?? 0) }}
                    </span>
                    <span
                        v-if="auction.category"
                        class="rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200"
                    >
                        {{ auction.category.name }}
                    </span>
                </div>
                <h1 class="mt-3 text-2xl font-bold break-words">{{ auction.title }}</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ status?.summary }}
                </p>
            </div>
            <div v-if="user?.is_admin" class="flex gap-2 shrink-0">
                <router-link
                    :to="`/auctions/${auction.id}/edit`"
                    class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 border border-blue-200 dark:border-blue-700 rounded px-3 py-1"
                >
                    Edit
                </router-link>
                <button
                    @click="emit('delete')"
                    class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 border border-red-200 dark:border-red-700 rounded px-3 py-1"
                >
                    Delete
                </button>
            </div>
        </div>

        <AuctionImageGallery
            v-model:active-image="activeImage"
            :images="auction.images"
            :title="auction.title"
        />

        <p class="mt-4 text-gray-700 dark:text-gray-300 whitespace-pre-line">
            {{ auction.description }}
        </p>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p
                    class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                >
                    {{ priceLabel }}
                </p>
                <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ formatMoney(priceValue, currencySymbol) }}
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        / item
                    </span>
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p
                    class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                >
                    Starting price
                </p>
                <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ formatMoney(auction.starting_price, currencySymbol) }}
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        / item
                    </span>
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p
                    class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                >
                    Availability
                </p>
                <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ getItemLabel(auction.quantity) }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Max {{ getItemLabel(auction.max_per_bidder) }} per bidder
                </p>
                <p
                    v-if="auction.quantity > 1 || leftoverSold > 0"
                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                >
                    {{ auction.items_allocated }} allocated
                    <span v-if="leftoverSold > 0">
                        · {{ getItemLabel(leftoverSold) }} sold later
                    </span>
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p
                    class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                >
                    Timing
                </p>
                <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ auction.is_active ? "Ends" : "Ended" }}
                    {{ formatDate(auction.ends_at) }}
                </p>
                <p v-if="auction.location" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Pickup: {{ auction.location }}
                </p>
            </div>
        </div>

        <div class="mt-6 rounded-lg bg-gray-50 dark:bg-gray-700/60 p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Details</h2>
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ auction.bid_count }} bid{{ auction.bid_count !== 1 ? "s" : "" }}
                </span>
            </div>

            <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <li>Prices shown are per item.</li>
                <li v-if="auction.quantity > 1">
                    Higher bids fill first, and partial fills are possible if stock runs out.
                </li>
            </ul>
        </div>
    </div>
</template>
