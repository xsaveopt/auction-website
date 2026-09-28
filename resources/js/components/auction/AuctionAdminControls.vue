<script setup lang="ts">
import { useAdminAuctionControls } from "../../composables/useAdminAuctionControls";
import type { Auction, ConfirmDialogState } from "../../lib/types";

const props = defineProps<{ auction: Auction }>();
const emit = defineEmits<{
    update: [auction: Auction];
    confirm: [dialog: ConfirmDialogState];
}>();

const {
    newEndsAt,
    adminAuctionError,
    adminAuctionSaving,
    endAuction,
    reactivateAuction,
    extendAuction,
} = useAdminAuctionControls({
    auction: () => props.auction,
    onUpdate: (auction) => emit("update", auction),
    confirm: (dialog) => emit("confirm", dialog),
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded shadow p-4">
        <p
            class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-3"
        >
            Admin — Auction Controls
        </p>
        <div
            v-if="adminAuctionError"
            class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-2 rounded text-sm mb-3"
        >
            {{ adminAuctionError }}
        </div>
        <div class="flex flex-wrap gap-2 items-end">
            <template v-if="auction.is_active">
                <button
                    @click="endAuction(false)"
                    :disabled="adminAuctionSaving"
                    class="text-sm px-3 py-1.5 rounded border border-yellow-400 text-yellow-700 dark:text-yellow-400 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 disabled:opacity-50"
                >
                    End Now
                </button>
                <button
                    @click="endAuction(true)"
                    :disabled="adminAuctionSaving"
                    class="text-sm px-3 py-1.5 rounded border border-red-400 text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50"
                >
                    Cancel
                </button>
                <div class="flex items-center gap-1.5">
                    <input
                        v-model="newEndsAt"
                        type="datetime-local"
                        class="text-sm border rounded px-2 py-1.5 dark:bg-gray-700 dark:border-gray-600"
                    />
                    <button
                        @click="extendAuction"
                        :disabled="adminAuctionSaving"
                        class="text-sm px-3 py-1.5 rounded border border-blue-400 text-blue-700 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 disabled:opacity-50"
                    >
                        Extend to
                    </button>
                </div>
            </template>
            <template v-else>
                <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Status: <strong>{{ auction.status }}</strong></span
                >
                <div class="flex items-center gap-1.5">
                    <input
                        v-model="newEndsAt"
                        type="datetime-local"
                        class="text-sm border rounded px-2 py-1.5 dark:bg-gray-700 dark:border-gray-600"
                    />
                    <button
                        @click="reactivateAuction"
                        :disabled="adminAuctionSaving"
                        class="text-sm px-3 py-1.5 rounded border border-green-400 text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 disabled:opacity-50"
                    >
                        Reactivate
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
