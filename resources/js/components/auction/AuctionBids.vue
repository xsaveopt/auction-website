<script setup lang="ts">
import { useId } from "vue";
import { useAdminBids } from "../../composables/useAdminBids";
import { formatDate, formatMoney } from "../../lib/format";
import { injectCurrencySymbol, injectUser } from "../../lib/injection";
import type { Auction, ConfirmDialogState, User } from "../../lib/types";
import TrashIcon from "./TrashIcon.vue";

const props = defineProps<{
    auction: Auction;
    highlightedBids: Set<number>;
    leftoverSold: number;
    users: User[];
}>();
const emit = defineEmits<{
    update: [auction: Auction];
    confirm: [dialog: ConfirmDialogState];
    loadUsers: [];
}>();

const user = injectUser();
const currencySymbol = injectCurrencySymbol();
const {
    editingBidId,
    editBidAmount,
    editBidQuantity,
    showAddBid,
    addBidUsername,
    addBidAmount,
    addBidQuantity,
    adminBidError,
    adminBidSaving,
    toggleAddBid,
    startEditBid,
    cancelEditBid,
    saveBid,
    deleteBid,
    submitAddBid,
} = useAdminBids({
    auction: () => props.auction,
    onUpdate: (auction) => emit("update", auction),
    confirm: (dialog) => emit("confirm", dialog),
});

function toggle() {
    if (toggleAddBid()) emit("loadUsers");
}

