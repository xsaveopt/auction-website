<script setup lang="ts">
import { useId } from "vue";
import { getItemLabel } from "../../lib/auctionPresentation";
import { usePriceOffer } from "../../composables/usePriceOffer";
import { formatMoney } from "../../lib/format";
import { injectCurrencySymbol } from "../../lib/injection";
import type { Auction, LeftoverPriceOffer } from "../../lib/types";
import AuctionOfferStatus from "./AuctionOfferStatus.vue";

const props = defineProps<{ auction: Auction; offer: LeftoverPriceOffer | null }>();
const emit = defineEmits<{ update: [auction: Auction] }>();

const currencySymbol = injectCurrencySymbol();
const {
    showOfferForm,
    offerQuantity,
    offerPrice,
    offerError,
    submittingOffer,
    needsRebid,
    priceOfferLimit,
    rebidMinPrice,
    offerTotal,
    toggleOfferForm,
    submitPriceOffer,
} = usePriceOffer({
    auction: () => props.auction,
    offer: () => props.offer,
    onUpdate: (auction) => emit("update", auction),
});

const uid = useId();
</script>

<template>
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <template v-if="!offer">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                        Offer a lower price
                    </h3>
                </div>
                <button
                    @click="toggleOfferForm"
                    class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                >
                    {{ showOfferForm ? "Cancel" : "Make offer" }}
                </button>
            </div>

            <div v-if="showOfferForm" class="mt-4 space-y-4">
                <div
                    v-if="offerError"
                    class="rounded bg-red-100 dark:bg-red-900/30 p-3 text-sm text-red-700 dark:text-red-400"
                >
                    {{ offerError }}
                </div>
                <form @submit.prevent="submitPriceOffer" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label
                                :for="`${uid}-quantity`"
                                class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >
                                Quantity
                            </label>
                            <input
                                :id="`${uid}-quantity`"
                                v-model="offerQuantity"
                                type="number"
                                min="1"
                                :max="auction.leftover_quantity"
                                required
                                class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600"
                            />
                        </div>
                        <div>
                            <label
                                :for="`${uid}-price`"
                                class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >
                                Price per item (max {{ currencySymbol }}{{ priceOfferLimit }})
                            </label>
                            <input
                                :id="`${uid}-price`"
                                v-model="offerPrice"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="priceOfferLimit"
                                required
                                class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600"
                            />
                        </div>
                    </div>
                    <div
                        class="rounded-lg bg-gray-50 dark:bg-gray-700/60 p-4 text-sm text-gray-600 dark:text-gray-300"
                    >
                        <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Offer total
                        </p>
                        <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                            {{ formatMoney(offerTotal, currencySymbol) }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="submittingOffer"
                        class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60 text-sm"
                    >
                        {{ submittingOffer ? "Submitting..." : "Submit offer" }}
                    </button>
                </form>
            </div>
        </template>

        <div v-else class="space-y-3">
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">Your offer</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ getItemLabel(offer.quantity) }} at
                    {{ formatMoney(offer.offered_price_per_item, currencySymbol) }}
                    each
                </p>
            </div>

            <template v-if="needsRebid">
                <div
                    class="rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 p-3 text-sm text-amber-700 dark:text-amber-300"
                >
                    There is a tie at your price. Submit a higher offer to compete.
                </div>
                <div
                    v-if="offerError"
                    class="rounded bg-red-100 dark:bg-red-900/30 p-3 text-sm text-red-700 dark:text-red-400"
                >
                    {{ offerError }}
                </div>
                <form @submit.prevent="submitPriceOffer" class="space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label
                                :for="`${uid}-rebid-quantity`"
                                class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Quantity</label
                            >
                            <input
                                :id="`${uid}-rebid-quantity`"
                                v-model="offerQuantity"
                                type="number"
                                min="1"
                                :max="auction.leftover_quantity"
                                required
                                class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600"
                            />
                        </div>
                        <div>
                            <label
                                :for="`${uid}-rebid-price`"
                                class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >
                                Price per item ({{ currencySymbol }}{{ rebidMinPrice }} –
                                {{ currencySymbol }}{{ priceOfferLimit }})
                            </label>
                            <input
                                :id="`${uid}-rebid-price`"
                                v-model="offerPrice"
                                type="number"
                                step="0.01"
                                :min="rebidMinPrice"
                                :max="priceOfferLimit"
                                required
                                class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600"
                            />
                        </div>
                    </div>
                    <button
                        type="submit"
                        :disabled="submittingOffer"
                        class="w-full bg-amber-600 text-white px-4 py-2 rounded hover:bg-amber-700 disabled:opacity-60 text-sm"
                    >
                        {{ submittingOffer ? "Submitting..." : "Submit higher offer" }}
                    </button>
                </form>
            </template>

            <AuctionOfferStatus v-else :status="offer.status" size="md" />
        </div>
    </div>
</template>
