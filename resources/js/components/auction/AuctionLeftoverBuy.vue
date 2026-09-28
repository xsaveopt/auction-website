<script setup lang="ts">
import { useId } from "vue";
import { useLeftoverPurchase } from "../../composables/useLeftoverPurchase";
import { formatMoney } from "../../lib/format";
import { injectCurrencySymbol } from "../../lib/injection";
import type { Auction } from "../../lib/types";

const props = defineProps<{ auction: Auction; hasPurchase: boolean }>();
const emit = defineEmits<{ update: [auction: Auction] }>();

const currencySymbol = injectCurrencySymbol();
const { leftoverQuantity, leftoverError, buyingLeftover, leftoverBuyTotal, buyLeftover } =
    useLeftoverPurchase({
        auction: () => props.auction,
        onUpdate: (auction) => emit("update", auction),
    });

const uid = useId();
</script>

<template>
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
        <div>
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                {{ hasPurchase ? "Buy more" : "Buy now" }}
            </h3>
        </div>

        <div
            v-if="leftoverError"
            class="rounded bg-red-100 dark:bg-red-900/30 p-3 text-sm text-red-700 dark:text-red-400"
        >
            {{ leftoverError }}
        </div>

        <form @submit.prevent="buyLeftover" class="space-y-4">
            <div>
                <label
                    :for="`${uid}-quantity`"
                    class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                >
                    Quantity
                </label>
                <input
                    :id="`${uid}-quantity`"
                    v-model="leftoverQuantity"
                    type="number"
                    min="1"
                    :max="auction.leftover_quantity"
                    required
                    class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600"
                />
            </div>
            <div
                class="rounded-lg bg-gray-50 dark:bg-gray-700/60 p-4 text-sm text-gray-600 dark:text-gray-300"
            >
                <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    Buy now total
                </p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                    {{ formatMoney(leftoverBuyTotal, currencySymbol) }}
                </p>
            </div>
            <button
                type="submit"
                class="w-full bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-60"
                :disabled="buyingLeftover"
            >
                {{ buyingLeftover ? "Buying..." : hasPurchase ? "Buy more" : "Buy now" }}
            </button>
        </form>
    </div>
</template>
