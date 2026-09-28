<script setup lang="ts">
import { computed } from "vue";
import { getItemLabel } from "../../lib/auctionPresentation";
import { formatMoney } from "../../lib/format";
import { injectCurrencySymbol } from "../../lib/injection";
import type { Money } from "../../lib/types";

const props = defineProps<{
    title: string;
    prefix: string;
    quantity: number;
    pricePerItem: Money;
}>();

const currencySymbol = injectCurrencySymbol();
const total = computed(() => props.quantity * Number(props.pricePerItem));
</script>

<template>
    <div
        class="rounded-lg border border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/20 p-4 text-green-800 dark:text-green-300"
    >
        <p class="font-semibold">{{ title }}</p>
        <p class="mt-1 text-sm">
            {{ prefix }}{{ getItemLabel(quantity) }} at
            {{ formatMoney(pricePerItem, currencySymbol) }} each.
        </p>
        <p class="mt-1 text-xs text-green-700 dark:text-green-400">
            Total: {{ formatMoney(total, currencySymbol) }}
        </p>
    </div>
</template>
