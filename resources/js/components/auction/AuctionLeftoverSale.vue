<script setup lang="ts">
import { computed } from "vue";
import { getItemLabel } from "../../lib/auctionPresentation";
import { useLeftoverState } from "../../composables/useLeftoverState";
import { formatMoney } from "../../lib/format";
import { injectCurrencySymbol, injectUser } from "../../lib/injection";
import type { Auction, ConfirmDialogState, User } from "../../lib/types";
import AuctionAdminOfferForm from "./AuctionAdminOfferForm.vue";
import AuctionAdminOffers from "./AuctionAdminOffers.vue";
import AuctionAdminPurchaseForm from "./AuctionAdminPurchaseForm.vue";
import AuctionLeftoverBuy from "./AuctionLeftoverBuy.vue";
import AuctionLeftoverPurchases from "./AuctionLeftoverPurchases.vue";
import AuctionLeftoverReceipt from "./AuctionLeftoverReceipt.vue";
import AuctionPriceOffer from "./AuctionPriceOffer.vue";

const props = defineProps<{ auction: Auction; users: User[] }>();
const emit = defineEmits<{
    update: [auction: Auction];
    confirm: [dialog: ConfirmDialogState];
    loadUsers: [];
}>();

const user = injectUser();
const currencySymbol = injectCurrencySymbol();
const {
    myLeftoverPurchase,
    myPriceOffer,
    pendingOffers,
    allOffers,
    hasLeftoversAvailable,
    leftoverSold,
    leftoverDiscountPercent,
} = useLeftoverState(() => props.auction);

const regularPurchases = computed(() =>
    (props.auction.leftover_purchases ?? []).filter((p) => !p.is_override),
);
const isSeller = computed(() => Boolean(user.value && user.value.id === props.auction.seller?.id));
const isBuyer = computed(() => Boolean(user.value && !isSeller.value && !user.value.is_admin));

function onUpdate(auction: Auction) {
    emit("update", auction);
}

function onConfirm(dialog: ConfirmDialogState) {
    emit("confirm", dialog);
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Leftover sale</h2>
            </div>
            <span
                class="rounded-full px-3 py-1 text-xs font-semibold"
                :class="
                    hasLeftoversAvailable
                        ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                "
            >
                {{
                    hasLeftoversAvailable
                        ? `${getItemLabel(auction.leftover_quantity)} left`
                        : "No leftovers left"
                }}
            </span>
        </div>

        <div
            class="mt-4 rounded-lg border p-4"
            :class="
                hasLeftoversAvailable
                    ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20'
                    : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-700/60'
            "
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p
                        class="font-semibold"
                        :class="
                            hasLeftoversAvailable
                                ? 'text-green-800 dark:text-green-300'
                                : 'text-gray-900 dark:text-gray-100'
                        "
                    >
                        {{ formatMoney(auction.leftover_price, currencySymbol) }} per item
                    </p>
                    <p
                        class="mt-1 text-sm"
                        :class="
                            hasLeftoversAvailable
                                ? 'text-green-700 dark:text-green-400'
                                : 'text-gray-500 dark:text-gray-400'
                        "
                    >
                        <span v-if="leftoverDiscountPercent > 0">
                            {{ leftoverDiscountPercent }}% off the original
                            {{ formatMoney(auction.starting_price, currencySymbol) }} price.
                        </span>
                        <span v-else> Configured leftover price. </span>
                    </p>
                </div>
                <div class="text-right text-sm">
                    <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ getItemLabel(auction.leftover_quantity) }} available
                    </p>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">
                        {{ getItemLabel(leftoverSold) }} sold after the auction
                    </p>
                </div>
            </div>
        </div>

        <template v-if="hasLeftoversAvailable && isBuyer">
            <AuctionLeftoverReceipt
                v-if="myLeftoverPurchase"
                class="mt-4"
                title="Purchase confirmed"
                prefix="You purchased "
                :quantity="myLeftoverPurchase.quantity"
                :price-per-item="myLeftoverPurchase.price_per_item"
            />
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <AuctionLeftoverBuy
                    :auction="auction"
                    :has-purchase="Boolean(myLeftoverPurchase)"
                    @update="onUpdate"
                />
                <AuctionPriceOffer :auction="auction" :offer="myPriceOffer" @update="onUpdate" />
            </div>
        </template>

        <AuctionAdminOfferForm
            v-else-if="hasLeftoversAvailable && user?.is_admin"
            :auction="auction"
            :users="users"
            @update="onUpdate"
            @load-users="emit('loadUsers')"
        />
        <div
            v-else-if="hasLeftoversAvailable && isSeller"
            class="mt-4 rounded-lg bg-gray-50 dark:bg-gray-700/60 p-4 text-sm text-gray-500 dark:text-gray-400"
        >
            You cannot purchase leftovers from your own auction.
        </div>
        <div
            v-else-if="hasLeftoversAvailable"
            class="mt-4 rounded-lg bg-gray-50 dark:bg-gray-700/60 p-4 text-sm text-gray-500 dark:text-gray-400"
        >
            <router-link
                to="/login"
                class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
            >
                Log in
            </router-link>
            to buy leftovers or send a lower offer.
        </div>
        <div
            v-else-if="isBuyer && (myLeftoverPurchase || myPriceOffer?.status === 'accepted')"
            class="mt-4 space-y-3"
        >
            <AuctionLeftoverReceipt
                v-if="myLeftoverPurchase"
                title="Purchase confirmed"
                prefix="You purchased "
                :quantity="myLeftoverPurchase.quantity"
                :price-per-item="myLeftoverPurchase.price_per_item"
            />
            <AuctionLeftoverReceipt
                v-if="myPriceOffer?.status === 'accepted'"
                title="Offer accepted"
                prefix=""
                :quantity="myPriceOffer.quantity"
                :price-per-item="myPriceOffer.offered_price_per_item"
            />
        </div>

        <AuctionLeftoverPurchases
            v-if="regularPurchases.length > 0"
            :purchases="regularPurchases"
            @update="onUpdate"
            @confirm="onConfirm"
        />

        <AuctionAdminOffers
            v-if="user?.is_admin && allOffers.length > 0"
            :offers="allOffers"
            :pending-count="pendingOffers.length"
            @update="onUpdate"
            @confirm="onConfirm"
        />

        <AuctionAdminPurchaseForm
            v-if="user?.is_admin && (auction.leftover_quantity ?? 0) > 0"
            :auction="auction"
            :users="users"
            @update="onUpdate"
            @load-users="emit('loadUsers')"
        />
    </div>
</template>
