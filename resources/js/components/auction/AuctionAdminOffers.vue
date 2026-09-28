<script setup lang="ts">
import { useAdminOfferActions } from "../../composables/useAdminPriceOffers";
import { formatDate, formatMoney } from "../../lib/format";
import { injectCurrencySymbol } from "../../lib/injection";
import type { Auction, ConfirmDialogState, LeftoverPriceOffer } from "../../lib/types";
import AuctionOfferStatus from "./AuctionOfferStatus.vue";
import TrashIcon from "./TrashIcon.vue";

defineProps<{ offers: LeftoverPriceOffer[]; pendingCount: number }>();
const emit = defineEmits<{
    update: [auction: Auction];
    confirm: [dialog: ConfirmDialogState];
}>();

const currencySymbol = injectCurrencySymbol();
const { acceptPriceOffer, rejectPriceOffer, deletePriceOffer } = useAdminOfferActions({
    onUpdate: (auction) => emit("update", auction),
    confirm: (dialog) => emit("confirm", dialog),
});
</script>

<template>
    <div class="border-t dark:border-gray-700 pt-6 mt-6">
        <p
            class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2"
        >
            Price offers ({{ pendingCount }} pending)
        </p>
        <ul class="divide-y dark:divide-gray-700">
            <li
                v-for="offer in offers"
                :key="offer.id"
                class="py-3 flex items-center justify-between gap-2"
            >
                <div class="flex items-center gap-2 text-sm flex-wrap">
                    <span class="font-medium">{{ offer.user?.username }}</span>
                    <AuctionOfferStatus :status="offer.status" size="sm" />
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{
                        formatDate(offer.created_at)
                    }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-blue-700 dark:text-blue-400 text-sm">
                        {{ offer.quantity }} ×
                        {{ formatMoney(offer.offered_price_per_item, currencySymbol) }}
                    </span>
                    <template v-if="offer.status === 'pending'">
                        <button
                            @click="acceptPriceOffer(offer)"
                            class="text-xs bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded"
                        >
                            Accept
                        </button>
                        <button
                            @click="rejectPriceOffer(offer)"
                            class="text-xs bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded"
                        >
                            Reject
                        </button>
                    </template>
                    <button
                        @click="deletePriceOffer(offer)"
                        class="text-gray-400 hover:text-red-500 dark:hover:text-red-400 ml-1"
                        title="Delete offer"
                    >
                        <TrashIcon class="w-3.5 h-3.5 inline" />
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
