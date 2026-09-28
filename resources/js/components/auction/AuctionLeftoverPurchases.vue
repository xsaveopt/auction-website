<script setup lang="ts">
import { useAdminPurchaseActions } from "../../composables/useAdminLeftoverPurchases";
import { formatDate, formatMoney } from "../../lib/format";
import { injectCurrencySymbol, injectUser } from "../../lib/injection";
import type { Auction, ConfirmDialogState, LeftoverPurchase } from "../../lib/types";
import TrashIcon from "./TrashIcon.vue";

defineProps<{ purchases: LeftoverPurchase[] }>();
const emit = defineEmits<{
    update: [auction: Auction];
    confirm: [dialog: ConfirmDialogState];
}>();

const user = injectUser();
const currencySymbol = injectCurrencySymbol();
const { deleteLeftoverPurchase } = useAdminPurchaseActions({
    onUpdate: (auction) => emit("update", auction),
    confirm: (dialog) => emit("confirm", dialog),
});
</script>

<template>
    <div class="mt-6 border-t dark:border-gray-700 pt-6">
        <div class="flex items-center justify-between gap-4 mb-2">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Purchases</h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ purchases.length }} total
            </span>
        </div>
        <ul class="divide-y dark:divide-gray-700">
            <li
                v-for="purchase in purchases"
                :key="purchase.id"
                class="py-2 flex items-center justify-between"
            >
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-medium">{{ purchase.user?.username }}</span>
                    <span
                        v-if="purchase.user?.id === user?.id"
                        class="text-xs text-blue-600 dark:text-blue-400"
                        >(you)</span
                    >
                    <span
                        v-if="purchase.from_price_offer"
                        class="rounded-full bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:text-blue-300"
                    >
                        Accepted offer
                    </span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{
                        formatDate(purchase.created_at)
                    }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="font-bold text-blue-700 dark:text-blue-400">
                        {{ purchase.quantity }} ×
                        {{ formatMoney(purchase.price_per_item, currencySymbol) }}
                    </span>
                    <button
                        v-if="user?.is_admin"
                        @click="deleteLeftoverPurchase(purchase)"
                        class="text-gray-400 hover:text-red-500 dark:hover:text-red-400 ml-2"
                        title="Delete purchase"
                    >
                        <TrashIcon class="w-3.5 h-3.5 inline" />
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
