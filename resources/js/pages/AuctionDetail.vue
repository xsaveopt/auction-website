<script setup lang="ts">
import { getItemLabel } from "../auctionPresentation";
import ConfirmDialog from "../ConfirmDialog.vue";
import AuctionImageGallery from "../components/auction/AuctionImageGallery.vue";
import AuctionQuestions from "../components/auction/AuctionQuestions.vue";
import { useAuctionDetail } from "../composables/useAuctionDetail";

const props = defineProps<{ id?: string }>();

const {
    user,
    schedule,
    currencySymbol,
    heartbeatData,
    now,
    notify,
    auction,
    bidAmount,
    bidQuantity,
    error,
    loading,
    activeImage,
    highlightedBids,
    endingSoonNotified,
    leftoverQuantity,
    leftoverError,
    buyingLeftover,
    showOfferForm,
    offerQuantity,
    offerPrice,
    offerError,
    submittingOffer,
    showAdminOfferForm,
    adminOfferUsername,
    adminOfferQuantity,
    adminOfferPrice,
    adminOfferError,
    adminOfferSaving,
    editingBidId,
    editBidAmount,
    editBidQuantity,
    showAddBid,
    addBidUsername,
    addBidAmount,
    addBidQuantity,
    adminBidError,
    showAddPurchase,
    addPurchaseUsername,
    addPurchaseQuantity,
    adminPurchaseError,
    adminPurchaseSaving,
    adminBidSaving,
    newEndsAt,
    adminAuctionError,
    adminAuctionSaving,
    allUsers,
    usersLoaded,
    confirmDialog,
    isSeller,
    canModerateQuestions,
    canAskQuestion,
    myBid,
    myLeftoverPurchase,
    myPriceOffer,
    myPriceOfferNeedsRebid,
    pendingOffers,
    allOffers,
    hasLeftoversAvailable,
    leftoverSold,
    leftoverDiscountPercent,
    leftoverSavingsPerItem,
    priceOfferLimit,
    rebidMinPrice,
    leftoverBuyTotal,
    offerTotal,
    roundIsClosed,
    effectiveLeftoverAvailable,
    auctionStatus,
    primaryPriceLabel,
    primaryPriceValue,
    shouldShowLeftoverSection,
    selectedBidTotal,
    load,
    deleteAuction,
    buyLeftover,
    submitPriceOffer,
    acceptPriceOffer,
    rejectPriceOffer,
    submitAdminOffer,
    deletePriceOffer,
    placeBid,
    loadUsers,
    startEditBid,
    cancelEditBid,
    saveBid,
    deleteBid,
    submitAddBid,
    endAuction,
    reactivateAuction,
    extendAuction,
    deleteLeftoverPurchase,
    submitAddPurchase,
    formatDate,
    formatMoney,
    watchingText,
} = useAuctionDetail(props);
</script>

