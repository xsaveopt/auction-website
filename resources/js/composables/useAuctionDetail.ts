import { ref, computed, onMounted, watch, watchEffect } from "vue";
import { useRouter } from "vue-router";
import { api, ApiError } from "../lib/api";
import { apiError } from "../lib/apiError";
import { getItemLabel } from "../lib/auctionPresentation";
import { formatDate, formatMoney } from "../lib/format";
import {
    injectUser,
    injectCurrencySymbol,
    injectHeartbeatData,
    injectNow,
    injectNotifyOptional,
} from "../lib/injection";
import type { Auction, Bid, ConfirmDialogState } from "../lib/types";
import { useLeftoverState } from "./useLeftoverState";

export interface AuctionStatusBadge {
    label: string;
    tone: string;
    summary: string;
}

function bidKey(bid: Bid) {
    return `${bid.id}:${bid.amount}:${bid.quantity}`;
}

export function useAuctionDetail(props: { id?: string }) {
    const router = useRouter();

    const user = injectUser();
    const currencySymbol = injectCurrencySymbol();
    const heartbeatData = injectHeartbeatData();
    const now = injectNow();
    const notify = injectNotifyOptional();
    const auction = ref<Auction | null>(null);
    const error = ref("");
    const loading = ref(true);
    const loadError = ref("");
    let loadSequence = 0;
    const activeImage = ref(0);
    const highlightedBids = ref<Set<number>>(new Set());
    const endingSoonNotified = ref(false);
    const confirmDialog = ref<ConfirmDialogState | null>(null);

    const leftover = useLeftoverState(() => auction.value);
    const {
        myLeftoverPurchase,
        myPriceOffer,
        pendingOffers,
        hasLeftoversAvailable,
        roundIsClosed,
        effectiveLeftoverAvailable,
    } = leftover;

    const isSeller = computed(() => {
        if (!user.value || !auction.value) return false;
        return user.value.id === auction.value.seller?.id;
    });

    const canModerateQuestions = computed(() => {
        if (!user.value || !auction.value) return false;
        return user.value.id === auction.value.seller?.id || Boolean(user.value.is_admin);
    });

    const canAskQuestion = computed(() => {
        if (!user.value || !auction.value) return false;
        return user.value.id !== auction.value.seller?.id;
    });

    const canBid = computed(() =>
        Boolean(
            auction.value?.is_active && user.value && user.value.id !== auction.value.seller?.id,
        ),
    );

    const myBid = computed<Bid | null>(() => {
        if (!user.value || !auction.value) return null;
        return auction.value.bids?.find((b) => b.user?.id === user.value?.id) ?? null;
    });

    const auctionStatus = computed<AuctionStatusBadge | null>(() => {
        if (!auction.value) return null;
        if (auction.value.is_active) {
            return {
                label: "Live auction",
                tone: "bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
                summary: `Bidding closes ${formatDate(auction.value.ends_at)}.`,
            };
        }
        if (effectiveLeftoverAvailable.value) {
            return {
                label: "Leftover sale",
                tone: "bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300",
                summary: `${getItemLabel(auction.value.leftover_quantity)} still available at ${formatMoney(auction.value.leftover_price, currencySymbol.value)} each.`,
            };
        }
        if (
            auction.value.leftover_enabled &&
            auction.value.leftover_quantity === 0 &&
            !roundIsClosed.value
        ) {
            return {
                label: "Sold out",
                tone: "bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200",
                summary: "All available and leftover items have already been claimed.",
            };
        }

        return {
            label: "Ended",
            tone: "bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300",
            summary: "Bidding has closed for this auction.",
        };
    });

    const primaryPriceLabel = computed(() => {
        if (!auction.value) return "";
        if (auction.value.is_active) return "Current clearing price";
        if (effectiveLeftoverAvailable.value) return "Buy now price";
        if ((auction.value.bid_count ?? 0) > 0) return "Final clearing price";

        return "Starting price";
    });

    const primaryPriceValue = computed(() => {
        if (!auction.value) return 0;

        return effectiveLeftoverAvailable.value && !auction.value.is_active
            ? auction.value.leftover_price
            : auction.value.current_price;
    });

    const showSoldOutNotice = computed(() =>
        Boolean(
            auction.value?.leftover_enabled &&
            auction.value.leftover_quantity === 0 &&
            !(roundIsClosed.value && !user.value?.is_admin),
        ),
    );

    const shouldShowLeftoverSection = computed(() => {
        if (!auction.value?.leftover_enabled) return false;
        if (roundIsClosed.value && !user.value?.is_admin) return false;

        return Boolean(
            hasLeftoversAvailable.value ||
            auction.value.leftover_purchases?.length ||
            pendingOffers.value.length ||
            user.value?.is_admin ||
            myLeftoverPurchase.value ||
            myPriceOffer.value,
        );
    });

    function highlightNewBids(newBids: Bid[]) {
        if (!auction.value?.bids) return;

        const oldKeys = new Set(auction.value.bids.map(bidKey));
        const newHighlights = newBids.filter((b) => !oldKeys.has(bidKey(b))).map((b) => b.id);
        if (newHighlights.length) {
            highlightedBids.value = new Set(newHighlights);
            setTimeout(() => {
                highlightedBids.value = new Set();
            }, 1500);
        }
    }

    function notifyChanges(newAuction: Auction, newBids: Bid[]) {
        if (!notify) return;

        if (user.value && auction.value?.bids) {
            const oldMyBid = auction.value.bids.find((b) => b.user?.id === user.value?.id);
            const newMyBid = newBids.find((b) => b.user?.id === user.value?.id);
            if (
                oldMyBid &&
                (oldMyBid.won_quantity ?? 0) > 0 &&
                newMyBid &&
                (newMyBid.won_quantity ?? 0) === 0
            ) {
                notify(`You've been overbid on "${newAuction.title}"!`, "warning", 6000);
            }
        }

        if (auction.value?.is_active && !newAuction.is_active) {
            if (user.value) {
                const winningBid = newBids.find((b) => b.user?.id === user.value?.id);
                if (winningBid && (winningBid.won_quantity ?? 0) > 0) {
                    notify(`You won "${newAuction.title}"!`, "success", 10000);
                } else if (winningBid) {
                    notify(
                        `Auction "${newAuction.title}" has ended — you didn't win.`,
                        "info",
                        8000,
                    );
                } else {
                    notify(`"${newAuction.title}" has ended.`, "info");
                }
            } else {
                notify(`"${newAuction.title}" has ended.`, "info");
            }
            endingSoonNotified.value = true;
        }

        if (user.value && auction.value?.questions) {
            const prevAnswered = new Set(
                auction.value.questions
                    .filter((q) => q.user?.id === user.value?.id && q.answer)
                    .map((q) => q.id),
            );
            const newlyAnswered = (newAuction.questions ?? []).filter(
                (q) => q.user?.id === user.value?.id && q.answer && !prevAnswered.has(q.id),
            );
            if (newlyAnswered.length > 0) {
                notify("Your question has been answered!", "info", 6000);
            }
        }
    }

    function updateAuction(newAuction: Auction | null): boolean {
        if (!newAuction || String(newAuction.id) !== String(props.id)) return false;

        const newBids = newAuction.bids ?? [];
        highlightNewBids(newBids);
        notifyChanges(newAuction, newBids);

        auction.value = newAuction;
        activeImage.value = Math.min(activeImage.value, Math.max(newAuction.images.length - 1, 0));
        return true;
    }

    async function load(showLoading = false): Promise<Auction | null> {
        const sequence = ++loadSequence;
        if (showLoading) loading.value = true;
        try {
            const data = await api<{ auction: Auction }>(`/auctions/${props.id}`);
            if (sequence !== loadSequence) return null;
            loadError.value = "";
            return updateAuction(data.auction) ? data.auction : null;
        } catch (e) {
            if (sequence !== loadSequence) return null;
            loadError.value =
                e instanceof ApiError && e.status === 404
                    ? "This auction could not be found."
                    : "Failed to load this auction.";
            return null;
        } finally {
            if (sequence === loadSequence) loading.value = false;
        }
    }

    function confirm(dialog: ConfirmDialogState) {
        confirmDialog.value = dialog;
    }

    function deleteAuction() {
        confirm({
            title: "Delete Auction",
            message: "Are you sure you want to delete this auction? This cannot be undone.",
            confirmLabel: "Delete",
            danger: true,
            onConfirm: async () => {
                try {
                    await api(`/auctions/${props.id}`, { method: "DELETE" });
                    router.push("/");
                } catch (e) {
                    error.value = apiError(e) || "Failed to delete auction.";
                }
            },
        });
    }

    onMounted(async () => {
        await load(true);
    });

    watch(heartbeatData, (data) => {
        if (data?.auction && String(data.auction.id) === String(props.id)) {
            updateAuction(data.auction);
        }
    });

    watch(
        () => props.id,
        async () => {
            auction.value = null;
            loadError.value = "";
            error.value = "";
            highlightedBids.value = new Set();
            activeImage.value = 0;
            endingSoonNotified.value = false;
            await load(true);
        },
    );

    watchEffect(() => {
        if (
            !notify ||
            !user.value ||
            !auction.value?.is_active ||
            !auction.value?.ends_at ||
            endingSoonNotified.value ||
            isSeller.value
        )
            return;
        if (!myBid.value) return;
        const timeLeft = new Date(auction.value.ends_at).getTime() - now.value.getTime();
        if (timeLeft > 0 && timeLeft <= 5 * 60 * 1000) {
            endingSoonNotified.value = true;
            notify(`"${auction.value.title}" ends in less than 5 minutes!`, "warning", 6000);
        }
    });

    return {
        user,
        notify,
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
        myBid,
        ...leftover,
        auctionStatus,
        primaryPriceLabel,
        primaryPriceValue,
        showSoldOutNotice,
        shouldShowLeftoverSection,
        updateAuction,
        load,
        confirm,
        deleteAuction,
    };
}
