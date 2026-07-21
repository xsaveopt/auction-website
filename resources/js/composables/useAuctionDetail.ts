import { ref, computed, onMounted, watch, watchEffect } from "vue";
import { useRouter } from "vue-router";
import { api, ApiError } from "../api";
import {
    getItemLabel,
    getLeftoverDiscountPercent,
    hasAvailableLeftovers,
} from "../auctionPresentation";
import {
    injectUser,
    injectSchedule,
    injectCurrencySymbol,
    injectHeartbeatData,
    injectNow,
    injectNotifyOptional,
} from "../injection";
import type {
    Auction,
    Bid,
    ConfirmDialogState,
    LeftoverPriceOffer,
    LeftoverPurchase,
    Money,
    User,
} from "../types";

export function useAuctionDetail(props: { id?: string }) {
    const router = useRouter();

    const user = injectUser();
    const schedule = injectSchedule();
    const currencySymbol = injectCurrencySymbol();
    const heartbeatData = injectHeartbeatData();
    const now = injectNow();
    const notify = injectNotifyOptional();
    const auction = ref<Auction | null>(null);
    const bidAmount = ref("");
    const bidQuantity = ref(1);
    const error = ref("");
    const loading = ref(true);
    const activeImage = ref(0);
    const highlightedBids = ref<Set<number>>(new Set());
    const endingSoonNotified = ref(false);
    const leftoverQuantity = ref(1);
    const leftoverError = ref("");
    const buyingLeftover = ref(false);
    const showOfferForm = ref(false);
    const offerQuantity = ref(1);
    const offerPrice = ref("");
    const offerError = ref("");
    const submittingOffer = ref(false);

    const showAdminOfferForm = ref(false);
    const adminOfferUsername = ref("");
    const adminOfferQuantity = ref(1);
    const adminOfferPrice = ref("");
    const adminOfferError = ref("");
    const adminOfferSaving = ref(false);
    const editingBidId = ref<number | null>(null);
    const editBidAmount = ref("");
    const editBidQuantity = ref(1);
    const showAddBid = ref(false);
    const addBidUsername = ref("");
    const addBidAmount = ref("");
    const addBidQuantity = ref(1);
    const adminBidError = ref("");
    const showAddPurchase = ref(false);
    const addPurchaseUsername = ref("");
    const addPurchaseQuantity = ref(1);
    const adminPurchaseError = ref("");
    const adminPurchaseSaving = ref(false);
    const adminBidSaving = ref(false);
    const newEndsAt = ref("");
    const adminAuctionError = ref("");
    const adminAuctionSaving = ref(false);
    const allUsers = ref<User[]>([]);
    const usersLoaded = ref(false);
    const confirmDialog = ref<ConfirmDialogState | null>(null);

    const isSeller = computed(() => {
        if (!user.value || !auction.value) return false;
        return user.value.id === auction.value.seller?.id;
    });

    const canModerateQuestions = computed(() => {
        if (!user.value || !auction.value) return false;
        return user.value.id === auction.value.seller?.id || user.value.is_admin;
    });

    const canAskQuestion = computed(() => {
        if (!user.value || !auction.value) return false;
        return user.value.id !== auction.value.seller?.id;
    });

    const myBid = computed<Bid | null>(() => {
        if (!user.value || !auction.value) return null;
        return auction.value.bids?.find((b) => b.user?.id === user.value?.id) ?? null;
    });

    const myLeftoverPurchase = computed<LeftoverPurchase | null>(() => {
        if (!user.value || !auction.value?.leftover_purchases) return null;
        return auction.value.leftover_purchases.find((p) => p.user?.id === user.value?.id) ?? null;
    });

    const myPriceOffer = computed<LeftoverPriceOffer | null>(() => {
        if (!user.value || !auction.value?.leftover_price_offers) return null;
        return (
            auction.value.leftover_price_offers.find((o) => o.user?.id === user.value?.id) ?? null
        );
    });

    const myPriceOfferNeedsRebid = computed(
        () =>
            myPriceOffer.value?.status === "pending" &&
            myPriceOffer.value?.rebid_requested_at != null,
    );

    const pendingOffers = computed(() => {
        if (!auction.value?.leftover_price_offers) return [];
        return auction.value.leftover_price_offers.filter((o) => o.status === "pending");
    });

    const allOffers = computed(() => auction.value?.leftover_price_offers ?? []);

    const hasLeftoversAvailable = computed(() => hasAvailableLeftovers(auction.value));

    const leftoverSold = computed(() => {
        if (!auction.value) return 0;
        return Math.max(
            0,
            auction.value.quantity -
                (auction.value.items_allocated ?? 0) -
                (auction.value.leftover_quantity ?? 0),
        );
    });

    const leftoverDiscountPercent = computed(() => getLeftoverDiscountPercent(auction.value));

    const leftoverSavingsPerItem = computed(() => {
        if (!auction.value) return 0;

        return Math.max(
            0,
            Number(auction.value.starting_price) - Number(auction.value.leftover_price),
        );
    });

    const priceOfferLimit = computed(() => {
        const limit = Number(auction.value?.leftover_price) - 0.01;

        return limit > 0 ? limit.toFixed(2) : "0.01";
    });

    const rebidMinPrice = computed(() => {
        const current = Number(myPriceOffer.value?.offered_price_per_item ?? 0);
        return (current + 0.01).toFixed(2);
    });

    const leftoverBuyTotal = computed(() => {
        if (!auction.value) return 0;

        const quantity =
            Number(auction.value.leftover_quantity) > 1 ? Number(leftoverQuantity.value || 1) : 1;

        return quantity * Number(auction.value.leftover_price);
    });

    const offerTotal = computed(() => {
        const quantity =
            Number(auction.value?.leftover_quantity) > 1 ? Number(offerQuantity.value || 1) : 1;

        return quantity * Number(offerPrice.value || 0);
    });

    const roundIsClosed = computed(() => auction.value?.round?.status === "ended");

    const effectiveLeftoverAvailable = computed(
        () => hasLeftoversAvailable.value && !(roundIsClosed.value && !user.value?.is_admin),
    );

    const auctionStatus = computed(() => {
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
                summary: `${getItemLabel(auction.value.leftover_quantity)} still available at ${currencySymbol.value}${formatMoney(auction.value.leftover_price)} each.`,
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

    const selectedBidTotal = computed(
        () => Number(bidAmount.value || 0) * Number(bidQuantity.value || 0),
    );

    function bidKey(bid: Bid) {
        return `${bid.id}:${bid.amount}:${bid.quantity}`;
    }

    function clampBidQuantity(
        quantity: number | string | null | undefined,
        maxPerBidder: number | string | null | undefined,
    ) {
        const max = Math.max(1, Number(maxPerBidder || 1));
        const normalized = Math.trunc(Number(quantity || 1));
        return Math.min(Math.max(normalized || 1, 1), max);
    }

    function updateAuction(newAuction: Auction | null, resetForm = false) {
        if (!newAuction) return;

        const newBids = newAuction.bids ?? [];

        if (auction.value?.bids) {
            const oldKeys = new Set(auction.value.bids.map(bidKey));
            const newHighlights = newBids.filter((b) => !oldKeys.has(bidKey(b))).map((b) => b.id);
            if (newHighlights.length) {
                highlightedBids.value = new Set(newHighlights);
                setTimeout(() => {
                    highlightedBids.value = new Set();
                }, 1500);
            }
        }

        if (notify && user.value && auction.value?.bids) {
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

        if (notify && auction.value?.is_active && !newAuction.is_active) {
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

        if (notify && user.value && auction.value?.questions) {
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

        auction.value = newAuction;
        activeImage.value = Math.min(activeImage.value, Math.max(newAuction.images.length - 1, 0));
        if (resetForm) {
            const my = newBids.find((b) => b.user?.id === user.value?.id);
            bidAmount.value = my
                ? (Number(my.amount) + 1).toFixed(2)
                : Number(newAuction.starting_price).toFixed(2);
            bidQuantity.value = my ? clampBidQuantity(my.quantity, newAuction.max_per_bidder) : 1;
        } else {
            bidQuantity.value = clampBidQuantity(bidQuantity.value, newAuction.max_per_bidder);
        }
    }

    async function load(showLoading = false, resetForm = false) {
        if (showLoading) loading.value = true;
        const data = await api<{ auction: Auction }>(`/auctions/${props.id}`);
        updateAuction(data.auction, resetForm);
        loading.value = false;
    }

    function apiError(e: unknown, ...fields: string[]): string | undefined {
        if (!(e instanceof ApiError)) return undefined;
        if (e.data.message) return e.data.message;
        for (const field of fields) {
            const value = e.data.errors?.[field]?.[0];
            if (value) return value;
        }
        return undefined;
    }

    function deleteAuction() {
        confirmDialog.value = {
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
        };
    }

    async function buyLeftover() {
        leftoverError.value = "";
        try {
            buyingLeftover.value = true;
            const data = await api<{ auction: Auction }>(
                `/auctions/${props.id}/leftover-purchases`,
                {
                    method: "POST",
                    body: JSON.stringify({ quantity: Number(leftoverQuantity.value) }),
                },
            );
            updateAuction(data.auction);
            notify?.("Purchase successful!", "success");
        } catch (e) {
            leftoverError.value = apiError(e, "quantity") || "Purchase failed.";
        } finally {
            buyingLeftover.value = false;
        }
    }

    async function submitPriceOffer() {
        offerError.value = "";
        submittingOffer.value = true;
        try {
            const data = await api<{ auction: Auction }>(
                `/auctions/${props.id}/leftover-price-offers`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        quantity: Number(offerQuantity.value),
                        offered_price_per_item: Number(offerPrice.value),
                    }),
                },
            );
            updateAuction(data.auction);
            showOfferForm.value = false;
            notify?.("Offer submitted!", "success");
        } catch (e) {
            offerError.value =
                apiError(e, "quantity", "offered_price_per_item") || "Failed to submit offer.";
        } finally {
            submittingOffer.value = false;
        }
    }

    function acceptPriceOffer(offer: LeftoverPriceOffer) {
        confirmDialog.value = {
            message: `Accept offer of ${currencySymbol.value}${Number(offer.offered_price_per_item).toFixed(2)} × ${offer.quantity} from ${offer.user?.username}?`,
            confirmLabel: "Accept",
            danger: false,
            onConfirm: async () => {
                try {
                    const data = await api<{ auction: Auction }>(
                        `/admin/leftover-price-offers/${offer.id}/accept`,
                        {
                            method: "POST",
                        },
                    );
                    updateAuction(data.auction);
                    notify?.("Offer accepted.", "success");
                } catch (e) {
                    notify?.(apiError(e) || "Failed to accept offer.", "error");
                }
            },
        };
    }

    function rejectPriceOffer(offer: LeftoverPriceOffer) {
        confirmDialog.value = {
            message: `Reject offer from ${offer.user?.username}?`,
            confirmLabel: "Reject",
            danger: true,
            onConfirm: async () => {
                try {
                    const data = await api<{ auction: Auction }>(
                        `/admin/leftover-price-offers/${offer.id}/reject`,
                        {
                            method: "POST",
                        },
                    );
                    updateAuction(data.auction);
                    notify?.("Offer rejected.", "success");
                } catch (e) {
                    notify?.(apiError(e) || "Failed to reject offer.", "error");
                }
            },
        };
    }

    async function submitAdminOffer() {
        adminOfferError.value = "";
        adminOfferSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(
                `/admin/auctions/${props.id}/leftover-price-offers`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        username: adminOfferUsername.value,
                        quantity: Number(adminOfferQuantity.value),
                        offered_price_per_item: Number(adminOfferPrice.value),
                    }),
                },
            );
            updateAuction(data.auction);
            showAdminOfferForm.value = false;
            adminOfferUsername.value = "";
            adminOfferQuantity.value = 1;
            adminOfferPrice.value = "";
            notify?.("Offer added.", "success");
        } catch (e) {
            adminOfferError.value =
                apiError(e, "username", "quantity", "offered_price_per_item") ||
                "Failed to add offer.";
        } finally {
            adminOfferSaving.value = false;
        }
    }

    function deletePriceOffer(offer: LeftoverPriceOffer) {
        confirmDialog.value = {
            message: `Delete offer from ${offer.user?.username}?`,
            confirmLabel: "Delete",
            danger: true,
            onConfirm: async () => {
                try {
                    const data = await api<{ auction: Auction }>(
                        `/admin/leftover-price-offers/${offer.id}`,
                        {
                            method: "DELETE",
                        },
                    );
                    updateAuction(data.auction);
                    notify?.("Offer deleted.", "success");
                } catch (e) {
                    notify?.(apiError(e) || "Failed to delete offer.", "error");
                }
            },
        };
    }

    async function placeBid() {
        error.value = "";
        try {
            const quantity =
                Number(auction.value?.max_per_bidder) > 1
                    ? clampBidQuantity(bidQuantity.value, auction.value?.max_per_bidder)
                    : 1;

            bidQuantity.value = quantity;

            await api(`/auctions/${props.id}/bids`, {
                method: "POST",
                body: JSON.stringify({
                    amount: Number(bidAmount.value),
                    quantity,
                }),
            });
            await load(false, true);
            notify?.("Bid placed successfully!", "success");
        } catch (e) {
            error.value = apiError(e, "amount", "quantity") || "Failed to place bid.";
        }
    }

    async function loadUsers() {
        if (usersLoaded.value) return;
        try {
            const data = await api<{ users: User[] }>("/admin/users");
            allUsers.value = data.users;
            usersLoaded.value = true;
        } catch {}
    }

    function startEditBid(bid: Bid) {
        editingBidId.value = bid.id;
        editBidAmount.value = String(Number(bid.amount).toFixed(2));
        editBidQuantity.value = bid.quantity;
        adminBidError.value = "";
    }

    function cancelEditBid() {
        editingBidId.value = null;
        adminBidError.value = "";
    }

    async function saveBid(bid: Bid) {
        adminBidError.value = "";
        adminBidSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(`/admin/bids/${bid.id}`, {
                method: "PUT",
                body: JSON.stringify({
                    amount: Number(editBidAmount.value),
                    quantity: Number(editBidQuantity.value),
                }),
            });
            updateAuction(data.auction);
            editingBidId.value = null;
            notify?.("Bid updated.", "success");
        } catch (e) {
            adminBidError.value = apiError(e) || "Failed to update bid.";
        } finally {
            adminBidSaving.value = false;
        }
    }

    function deleteBid(bid: Bid) {
        confirmDialog.value = {
            message: `Delete bid by ${bid.user?.username}?`,
            confirmLabel: "Delete",
            danger: true,
            onConfirm: async () => {
                adminBidError.value = "";
                try {
                    const data = await api<{ auction: Auction }>(`/admin/bids/${bid.id}`, {
                        method: "DELETE",
                    });
                    updateAuction(data.auction);
                    notify?.("Bid deleted.", "success");
                } catch (e) {
                    adminBidError.value = apiError(e) || "Failed to delete bid.";
                }
            },
        };
    }

    async function submitAddBid() {
        adminBidError.value = "";
        adminBidSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(`/admin/auctions/${props.id}/bids`, {
                method: "POST",
                body: JSON.stringify({
                    username: addBidUsername.value,
                    amount: Number(addBidAmount.value),
                    quantity: Number(addBidQuantity.value),
                }),
            });
            updateAuction(data.auction);
            showAddBid.value = false;
            addBidUsername.value = "";
            addBidAmount.value = "";
            addBidQuantity.value = 1;
            notify?.("Bid added.", "success");
        } catch (e) {
            adminBidError.value = apiError(e, "username", "amount") || "Failed to add bid.";
        } finally {
            adminBidSaving.value = false;
        }
    }

    function endAuction(cancel = false) {
        const label = cancel ? "cancel" : "end";
        confirmDialog.value = {
            message: `${cancel ? "Cancel" : "End"} this auction now?`,
            confirmLabel: cancel ? "Cancel Auction" : "End Auction",
            danger: true,
            onConfirm: async () => {
                adminAuctionError.value = "";
                adminAuctionSaving.value = true;
                try {
                    const data = await api<{ auction: Auction }>(
                        `/admin/auctions/${props.id}/${label}`,
                        { method: "POST" },
                    );
                    updateAuction(data.auction);
                    notify?.(`Auction ${cancel ? "cancelled" : "ended"}.`, "success");
                } catch (e) {
                    adminAuctionError.value = apiError(e) || `Failed to ${label} auction.`;
                } finally {
                    adminAuctionSaving.value = false;
                }
            },
        };
    }

    async function reactivateAuction() {
        if (!newEndsAt.value) {
            adminAuctionError.value = "Set a new end time first.";
            return;
        }
        adminAuctionError.value = "";
        adminAuctionSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(`/admin/auctions/${props.id}/reactivate`, {
                method: "POST",
                body: JSON.stringify({ ends_at: newEndsAt.value }),
            });
            updateAuction(data.auction);
            newEndsAt.value = "";
            notify?.("Auction reactivated.", "success");
        } catch (e) {
            adminAuctionError.value = apiError(e, "ends_at") || "Failed to reactivate.";
        } finally {
            adminAuctionSaving.value = false;
        }
    }

    async function extendAuction() {
        if (!newEndsAt.value) {
            adminAuctionError.value = "Set a new end time first.";
            return;
        }
        adminAuctionError.value = "";
        adminAuctionSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(`/admin/auctions/${props.id}/extend`, {
                method: "POST",
                body: JSON.stringify({ ends_at: newEndsAt.value }),
            });
            updateAuction(data.auction);
            newEndsAt.value = "";
            notify?.("Auction extended.", "success");
        } catch (e) {
            adminAuctionError.value = apiError(e, "ends_at") || "Failed to extend.";
        } finally {
            adminAuctionSaving.value = false;
        }
    }

    function deleteLeftoverPurchase(purchase: LeftoverPurchase) {
        confirmDialog.value = {
            message: `Delete purchase by ${purchase.user?.username}?`,
            confirmLabel: "Delete",
            danger: true,
            onConfirm: async () => {
                try {
                    const data = await api<{ auction: Auction }>(
                        `/admin/leftover-purchases/${purchase.id}`,
                        {
                            method: "DELETE",
                        },
                    );
                    updateAuction(data.auction);
                    notify?.("Purchase deleted.", "success");
                } catch {
                    notify?.("Failed to delete purchase.", "error");
                }
            },
        };
    }

    async function submitAddPurchase() {
        adminPurchaseError.value = "";
        adminPurchaseSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(
                `/admin/auctions/${props.id}/leftover-purchases`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        username: addPurchaseUsername.value,
                        quantity: Number(addPurchaseQuantity.value),
                    }),
                },
            );
            updateAuction(data.auction);
            showAddPurchase.value = false;
            addPurchaseUsername.value = "";
            addPurchaseQuantity.value = 1;
            notify?.("Purchase added.", "success");
        } catch (e) {
            adminPurchaseError.value =
                apiError(e, "username", "quantity") || "Failed to add purchase.";
        } finally {
            adminPurchaseSaving.value = false;
        }
    }

    function formatDate(d: string | null | undefined) {
        if (!d) return "";
        return d.slice(0, 16).replace("T", " ");
    }

    function formatMoney(value: Money | null | undefined) {
        return Number(value).toFixed(2);
    }

    function watchingText(count: number) {
        return `${count} currently watching`;
    }

    onMounted(async () => {
        await load(true, true);
    });

    watch(heartbeatData, (data) => {
        if (data?.auction && String(data.auction.id) === String(props.id)) {
            updateAuction(data.auction);
        }
    });

    watch(
        () => props.id,
        async () => {
            activeImage.value = 0;
            endingSoonNotified.value = false;
            await load(true, true);
        },
    );

    watch(
        () => auction.value?.leftover_quantity,
        (quantity) => {
            const maxQuantity = Math.max(1, Number(quantity || 1));

            leftoverQuantity.value = Math.min(
                Math.max(Number(leftoverQuantity.value) || 1, 1),
                maxQuantity,
            );
            offerQuantity.value = Math.min(
                Math.max(Number(offerQuantity.value) || 1, 1),
                maxQuantity,
            );
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
        updateAuction,
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
    };
}