<template>
    <ConfirmDialog
        v-if="confirmDialog"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :confirm-label="confirmDialog.confirmLabel"
        :danger="confirmDialog.danger"
        @confirm="
            confirmDialog.onConfirm();
            confirmDialog = null;
        "
        @cancel="confirmDialog = null"
    />

    <div v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</div>
    <div
        v-else-if="auction"
        class="xl:grid xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)] xl:items-start xl:gap-6"
    >
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold"
                                :class="auctionStatus?.tone"
                            >
                                {{ auctionStatus?.label }}
                            </span>
                            <span
                                class="rounded-full bg-amber-50 dark:bg-amber-900/30 px-3 py-1 text-xs font-semibold text-amber-700 dark:text-amber-400"
                            >
                                {{ watchingText(auction.watcher_count ?? 0) }}
                            </span>
                            <span
                                v-if="auction.category"
                                class="rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200"
                            >
                                {{ auction.category.name }}
                            </span>
                        </div>
                        <h1 class="mt-3 text-2xl font-bold break-words">{{ auction.title }}</h1>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ auctionStatus?.summary }}
                        </p>
                    </div>
                    <div v-if="user?.is_admin" class="flex gap-2 shrink-0">
                        <router-link
                            :to="`/auctions/${auction.id}/edit`"
                            class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 border border-blue-200 dark:border-blue-700 rounded px-3 py-1"
                        >
                            Edit
                        </router-link>
                        <button
                            @click="deleteAuction"
                            class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 border border-red-200 dark:border-red-700 rounded px-3 py-1"
                        >
                            Delete
                        </button>
                    </div>
                </div>

                <AuctionImageGallery
                    v-model:active-image="activeImage"
                    :images="auction.images"
                    :title="auction.title"
                />

                <p class="mt-4 text-gray-700 dark:text-gray-300 whitespace-pre-line">
                    {{ auction.description }}
                </p>
                <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                        >
                            {{ primaryPriceLabel }}
                        </p>
                        <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ currencySymbol }}{{ formatMoney(primaryPriceValue) }}
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                / item
                            </span>
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                        >
                            Starting price
                        </p>
                        <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ currencySymbol }}{{ formatMoney(auction.starting_price) }}
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                / item
                            </span>
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                        >
                            Availability
                        </p>
                        <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ getItemLabel(auction.quantity) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Max {{ getItemLabel(auction.max_per_bidder) }} per bidder
                        </p>
                        <p
                            v-if="auction.quantity > 1 || leftoverSold > 0"
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ auction.items_allocated }} allocated
                            <span v-if="leftoverSold > 0">
                                · {{ getItemLabel(leftoverSold) }} sold later
                            </span>
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                        >
                            Timing
                        </p>
                        <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ auction.is_active ? "Ends" : "Ended" }}
                            {{ formatDate(auction.ends_at) }}
                        </p>
                        <p
                            v-if="auction.location"
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Pickup: {{ auction.location }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 rounded-lg bg-gray-50 dark:bg-gray-700/60 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Details
                            </h2>
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ auction.bid_count }} bid{{ auction.bid_count !== 1 ? "s" : "" }}
                        </span>
                    </div>

                    <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <li>Prices shown are per item.</li>
                        <li v-if="auction.quantity > 1">
                            Higher bids fill first, and partial fills are possible if stock runs
                            out.
                        </li>
                    </ul>
                </div>
            </div>

            <div
                v-if="auction.is_active && user && user.id !== auction.seller?.id"
                class="bg-white dark:bg-gray-800 rounded shadow p-6"
            >
                <h2 class="text-lg font-semibold mb-3">
                    {{ myBid ? "Update Your Bid" : "Place a Bid" }}
                </h2>
                <div
                    v-if="schedule && schedule.enabled && !schedule.is_open"
                    class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-700 rounded p-3 mb-3 text-orange-700 dark:text-orange-400 text-sm"
                >
                    Bidding is closed during office hours ({{ schedule.closed_start }} –
                    {{ schedule.closed_end }}). You can bid again after {{ schedule.closed_end }}.
                </div>
                <template v-else>
                    <div v-if="myBid" class="mb-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Your current bid:
                            <span class="font-bold text-green-700 dark:text-green-400"
                                >{{ currencySymbol }}{{ formatMoney(myBid.amount) }}</span
                            >
                            <span v-if="(auction.max_per_bidder ?? 0) > 1">
                                for {{ myBid.quantity }} item{{
                                    myBid.quantity !== 1 ? "s" : ""
                                }}</span
                            >
                            <span v-if="(auction.max_per_bidder ?? 0) > 1" class="ml-1"
                                >(up to {{ currencySymbol
                                }}{{
                                    formatMoney(Number(myBid?.amount) * (myBid?.quantity ?? 0))
                                }}
                                total)</span
                            >
                            <span
                                v-if="(myBid?.won_quantity ?? 0) > 0"
                                class="text-green-600 dark:text-green-400 ml-1"
                                >(winning {{ myBid.won_quantity }})</span
                            >
                        </p>
                    </div>
                    <div
                        v-if="error"
                        class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-3 rounded mb-3"
                    >
                        {{ error }}
                    </div>
                    <form @submit.prevent="placeBid" class="flex flex-wrap gap-3 items-end">
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Price per item</label
                            >
                            <div class="flex items-center">
                                <span class="text-gray-500 dark:text-gray-400 mr-1">{{
                                    currencySymbol
                                }}</span>
                                <input
                                    v-model="bidAmount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    class="border rounded px-3 py-2 w-32"
                                />
                            </div>
                        </div>
                        <div v-if="(auction.max_per_bidder ?? 0) > 1">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Quantity</label
                            >
                            <input
                                v-model="bidQuantity"
                                type="number"
                                min="1"
                                :max="auction.max_per_bidder ?? undefined"
                                required
                                class="border rounded px-3 py-2 w-20"
                            />
                        </div>
                        <div
                            v-if="(auction.max_per_bidder ?? 0) > 1"
                            class="basis-full text-sm text-gray-500 dark:text-gray-400"
                        >
                            Your price is per item, so bidding
                            {{ currencySymbol }}{{ formatMoney(bidAmount) }} for
                            {{ bidQuantity }} item{{ Number(bidQuantity) !== 1 ? "s" : "" }} means a
                            maximum total of {{ currencySymbol
                            }}{{ formatMoney(selectedBidTotal) }}.
                        </div>
                        <button
                            type="submit"
                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
                        >
                            {{ myBid ? "Update Bid" : "Bid" }}
                        </button>
                    </form>
                </template>
            </div>
            <template v-else-if="!auction.is_active">
                <div
                    v-if="
                        auction.leftover_enabled &&
                        auction.leftover_quantity === 0 &&
                        !(roundIsClosed && !user?.is_admin)
                    "
                    class="bg-gray-100 dark:bg-gray-700/60 border border-gray-300 dark:border-gray-600 rounded p-4"
                >
                    <span class="font-bold text-gray-800 dark:text-gray-100">Sold out</span>
                    <span class="ml-2 text-gray-500 dark:text-gray-400"
                        >· All items have been claimed.</span
                    >
                </div>
                <div
                    v-else
                    class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded p-4 text-yellow-800 dark:text-yellow-300"
                >
                    This auction has ended.
                </div>

                <div
                    v-if="shouldShowLeftoverSection"
                    class="bg-white dark:bg-gray-800 rounded shadow p-6"
                >
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
                                    {{ currencySymbol
                                    }}{{ formatMoney(auction.leftover_price) }} per item
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
                                        {{ currencySymbol
                                        }}{{ formatMoney(auction.starting_price) }} price.
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

                    <template v-if="hasLeftoversAvailable && user && !isSeller && !user.is_admin">
                        <div
                            v-if="myLeftoverPurchase"
                            class="mt-4 rounded-lg border border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/20 p-4 text-green-800 dark:text-green-300"
                        >
                            <p class="font-semibold">Purchase confirmed</p>
                            <p class="mt-1 text-sm">
                                You purchased {{ getItemLabel(myLeftoverPurchase.quantity) }} at
                                {{ currencySymbol
                                }}{{ formatMoney(myLeftoverPurchase.price_per_item) }} each.
                            </p>
                            <p class="mt-1 text-xs text-green-700 dark:text-green-400">
                                Total: {{ currencySymbol
                                }}{{
                                    formatMoney(
                                        myLeftoverPurchase.quantity *
                                            Number(myLeftoverPurchase.price_per_item),
                                    )
                                }}
                            </p>
                        </div>
                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div
                                class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4"
                            >
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ myLeftoverPurchase ? "Buy more" : "Buy now" }}
                                    </h3>
                                </div>

                                <div
                                    v-if="leftoverError"
                                    class="rounded bg-red-100 dark:bg-red-900/30 p-3 text-sm text-red-700 dark:text-red-400"
                                >
                                    {{ leftoverError }}
                                </div>

                                <form @submit.prevent="buyLeftover" class="space-y-4">
                                    <div>
                                        <label
                                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                        >
                                            Quantity
                                        </label>
                                        <input
                                            v-model="leftoverQuantity"
                                            type="number"
                                            min="1"
                                            :max="auction.leftover_quantity"
                                            required
                                            class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600"
                                        />
                                    </div>
                                    <div
                                        class="rounded-lg bg-gray-50 dark:bg-gray-700/60 p-4 text-sm text-gray-600 dark:text-gray-300"
                                    >
                                        <p
                                            class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500"
                                        >
                                            Buy now total
                                        </p>
                                        <p
                                            class="mt-1 font-semibold text-gray-900 dark:text-gray-100"
                                        >
                                            {{ currencySymbol }}{{ formatMoney(leftoverBuyTotal) }}
                                        </p>
                                    </div>
                                    <button
                                        type="submit"
                                        class="w-full bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-60"
                                        :disabled="buyingLeftover"
                                    >
                                        {{
                                            buyingLeftover
                                                ? "Buying..."
                                                : myLeftoverPurchase
                                                  ? "Buy more"
                                                  : "Buy now"
                                        }}
                                    </button>
                                </form>
                            </div>

                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <template v-if="!myPriceOffer">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3
                                                class="font-semibold text-gray-900 dark:text-gray-100"
                                            >
                                                Offer a lower price
                                            </h3>
                                        </div>
                                        <button
                                            @click="
                                                showOfferForm = !showOfferForm;
                                                offerError = '';
                                            "
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
                                                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                                    >
                                                        Quantity
                                                    </label>
                                                    <input
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
                                                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                                    >
                                                        Price per item (max {{ currencySymbol
                                                        }}{{ priceOfferLimit }})
                                                    </label>
                                                    <input
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
                                                <p
                                                    class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500"
                                                >
                                                    Offer total
                                                </p>
                                                <p
                                                    class="mt-1 font-semibold text-gray-900 dark:text-gray-100"
                                                >
                                                    {{ currencySymbol
                                                    }}{{ formatMoney(offerTotal) }}
                                                </p>
                                            </div>
                                            <button
                                                type="submit"
                                                :disabled="submittingOffer"
                                                class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60 text-sm"
                                            >
                                                {{
                                                    submittingOffer
                                                        ? "Submitting..."
                                                        : "Submit offer"
                                                }}
                                            </button>
                                        </form>
                                    </div>
                                </template>

                                <div v-else class="space-y-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                            Your offer
                                        </h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            {{ getItemLabel(myPriceOffer.quantity) }} at
                                            {{ currencySymbol
                                            }}{{ formatMoney(myPriceOffer.offered_price_per_item) }}
                                            each
                                        </p>
                                    </div>

                                    <!-- Rebid requested: allow submitting a higher offer -->
                                    <template v-if="myPriceOfferNeedsRebid">
                                        <div
                                            class="rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 p-3 text-sm text-amber-700 dark:text-amber-300"
                                        >
                                            There is a tie at your price. Submit a higher offer to
                                            compete.
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
                                                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                                        >Quantity</label
                                                    >
                                                    <input
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
                                                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                                    >
                                                        Price per item ({{ currencySymbol
                                                        }}{{ rebidMinPrice }} – {{ currencySymbol
                                                        }}{{ priceOfferLimit }})
                                                    </label>
                                                    <input
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
                                                {{
                                                    submittingOffer
                                                        ? "Submitting..."
                                                        : "Submit higher offer"
                                                }}
                                            </button>
                                        </form>
                                    </template>

                                    <span
                                        v-else
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize"
                                        :class="{
                                            'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300':
                                                myPriceOffer.status === 'pending',
                                            'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300':
                                                myPriceOffer.status === 'accepted',
                                            'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300':
                                                myPriceOffer.status === 'rejected',
                                        }"
                                    >
                                        {{ myPriceOffer.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Admin: add price offer as another user -->
                    <div
                        v-else-if="hasLeftoversAvailable && user?.is_admin"
                        class="mt-4 border-t dark:border-gray-700 pt-4"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                            >
                                Admin — Add price offer
                            </p>
                            <button
                                @click="
                                    showAdminOfferForm = !showAdminOfferForm;
                                    if (showAdminOfferForm) loadUsers();
                                    adminOfferError = '';
                                "
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
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                    >Username</label
                                >
                                <select
                                    v-model="adminOfferUsername"
                                    required
                                    class="border rounded px-3 py-2 w-40 dark:bg-gray-700 dark:border-gray-600"
                                >
                                    <option value="" disabled>Select user</option>
                                    <option v-for="u in allUsers" :key="u.id" :value="u.username">
                                        {{ u.username }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                    >Qty (max {{ auction.leftover_quantity }})</label
                                >
                                <input
                                    v-model="adminOfferQuantity"
                                    type="number"
                                    min="1"
                                    :max="auction.leftover_quantity"
                                    required
                                    class="border rounded px-3 py-2 w-20 dark:bg-gray-700 dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                    >Price/item (max {{ currencySymbol
                                    }}{{ priceOfferLimit }})</label
                                >
                                <input
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
                        v-else-if="
                            user &&
                            !isSeller &&
                            !user.is_admin &&
                            (myLeftoverPurchase || myPriceOffer?.status === 'accepted')
                        "
                        class="mt-4 space-y-3"
                    >
                        <div
                            v-if="myLeftoverPurchase"
                            class="rounded-lg border border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/20 p-4 text-green-800 dark:text-green-300"
                        >
                            <p class="font-semibold">Purchase confirmed</p>
                            <p class="mt-1 text-sm">
                                You purchased {{ getItemLabel(myLeftoverPurchase.quantity) }} at
                                {{ currencySymbol
                                }}{{ formatMoney(myLeftoverPurchase.price_per_item) }} each.
                            </p>
                            <p class="mt-1 text-xs text-green-700 dark:text-green-400">
                                Total: {{ currencySymbol
                                }}{{
                                    formatMoney(
                                        myLeftoverPurchase.quantity *
                                            Number(myLeftoverPurchase.price_per_item),
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            v-if="myPriceOffer?.status === 'accepted'"
                            class="rounded-lg border border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/20 p-4 text-green-800 dark:text-green-300"
                        >
                            <p class="font-semibold">Offer accepted</p>
                            <p class="mt-1 text-sm">
                                {{ getItemLabel(myPriceOffer.quantity) }} at {{ currencySymbol
                                }}{{ formatMoney(myPriceOffer.offered_price_per_item) }} each.
                            </p>
                            <p class="mt-1 text-xs text-green-700 dark:text-green-400">
                                Total: {{ currencySymbol
                                }}{{
                                    formatMoney(
                                        myPriceOffer.quantity *
                                            Number(myPriceOffer.offered_price_per_item),
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="auction.leftover_purchases && auction.leftover_purchases.length > 0"
                        class="mt-6 border-t dark:border-gray-700 pt-6"
                    >
                        <div class="flex items-center justify-between gap-4 mb-2">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Purchases
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ auction.leftover_purchases.length }} total
                            </span>
                        </div>
                        <ul class="divide-y dark:divide-gray-700">
                            <li
                                v-for="purchase in auction.leftover_purchases"
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
                                        {{ purchase.quantity }} × {{ currencySymbol
                                        }}{{ Number(purchase.price_per_item).toFixed(2) }}
                                    </span>
                                    <button
                                        v-if="user?.is_admin"
                                        @click="deleteLeftoverPurchase(purchase)"
                                        class="text-gray-400 hover:text-red-500 dark:hover:text-red-400 ml-2"
                                        title="Delete purchase"
                                    >
                                        <svg
                                            class="w-3.5 h-3.5 inline"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Admin: all price offers -->
                    <div
                        v-if="user?.is_admin && allOffers.length > 0"
                        class="border-t dark:border-gray-700 pt-6 mt-6"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2"
                        >
                            Price offers ({{ pendingOffers.length }} pending)
                        </p>
                        <ul class="divide-y dark:divide-gray-700">
                            <li
                                v-for="offer in allOffers"
                                :key="offer.id"
                                class="py-3 flex items-center justify-between gap-2"
                            >
                                <div class="flex items-center gap-2 text-sm flex-wrap">
                                    <span class="font-medium">{{ offer.user?.username }}</span>
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize"
                                        :class="{
                                            'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300':
                                                offer.status === 'pending',
                                            'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300':
                                                offer.status === 'accepted',
                                            'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300':
                                                offer.status === 'rejected',
                                        }"
                                    >
                                        {{ offer.status }}
                                    </span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{
                                        formatDate(offer.created_at)
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-bold text-blue-700 dark:text-blue-400 text-sm"
                                    >
                                        {{ offer.quantity }} × {{ currencySymbol
                                        }}{{ Number(offer.offered_price_per_item).toFixed(2) }}
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
                                        <svg
                                            class="w-3.5 h-3.5 inline"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Admin: add purchase for another user -->
                    <div
                        v-if="user?.is_admin && (auction.leftover_quantity ?? 0) > 0"
                        class="border-t dark:border-gray-700 pt-6 mt-6"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                            >
                                Admin — Add purchase
                            </p>
                            <button
                                @click="
                                    showAddPurchase = !showAddPurchase;
                                    if (showAddPurchase) loadUsers();
                                    adminPurchaseError = '';
                                "
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
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                    >Username</label
                                >
                                <select
                                    v-model="addPurchaseUsername"
                                    required
                                    class="border rounded px-3 py-2 w-40"
                                >
                                    <option value="" disabled>Select user</option>
                                    <option v-for="u in allUsers" :key="u.id" :value="u.username">
                                        {{ u.username }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                    >Qty (max {{ auction.leftover_quantity }})</label
                                >
                                <input
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
                </div>
            </template>

            <!-- Admin auction controls -->
            <div v-if="user?.is_admin" class="bg-white dark:bg-gray-800 rounded shadow p-4">
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

            <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold">
                        Bids ({{ auction.bid_count }})
                        <span
                            v-if="auction.quantity > 1"
                            class="text-sm font-normal text-gray-500 dark:text-gray-400"
                        >
                            — {{ auction.items_allocated }} /
                            {{ auction.quantity }} allocated<template
                                v-if="auction.leftover_enabled && leftoverSold > 0"
                            >
                                · {{ leftoverSold }} sold (buy now)</template
                            >
                        </span>
                    </h2>
                    <button
                        v-if="user?.is_admin"
                        @click="
                            showAddBid = !showAddBid;
                            if (showAddBid) loadUsers();
                        "
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
                <p
                    v-if="(auction.bids ?? []).length === 0"
                    class="text-gray-500 dark:text-gray-400"
                >
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
                            <!-- Admin edit/delete -->
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
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                </button>
                            </template>
                            <!-- Admin inline edit form -->
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
                            <!-- Normal bid display -->
                            <template v-else>
                                <span
                                    class="font-bold"
                                    :class="
                                        (bid.won_quantity ?? 0) > 0
                                            ? 'text-green-700 dark:text-green-400'
                                            : 'text-gray-500 dark:text-gray-400'
                                    "
                                >
                                    {{ currencySymbol }}{{ Number(bid.amount).toFixed(2) }}
                                </span>
                                <span
                                    v-if="(bid.won_quantity ?? 0) > 0 && auction.quantity > 1"
                                    class="block text-xs text-green-600 dark:text-green-400"
                                >
                                    wins {{ bid.won_quantity }} @ {{ currencySymbol
                                    }}{{ Number(bid.price ?? bid.amount).toFixed(2) }}
                                </span>
                            </template>
                        </div>
                    </li>
                </TransitionGroup>

                <!-- Admin: add bid form -->
                <div
                    v-if="user?.is_admin && showAddBid"
                    class="mt-4 pt-4 border-t dark:border-gray-700"
                >
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
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Username</label
                            >
                            <select
                                v-model="addBidUsername"
                                required
                                class="border rounded px-3 py-2 w-40"
                            >
                                <option value="" disabled>Select user</option>
                                <option v-for="u in allUsers" :key="u.id" :value="u.username">
                                    {{ u.username }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Amount</label
                            >
                            <div class="flex items-center">
                                <span class="text-gray-500 dark:text-gray-400 mr-1">{{
                                    currencySymbol
                                }}</span>
                                <input
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
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Quantity</label
                            >
                            <input
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
        </div>

        <AuctionQuestions
            :auction-id="props.id"
            :questions="auction.questions ?? []"
            :can-moderate="!!canModerateQuestions"
            :can-ask="canAskQuestion"
            :is-seller="isSeller"
            @refresh="load()"
        />
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
