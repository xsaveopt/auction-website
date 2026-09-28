<script setup lang="ts">
import { useId } from "vue";
import { useAdminOfferForm } from "../../composables/useAdminPriceOffers";
import { injectCurrencySymbol } from "../../lib/injection";
import type { Auction, User } from "../../lib/types";

const props = defineProps<{ auction: Auction; users: User[] }>();
const emit = defineEmits<{ update: [auction: Auction]; loadUsers: [] }>();

const currencySymbol = injectCurrencySymbol();
const {
    showAdminOfferForm,
    adminOfferUsername,
    adminOfferQuantity,
    adminOfferPrice,
    adminOfferError,
    adminOfferSaving,
    priceOfferLimit,
    toggleAdminOfferForm,
    submitAdminOffer,
} = useAdminOfferForm({
    auction: () => props.auction,
    onUpdate: (auction) => emit("update", auction),
});

function toggle() {
    if (toggleAdminOfferForm()) emit("loadUsers");
}

const uid = useId();
</script>

<template>
    <div class="mt-4 border-t dark:border-gray-700 pt-4">
        <div class="flex items-center justify-between mb-2">
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
            >
                Admin — Add price offer
            </p>
            <button
                @click="toggle"
                class="text-xs border rounded px-2 py-1"
                :class="
                    showAdminOfferForm
                        ? 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400'
                        : 'border-blue-300 dark:border-blue-700 text-blue-600 dark:text-blue-400'
                "
            >
                {{ showAdminOfferForm ? "Cancel" : "+ Add offer" }}
            </button>
        </div>
        <div
            v-if="adminOfferError"
            class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-2 rounded text-sm mb-3"
        >
            {{ adminOfferError }}
        </div>
        <form
            v-if="showAdminOfferForm"
            @submit.prevent="submitAdminOffer"
            class="flex flex-wrap gap-3 items-end"
        >
            <div>
                <label
                    :for="`${uid}-username`"
                    class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                    >Username</label
                >
                <select
                    :id="`${uid}-username`"
                    v-model="adminOfferUsername"
                    required
                    class="border rounded px-3 py-2 w-40 dark:bg-gray-700 dark:border-gray-600"
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
                    >Qty (max {{ auction.leftover_quantity }})</label
                >
                <input
                    :id="`${uid}-quantity`"
                    v-model="adminOfferQuantity"
                    type="number"
                    min="1"
                    :max="auction.leftover_quantity"
                    required
                    class="border rounded px-3 py-2 w-20 dark:bg-gray-700 dark:border-gray-600"
                />
            </div>
            <div>
                <label
                    :for="`${uid}-price`"
                    class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                    >Price/item (max {{ currencySymbol }}{{ priceOfferLimit }})</label
                >
                <input
                    :id="`${uid}-price`"
                    v-model="adminOfferPrice"
                    type="number"
                    step="0.01"
                    min="0.01"
                    :max="priceOfferLimit"
                    required
                    class="border rounded px-3 py-2 w-28 dark:bg-gray-700 dark:border-gray-600"
                />
            </div>
            <button
                type="submit"
                :disabled="adminOfferSaving"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60 text-sm"
            >
                {{ adminOfferSaving ? "Saving..." : "Add" }}
            </button>
        </form>
    </div>
</template>
