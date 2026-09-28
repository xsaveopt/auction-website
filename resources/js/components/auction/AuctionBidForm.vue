<script setup lang="ts">
import { useId } from "vue";
import { useBidForm } from "../../composables/useBidForm";
import { formatMoney } from "../../lib/format";
import { injectCurrencySymbol, injectSchedule } from "../../lib/injection";
import type { Auction } from "../../lib/types";

const props = defineProps<{
    auction: Auction;
    reload: () => Promise<Auction | null>;
}>();
const error = defineModel<string>("error", { required: true });

const schedule = injectSchedule();
const currencySymbol = injectCurrencySymbol();
const { bidAmount, bidQuantity, myBid, allowsMultiple, selectedBidTotal, placeBid } = useBidForm({
    auction: () => props.auction,
    reload: () => props.reload(),
    error,
});

const uid = useId();
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
        <h2 class="text-lg font-semibold mb-3">
            {{ myBid ? "Update Your Bid" : "Place a Bid" }}
        </h2>
        <div
            v-if="schedule && schedule.enabled && !schedule.is_open"
            class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-700 rounded p-3 mb-3 text-orange-700 dark:text-orange-400 text-sm"
        >
            Bidding is closed during office hours ({{ schedule.closed_start }} –
            {{ schedule.closed_end }}). You can bid again after {{ schedule.closed_end }}.
        </div>
        <template v-else>
            <div v-if="myBid" class="mb-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Your current bid:
                    <span class="font-bold text-green-700 dark:text-green-400">{{
                        formatMoney(myBid.amount, currencySymbol)
                    }}</span>
                    <span v-if="allowsMultiple">
                        for {{ myBid.quantity }} item{{ myBid.quantity !== 1 ? "s" : "" }}</span
                    >
                    <span v-if="allowsMultiple" class="ml-1"
                        >(up to
                        {{ formatMoney(Number(myBid.amount) * myBid.quantity, currencySymbol) }}
                        total)</span
                    >
                    <span
                        v-if="(myBid.won_quantity ?? 0) > 0"
                        class="text-green-600 dark:text-green-400 ml-1"
                        >(winning {{ myBid.won_quantity }})</span
                    >
                </p>
            </div>
            <div
                v-if="error"
                class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-3 rounded mb-3"
            >
                {{ error }}
            </div>
            <form @submit.prevent="placeBid" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label
                        :for="`${uid}-amount`"
                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                        >Price per item</label
                    >
                    <div class="flex items-center">
                        <span class="text-gray-500 dark:text-gray-400 mr-1">{{
                            currencySymbol
                        }}</span>
                        <input
                            :id="`${uid}-amount`"
                            v-model="bidAmount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            class="border rounded px-3 py-2 w-32"
                        />
                    </div>
                </div>
                <div v-if="allowsMultiple">
                    <label
                        :for="`${uid}-quantity`"
                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                        >Quantity</label
                    >
                    <input
                        :id="`${uid}-quantity`"
                        v-model="bidQuantity"
                        type="number"
                        min="1"
                        :max="auction.max_per_bidder ?? undefined"
                        required
                        class="border rounded px-3 py-2 w-20"
                    />
                </div>
                <div
                    v-if="allowsMultiple"
                    class="basis-full text-sm text-gray-500 dark:text-gray-400"
                >
                    Your price is per item, so bidding
                    {{ formatMoney(bidAmount, currencySymbol) }} for {{ bidQuantity }} item{{
                        Number(bidQuantity) !== 1 ? "s" : ""
                    }}
                    means a maximum total of {{ formatMoney(selectedBidTotal, currencySymbol) }}.
                </div>
                <button
                    type="submit"
                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
                >
                    {{ myBid ? "Update Bid" : "Bid" }}
                </button>
            </form>
        </template>
    </div>
</template>