const uid = useId();
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold">
                Bids ({{ auction.bid_count }})
                <span
                    v-if="auction.quantity > 1"
                    class="text-sm font-normal text-gray-500 dark:text-gray-400"
                >
                    — {{ auction.items_allocated }} / {{ auction.quantity }} allocated<template
                        v-if="auction.leftover_enabled && leftoverSold > 0"
                    >
                        · {{ leftoverSold }} sold (buy now)</template
                    >
                </span>
            </h2>
            <button
                v-if="user?.is_admin"
                @click="toggle"
                class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
            >
                {{ showAddBid ? "Cancel" : "+ Add bid" }}
            </button>
        </div>
        <div
            v-if="user?.is_admin && adminBidError && !showAddBid"
            class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-2 rounded text-sm mb-3"
        >
            {{ adminBidError }}
        </div>
        <p v-if="(auction.bids ?? []).length === 0" class="text-gray-500 dark:text-gray-400">
            No bids yet.
        </p>
        <TransitionGroup
            v-else
            name="bid-list"
            tag="ul"
            class="divide-y dark:divide-gray-700 relative"
        >
            <li
                v-for="bid in auction.bids"
                :key="bid.id"
                class="py-2 flex items-center justify-between transition-all duration-500"
                :class="[
                    (bid.won_quantity ?? 0) > 0
                        ? 'bg-green-50 dark:bg-green-900/20 -mx-2 px-2 rounded'
                        : '',
                    highlightedBids.has(bid.id) ? 'bid-flash' : '',
                ]"
            >
                <div class="flex items-center gap-2">
                    <span class="font-medium">{{ bid.user?.username }}</span>
                    <span
                        v-if="bid.user?.id === user?.id"
                        class="text-xs text-blue-600 dark:text-blue-400"
                        >(you)</span
                    >
                    <span
                        v-if="(auction.max_per_bidder ?? 0) > 1"
                        class="text-gray-400 dark:text-gray-500 text-xs"
                        >wants {{ bid.quantity }}</span
                    >
                    <span class="text-gray-400 dark:text-gray-500 text-xs">{{
                        formatDate(bid.created_at)
                    }}</span>
                </div>
                <div class="text-right flex items-center gap-2">
                    <template v-if="user?.is_admin && editingBidId !== bid.id">
                        <button
                            @click="startEditBid(bid)"
                            class="text-gray-400 hover:text-blue-500 dark:hover:text-blue-400"
                            title="Edit bid"
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
                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                />
                            </svg>
                        </button>
                        <button
                            @click="deleteBid(bid)"
                            class="text-gray-400 hover:text-red-500 dark:hover:text-red-400"
                            title="Delete bid"
                        >
                            <TrashIcon class="w-3.5 h-3.5" />
                        </button>
                    </template>
                    <template v-if="user?.is_admin && editingBidId === bid.id">
                        <div class="flex items-center gap-1">
                            <span class="text-xs text-gray-400">{{ currencySymbol }}</span>
                            <input
                                v-model="editBidAmount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="border rounded px-1.5 py-0.5 text-sm w-20"
                            />
                            <span class="text-xs text-gray-400">×</span>
                            <input
                                v-model="editBidQuantity"
                                type="number"
                                min="1"
                                class="border rounded px-1.5 py-0.5 text-sm w-12"
                            />
                            <button
                                @click="saveBid(bid)"
                                :disabled="adminBidSaving"
                                class="text-xs bg-blue-600 text-white px-2 py-0.5 rounded hover:bg-blue-700 disabled:opacity-60"
                            >
                                Save
                            </button>
                            <button
                                @click="cancelEditBid"
                                class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            >
                                ✕
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <span
                            class="font-bold"
                            :class="
                                (bid.won_quantity ?? 0) > 0
                                    ? 'text-green-700 dark:text-green-400'
                                    : 'text-gray-500 dark:text-gray-400'
                            "
                        >
                            {{ formatMoney(bid.amount, currencySymbol) }}
                        </span>
                        <span
                            v-if="(bid.won_quantity ?? 0) > 0 && auction.quantity > 1"
                            class="block text-xs text-green-600 dark:text-green-400"
                        >
                            wins {{ bid.won_quantity }} @
                            {{ formatMoney(bid.price ?? bid.amount, currencySymbol) }}
                        </span>
                    </template>
                </div>
            </li>
        </TransitionGroup>

        <div v-if="user?.is_admin && showAddBid" class="mt-4 pt-4 border-t dark:border-gray-700">
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-3"
            >
                Add Bid
            </p>
            <div
                v-if="adminBidError"
                class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-2 rounded text-sm mb-3"
            >
                {{ adminBidError }}
            </div>
            <form @submit.prevent="submitAddBid" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label
                        :for="`${uid}-username`"
                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                        >Username</label
                    >
                    <select
                        :id="`${uid}-username`"
                        v-model="addBidUsername"
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
                        :for="`${uid}-amount`"
                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                        >Amount</label
                    >
                    <div class="flex items-center">
                        <span class="text-gray-500 dark:text-gray-400 mr-1">{{
                            currencySymbol
                        }}</span>
                        <input
                            :id="`${uid}-amount`"
                            v-model="addBidAmount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            class="border rounded px-3 py-2 w-28"
                        />
                    </div>
                </div>
                <div>
                    <label
                        :for="`${uid}-quantity`"
                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                        >Quantity</label
                    >
                    <input
                        :id="`${uid}-quantity`"
                        v-model="addBidQuantity"
                        type="number"
                        min="1"
                        required
                        class="border rounded px-3 py-2 w-20"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="adminBidSaving"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60"
                >
                    {{ adminBidSaving ? "Adding..." : "Add Bid" }}
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped>
.bid-list-enter-from {
    opacity: 0;
    transform: translateY(-20px);
}
.bid-list-enter-active {
    transition: all 0.4s ease-out;
}
.bid-list-leave-active {
    transition: all 0.3s ease-in;
    position: absolute;
    width: 100%;
}
.bid-list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
.bid-list-move {
    transition: transform 0.4s ease;
}
@keyframes bid-highlight {
    0% {
        background-color: rgb(254 243 199);
    }
    100% {
        background-color: transparent;
    }
}
@keyframes bid-highlight-dark {
    0% {
        background-color: rgb(120 53 15 / 0.3);
    }
    100% {
        background-color: transparent;
    }
}
.bid-flash {
    animation: bid-highlight 1.5s ease-out;
}
:where(.dark) .bid-flash {
    animation: bid-highlight-dark 1.5s ease-out;
}
</style>
