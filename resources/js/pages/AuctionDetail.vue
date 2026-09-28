<script setup lang="ts">
import ConfirmDialog from "../components/ConfirmDialog.vue";
import AuctionAdminControls from "../components/auction/AuctionAdminControls.vue";
import AuctionAdminOverrideSales from "../components/auction/AuctionAdminOverrideSales.vue";
import AuctionBidForm from "../components/auction/AuctionBidForm.vue";
import AuctionBids from "../components/auction/AuctionBids.vue";
import AuctionLeftoverSale from "../components/auction/AuctionLeftoverSale.vue";
import AuctionOverview from "../components/auction/AuctionOverview.vue";
import AuctionQuestions from "../components/auction/AuctionQuestions.vue";
import { useAdminUsers } from "../composables/useAdminUsers";
import { useAuctionDetail } from "../composables/useAuctionDetail";

const props = defineProps<{ id?: string }>();

const {
    user,
    auction,
    error,
    loading,
    loadError,
    activeImage,
    highlightedBids,
    confirmDialog,
    isSeller,
    canModerateQuestions,
    canAskQuestion,
    canBid,
    leftoverSold,
    auctionStatus,
    primaryPriceLabel,
    primaryPriceValue,
    showSoldOutNotice,
    shouldShowLeftoverSection,
    updateAuction,
    load,
    confirm,
    deleteAuction,
} = useAuctionDetail(props);
const { users, loadUsers } = useAdminUsers();
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
            <AuctionOverview
                v-model:active-image="activeImage"
                :auction="auction"
                :status="auctionStatus"
                :price-label="primaryPriceLabel"
                :price-value="primaryPriceValue"
                :leftover-sold="leftoverSold"
                @delete="deleteAuction"
            />

            <AuctionBidForm v-if="canBid" v-model:error="error" :auction="auction" :reload="load" />
            <template v-else-if="!auction.is_active">
                <div
                    v-if="showSoldOutNotice"
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

                <AuctionLeftoverSale
                    v-if="shouldShowLeftoverSection"
                    :auction="auction"
                    :users="users"
                    @update="updateAuction"
                    @confirm="confirm"
                    @load-users="loadUsers"
                />

                <AuctionAdminOverrideSales
                    v-if="
                        user?.is_admin &&
                        auction.status !== 'cancelled' &&
                        ((auction.leftover_quantity ?? 0) > 0 ||
                            auction.leftover_purchases?.some((p) => p.is_override))
                    "
                    :auction="auction"
                    :users="users"
                    @update="updateAuction"
                    @confirm="confirm"
                    @load-users="loadUsers"
                />
            </template>

            <AuctionAdminControls
                v-if="user?.is_admin"
                :auction="auction"
                @update="updateAuction"
                @confirm="confirm"
            />

            <AuctionBids
                :auction="auction"
                :highlighted-bids="highlightedBids"
                :leftover-sold="leftoverSold"
                :users="users"
                @update="updateAuction"
                @confirm="confirm"
                @load-users="loadUsers"
            />
        </div>

        <AuctionQuestions
            :auction-id="props.id"
            :questions="auction.questions ?? []"
            :can-moderate="canModerateQuestions"
            :can-ask="canAskQuestion"
            :is-seller="isSeller"
            @refresh="load()"
        />
    </div>
    <p v-else-if="loadError" class="text-red-600 dark:text-red-400" role="alert">
        {{ loadError }}
    </p>
</template>
