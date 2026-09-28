<script setup lang="ts">
import { computed, useId } from "vue";
import { useAdminPurchaseActions } from "../../composables/useAdminLeftoverPurchases";
import {
    overrideQuoteUrl,
    useAdminOverrideSaleForm,
} from "../../composables/useAdminOverrideSales";
import { formatDate, formatMoney } from "../../lib/format";
import { injectCurrencySymbol } from "../../lib/injection";
import type { Auction, ConfirmDialogState, User } from "../../lib/types";
import TrashIcon from "./TrashIcon.vue";

const props = defineProps<{ auction: Auction; users: User[] }>();
const emit = defineEmits<{
    update: [auction: Auction];
    confirm: [dialog: ConfirmDialogState];
    loadUsers: [];
}>();

const currencySymbol = injectCurrencySymbol();
const overrideSales = computed(() =>
    (props.auction.leftover_purchases ?? []).filter((p) => p.is_override),
);
const remaining = computed(() => props.auction.leftover_quantity ?? 0);

const { showForm, username, quantity, pricePerItem, error, saving, toggleForm, submit } =
    useAdminOverrideSaleForm({
        auction: () => props.auction,
        onUpdate: (auction) => emit("update", auction),
    });
const { deleteLeftoverPurchase } = useAdminPurchaseActions({
    onUpdate: (auction) => emit("update", auction),
    confirm: (dialog) => emit("confirm", dialog),
});

function toggle() {
    if (toggleForm()) emit("loadUsers");
}

const uid = useId();
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Override sales</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Sell remaining items at any price after rounds and leftover sales have closed.
                    Each sale gets its own quote.
                </p>
            </div>
            <button
                v-if="remaining > 0"
                @click="toggle"
                class="text-xs border rounded px-2 py-1"
                :class="
                    showForm
                        ? 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400'
                        : 'border-blue-300 dark:border-blue-700 text-blue-600 dark:text-blue-400'
                "
            >
                {{ showForm ? "Cancel" : "+ Add override sale" }}
            </button>
        </div>

        <div
            v-if="error"
            class="mt-4 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-2 rounded text-sm"
        >
            {{ error }}
        </div>
        <form v-if="showForm" @submit.prevent="submit" class="mt-4 flex flex-wrap gap-3 items-end">
            <div>
                <label
                    :for="`${uid}-username`"
                    class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                    >Username</label
                >
                <select
                    :id="`${uid}-username`"
                    v-model="username"
                    required
                    class="border rounded px-3 py-2 w-40"
                >
                    <option value="" disabled>Select user</option>
                    <option v-for="u in users" :key="u.id" :value="u.username">
                        {{ u.username }}
                    </option>
                </select>
            </div>
            <div>
                <label
                    :for="`${uid}-quantity`"
                    class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                    >Qty (max {{ remaining }})</label
                >
                <input
                    :id="`${uid}-quantity`"
                    v-model="quantity"
                    type="number"
                    min="1"
                    :max="remaining"
                    required
                    class="border rounded px-3 py-2 w-20"
                />
            </div>
            <div>
                <label
                    :for="`${uid}-price`"
                    class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                    >Price / item ({{ currencySymbol }})</label
                >
                <input
                    :id="`${uid}-price`"
                    v-model="pricePerItem"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    class="border rounded px-3 py-2 w-28"
                />
            </div>
            <button
                type="submit"
                :disabled="saving"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60 text-sm"
            >
                {{ saving ? "Saving..." : "Add" }}
            </button>
        </form>

        <ul v-if="overrideSales.length > 0" class="mt-4 divide-y dark:divide-gray-700">
            <li
                v-for="sale in overrideSales"
                :key="sale.id"
                class="py-2 flex items-center justify-between"
            >
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-medium">{{ sale.user?.username }}</span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{
                        formatDate(sale.created_at)
                    }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-blue-700 dark:text-blue-400">
                        {{ sale.quantity }} ×
                        {{ formatMoney(sale.price_per_item, currencySymbol) }}
                    </span>
                    <a
                        :href="overrideQuoteUrl(auction.id, sale.id)"
                        target="_blank"
                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                    >
                        Quote
                    </a>
                    <button
                        @click="deleteLeftoverPurchase(sale)"
                        class="text-gray-400 hover:text-red-500 dark:hover:text-red-400"
                        title="Delete override sale"
                    >
                        <TrashIcon class="w-3.5 h-3.5 inline" />
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
