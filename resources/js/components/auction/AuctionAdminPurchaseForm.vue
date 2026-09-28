<script setup lang="ts">
import { useId } from "vue";
import { useAdminPurchaseForm } from "../../composables/useAdminLeftoverPurchases";
import type { Auction, User } from "../../lib/types";

const props = defineProps<{ auction: Auction; users: User[] }>();
const emit = defineEmits<{ update: [auction: Auction]; loadUsers: [] }>();

const {
    showAddPurchase,
    addPurchaseUsername,
    addPurchaseQuantity,
    adminPurchaseError,
    adminPurchaseSaving,
    toggleAddPurchase,
    submitAddPurchase,
} = useAdminPurchaseForm({
    auction: () => props.auction,
    onUpdate: (auction) => emit("update", auction),
});

function toggle() {
    if (toggleAddPurchase()) emit("loadUsers");
}

const uid = useId();
</script>

<template>
    <div class="border-t dark:border-gray-700 pt-6 mt-6">
        <div class="flex items-center justify-between mb-2">
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
            >
                Admin — Add purchase
            </p>
            <button
                @click="toggle"
                class="text-xs border rounded px-2 py-1"
                :class="
                    showAddPurchase
                        ? 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400'
                        : 'border-blue-300 dark:border-blue-700 text-blue-600 dark:text-blue-400'
                "
            >
                {{ showAddPurchase ? "Cancel" : "+ Add purchase" }}
            </button>
        </div>
        <div
            v-if="adminPurchaseError"
            class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-2 rounded text-sm mb-3"
        >
            {{ adminPurchaseError }}
        </div>
        <form
            v-if="showAddPurchase"
            @submit.prevent="submitAddPurchase"
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
                    v-model="addPurchaseUsername"
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
                    >Qty (max {{ auction.leftover_quantity }})</label
                >
                <input
                    :id="`${uid}-quantity`"
                    v-model="addPurchaseQuantity"
                    type="number"
                    min="1"
                    :max="auction.leftover_quantity"
                    required
                    class="border rounded px-3 py-2 w-20"
                />
            </div>
            <button
                type="submit"
                :disabled="adminPurchaseSaving"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60 text-sm"
            >
                {{ adminPurchaseSaving ? "Saving..." : "Add" }}
            </button>
        </form>
    </div>
</template>
