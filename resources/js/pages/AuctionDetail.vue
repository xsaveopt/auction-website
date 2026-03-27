<script setup>
import { ref, inject, computed, onMounted, watch, watchEffect } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";
import {
    getItemLabel,
    getLeftoverDiscountPercent,
    hasAvailableLeftovers,
} from "../auctionPresentation.js";

const router = useRouter();

const props = defineProps({ id: String });
const user = inject("user");
const schedule = inject("schedule");
const currencySymbol = inject("currencySymbol");
const heartbeatData = inject("heartbeatData");
const now = inject("now");
const notify = inject("notify", null);
const auction = ref(null);
const bidAmount = ref("");
const bidQuantity = ref(1);
const error = ref("");
const questionError = ref("");
const loading = ref(true);
const activeImage = ref(0);
const questionText = ref("");
const answerDrafts = ref({});
const editingQuestionId = ref(null);
const askingQuestion = ref(false);
const savingAnswerId = ref(null);
const deletingQuestionId = ref(null);
const highlightedBids = ref(new Set());
const endingSoonNotified = ref(false);
const leftoverQuantity = ref(1);
const leftoverError = ref("");
const buyingLeftover = ref(false);
const showOfferForm = ref(false);
const offerQuantity = ref(1);
const offerPrice = ref("");
const offerError = ref("");
const submittingOffer = ref(false);

// Admin state
const showAdminOfferForm = ref(false);
const adminOfferUsername = ref("");
const adminOfferQuantity = ref(1);
const adminOfferPrice = ref("");
const adminOfferError = ref("");
const adminOfferSaving = ref(false);
const editingBidId = ref(null);
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
const allUsers = ref([]);
const usersLoaded = ref(false);

const isSeller = computed(() => {
    if (!user.value || !auction.value) return false;
    return user.value.id === auction.value.seller.id;
});

const canModerateQuestions = computed(() => {
    if (!user.value || !auction.value) return false;
    return user.value.id === auction.value.seller.id || user.value.is_admin;
});

const canAskQuestion = computed(() => {
    if (!user.value || !auction.value) return false;
    return user.value.id !== auction.value.seller.id;
});

const myBid = computed(() => {
    if (!user.value || !auction.value) return null;
    return auction.value.bids.find((b) => b.user.id === user.value.id);
});

const myLeftoverPurchase = computed(() => {
    if (!user.value || !auction.value?.leftover_purchases) return null;
    return auction.value.leftover_purchases.find((p) => p.user.id === user.value.id) ?? null;
});

const myPriceOffer = computed(() => {
    if (!user.value || !auction.value?.leftover_price_offers) return null;
    return auction.value.leftover_price_offers.find((o) => o.user.id === user.value.id) ?? null;
});

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
        auction.value.quantity - auction.value.items_allocated - auction.value.leftover_quantity,
    );
});

const leftoverDiscountPercent = computed(() => getLeftoverDiscountPercent(auction.value));

const leftoverSavingsPerItem = computed(() => {
    if (!auction.value) return 0;

    return Math.max(0, Number(auction.value.starting_price) - Number(auction.value.leftover_price));
});

const priceOfferLimit = computed(() => {
    const limit = Number(auction.value?.leftover_price) - 0.01;

    return limit > 0 ? limit.toFixed(2) : "0.01";
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

const auctionStatus = computed(() => {
    if (!auction.value) return null;
    if (auction.value.is_active) {
        return {
            label: "Live auction",
            tone: "bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
            summary: `Bidding closes ${formatDate(auction.value.ends_at)}.`,
        };
    }
    if (hasLeftoversAvailable.value) {
        return {
            label: "Leftover sale",
            tone: "bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300",
            summary: `${getItemLabel(auction.value.leftover_quantity)} still available at ${currencySymbol.value}${formatMoney(auction.value.leftover_price)} each.`,
        };
    }
    if (auction.value.leftover_enabled && auction.value.leftover_quantity === 0) {
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
    if (hasLeftoversAvailable.value) return "Buy now price";
    if (auction.value.bid_count > 0) return "Final clearing price";

    return "Starting price";
});

const primaryPriceValue = computed(() => {
    if (!auction.value) return 0;

    return hasLeftoversAvailable.value && !auction.value.is_active
        ? auction.value.leftover_price
        : auction.value.current_price;
});

const shouldShowLeftoverSection = computed(() => {
    if (!auction.value?.leftover_enabled) return false;

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
const answeredQuestions = computed(
    () => auction.value?.questions?.filter((question) => question.answer) ?? [],
);
const openQuestions = computed(
    () => auction.value?.questions?.filter((question) => !question.answer) ?? [],
);

function bidKey(bid) {
    return `${bid.id}:${bid.amount}:${bid.quantity}`;
}

function clampBidQuantity(quantity, maxPerBidder) {
    const max = Math.max(1, Number(maxPerBidder || 1));
    const normalized = Math.trunc(Number(quantity || 1));
    return Math.min(Math.max(normalized || 1, 1), max);
}

function updateAuction(newAuction, resetForm = false) {
    if (!newAuction) return;

    // Detect new or changed bids for highlight animation
    if (auction.value?.bids) {
        const oldKeys = new Set(auction.value.bids.map(bidKey));
        const newHighlights = newAuction.bids
            .filter((b) => !oldKeys.has(bidKey(b)))
            .map((b) => b.id);
        if (newHighlights.length) {
            highlightedBids.value = new Set(newHighlights);
            setTimeout(() => {
                highlightedBids.value = new Set();
            }, 1500);
        }
    }

    // Overbid detection
    if (notify && user.value && auction.value?.bids) {
        const oldMyBid = auction.value.bids.find((b) => b.user.id === user.value.id);
        const newMyBid = newAuction.bids.find((b) => b.user.id === user.value.id);
        if (
            oldMyBid &&
            (oldMyBid.won_quantity ?? 0) > 0 &&
            newMyBid &&
            (newMyBid.won_quantity ?? 0) === 0
        ) {
            notify(`You've been overbid on "${newAuction.title}"!`, "warning", 6000);
        }
    }

    // Auction ended detection
    if (notify && auction.value?.is_active && !newAuction.is_active) {
        if (user.value) {
            const myBid = newAuction.bids.find((b) => b.user.id === user.value.id);
            if (myBid && (myBid.won_quantity ?? 0) > 0) {
                notify(`You won "${newAuction.title}"!`, "success", 10000);
            } else if (myBid) {
                notify(`Auction "${newAuction.title}" has ended — you didn't win.`, "info", 8000);
            } else {
                notify(`"${newAuction.title}" has ended.`, "info");
            }
        } else {
            notify(`"${newAuction.title}" has ended.`, "info");
        }
        endingSoonNotified.value = true;
    }

    // Question answered detection
    if (notify && user.value && auction.value?.questions) {
        const prevAnswered = new Set(
            auction.value.questions
                .filter((q) => q.user.id === user.value.id && q.answer)
                .map((q) => q.id),
        );
        const newlyAnswered = (newAuction.questions ?? []).filter(
            (q) => q.user.id === user.value.id && q.answer && !prevAnswered.has(q.id),
        );
        if (newlyAnswered.length > 0) {
            notify("Your question has been answered!", "info", 6000);
        }
    }

    auction.value = newAuction;
    activeImage.value = Math.min(activeImage.value, Math.max(newAuction.images.length - 1, 0));
    if (resetForm) {
        const my = newAuction.bids.find((b) => b.user.id === user.value?.id);
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
    const data = await api(`/auctions/${props.id}`);
    updateAuction(data.auction, resetForm);
    loading.value = false;
}

async function deleteAuction() {
    if (!confirm("Are you sure you want to delete this auction?")) return;
    try {
        await api(`/auctions/${props.id}`, { method: "DELETE" });
        router.push("/");
    } catch (e) {
        error.value = e.data?.message || "Failed to delete auction.";
    }
}

async function buyLeftover() {
    leftoverError.value = "";
    try {
        buyingLeftover.value = true;
        const data = await api(`/auctions/${props.id}/leftover-purchases`, {
            method: "POST",
            body: JSON.stringify({ quantity: Number(leftoverQuantity.value) }),
        });
        updateAuction(data.auction);
        notify?.("Purchase successful!", "success");
    } catch (e) {
        leftoverError.value =
            e.data?.message || e.data?.errors?.quantity?.[0] || "Purchase failed.";
    } finally {
        buyingLeftover.value = false;
    }
}

async function submitPriceOffer() {
    offerError.value = "";
    submittingOffer.value = true;
    try {
        const data = await api(`/auctions/${props.id}/leftover-price-offers`, {
            method: "POST",
            body: JSON.stringify({
                quantity: Number(offerQuantity.value),
                offered_price_per_item: Number(offerPrice.value),
            }),
        });
        updateAuction(data.auction);
        showOfferForm.value = false;
        notify?.("Offer submitted!", "success");
    } catch (e) {
        offerError.value =
            e.data?.message ||
            e.data?.errors?.quantity?.[0] ||
            e.data?.errors?.offered_price_per_item?.[0] ||
            "Failed to submit offer.";
    } finally {
        submittingOffer.value = false;
    }
}

async function acceptPriceOffer(offer) {
    if (
        !confirm(
            `Accept offer of ${currencySymbol.value}${Number(offer.offered_price_per_item).toFixed(2)} × ${offer.quantity} from ${offer.user.username}?`,
        )
    )
        return;
    try {
        const data = await api(`/admin/leftover-price-offers/${offer.id}/accept`, {
            method: "POST",
        });
        updateAuction(data.auction);
        notify?.("Offer accepted.", "success");
    } catch (e) {
        notify?.(e.data?.message || "Failed to accept offer.", "error");
    }
}

async function rejectPriceOffer(offer) {
    if (!confirm(`Reject offer from ${offer.user.username}?`)) return;
    try {
        const data = await api(`/admin/leftover-price-offers/${offer.id}/reject`, {
            method: "POST",
        });
        updateAuction(data.auction);
        notify?.("Offer rejected.", "success");
    } catch (e) {
        notify?.(e.data?.message || "Failed to reject offer.", "error");
    }
}

async function submitAdminOffer() {
    adminOfferError.value = "";
    adminOfferSaving.value = true;
    try {
        const data = await api(`/admin/auctions/${props.id}/leftover-price-offers`, {
            method: "POST",
            body: JSON.stringify({
                username: adminOfferUsername.value,
                quantity: Number(adminOfferQuantity.value),
                offered_price_per_item: Number(adminOfferPrice.value),
            }),
        });
        updateAuction(data.auction);
        showAdminOfferForm.value = false;
        adminOfferUsername.value = "";
        adminOfferQuantity.value = 1;
        adminOfferPrice.value = "";
        notify?.("Offer added.", "success");
    } catch (e) {
        adminOfferError.value =
            e.data?.message ||
            e.data?.errors?.username?.[0] ||
            e.data?.errors?.quantity?.[0] ||
            e.data?.errors?.offered_price_per_item?.[0] ||
            "Failed to add offer.";
    } finally {
        adminOfferSaving.value = false;
    }
}

async function deletePriceOffer(offer) {
    if (!confirm(`Delete offer from ${offer.user.username}?`)) return;
    try {
        const data = await api(`/admin/leftover-price-offers/${offer.id}`, { method: "DELETE" });
        updateAuction(data.auction);
        notify?.("Offer deleted.", "success");
    } catch (e) {
        notify?.(e.data?.message || "Failed to delete offer.", "error");
    }
}

async function placeBid() {
    error.value = "";
    try {
        const quantity =
            auction.value?.max_per_bidder > 1
                ? clampBidQuantity(bidQuantity.value, auction.value.max_per_bidder)
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
        error.value =
            e.data?.message ||
            e.data?.errors?.amount?.[0] ||
            e.data?.errors?.quantity?.[0] ||
            "Failed to place bid.";
    }
}

function startAnswer(question) {
    editingQuestionId.value = question.id;
    answerDrafts.value = {
        ...answerDrafts.value,
        [question.id]: question.answer ?? "",
    };
    questionError.value = "";
}

function cancelAnswer(questionId) {
    if (editingQuestionId.value === questionId) {
        editingQuestionId.value = null;
    }
}

async function submitQuestion() {
    questionError.value = "";

    const question = questionText.value.trim();

    if (!question) {
        questionError.value = "Question is required.";
        return;
    }

    try {
        askingQuestion.value = true;
        await api(`/auctions/${props.id}/questions`, {
            method: "POST",
            body: JSON.stringify({ question }),
        });
        questionText.value = "";
        await load();
    } catch (e) {
        questionError.value =
            e.data?.message || e.data?.errors?.question?.[0] || "Failed to ask question.";
    } finally {
        askingQuestion.value = false;
    }
}

async function submitAnswer(question) {
    questionError.value = "";

    const answer = (answerDrafts.value[question.id] ?? "").trim();

    if (!answer) {
        questionError.value = "Answer is required.";
        return;
    }

    try {
        savingAnswerId.value = question.id;
        await api(`/questions/${question.id}`, {
            method: "PUT",
            body: JSON.stringify({ answer }),
        });
        editingQuestionId.value = null;
        await load();
    } catch (e) {
        questionError.value =
            e.data?.message || e.data?.errors?.answer?.[0] || "Failed to save answer.";
    } finally {
        savingAnswerId.value = null;
    }
}

async function deleteQuestion(question) {
    if (!confirm("Delete this question?")) return;

    questionError.value = "";

    try {
        deletingQuestionId.value = question.id;
        await api(`/questions/${question.id}`, { method: "DELETE" });
        if (editingQuestionId.value === question.id) {
            editingQuestionId.value = null;
        }
        const nextDrafts = { ...answerDrafts.value };
        delete nextDrafts[question.id];
        answerDrafts.value = nextDrafts;
        await load();
    } catch (e) {
        questionError.value = e.data?.message || "Failed to delete question.";
    } finally {
        deletingQuestionId.value = null;
    }
}

async function loadUsers() {
    if (usersLoaded.value) return;
    try {
        const data = await api("/admin/users");
        allUsers.value = data.users;
        usersLoaded.value = true;
    } catch {}
}

function startEditBid(bid) {
    editingBidId.value = bid.id;
    editBidAmount.value = String(Number(bid.amount).toFixed(2));
    editBidQuantity.value = bid.quantity;
    adminBidError.value = "";
}

function cancelEditBid() {
    editingBidId.value = null;
    adminBidError.value = "";
}

async function saveBid(bid) {
    adminBidError.value = "";
    adminBidSaving.value = true;
    try {
        const data = await api(`/admin/bids/${bid.id}`, {
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
        adminBidError.value = e.data?.message || "Failed to update bid.";
    } finally {
        adminBidSaving.value = false;
    }
}

async function deleteBid(bid) {
    if (!confirm(`Delete bid by ${bid.user.username}?`)) return;
    adminBidError.value = "";
    try {
        const data = await api(`/admin/bids/${bid.id}`, { method: "DELETE" });
        updateAuction(data.auction);
        notify?.("Bid deleted.", "success");
    } catch (e) {
        adminBidError.value = e.data?.message || "Failed to delete bid.";
    }
}

async function submitAddBid() {
    adminBidError.value = "";
    adminBidSaving.value = true;
    try {
        const data = await api(`/admin/auctions/${props.id}/bids`, {
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
        adminBidError.value =
            e.data?.message ||
            e.data?.errors?.username?.[0] ||
            e.data?.errors?.amount?.[0] ||
            "Failed to add bid.";
    } finally {
        adminBidSaving.value = false;
    }
}

async function endAuction(cancel = false) {
    const label = cancel ? "cancel" : "end";
    if (!confirm(`${cancel ? "Cancel" : "End"} this auction now?`)) return;
    adminAuctionError.value = "";
    adminAuctionSaving.value = true;
    try {
        const data = await api(`/admin/auctions/${props.id}/${label}`, { method: "POST" });
        updateAuction(data.auction);
        notify?.(`Auction ${cancel ? "cancelled" : "ended"}.`, "success");
    } catch (e) {
        adminAuctionError.value = e.data?.message || `Failed to ${label} auction.`;
    } finally {
        adminAuctionSaving.value = false;
    }
}

async function reactivateAuction() {
    if (!newEndsAt.value) {
        adminAuctionError.value = "Set a new end time first.";
        return;
    }
    adminAuctionError.value = "";
    adminAuctionSaving.value = true;
    try {
        const data = await api(`/admin/auctions/${props.id}/reactivate`, {
            method: "POST",
            body: JSON.stringify({ ends_at: newEndsAt.value }),
        });
        updateAuction(data.auction);
        newEndsAt.value = "";
        notify?.("Auction reactivated.", "success");
    } catch (e) {
        adminAuctionError.value =
            e.data?.message || e.data?.errors?.ends_at?.[0] || "Failed to reactivate.";
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
        const data = await api(`/admin/auctions/${props.id}/extend`, {
            method: "POST",
            body: JSON.stringify({ ends_at: newEndsAt.value }),
        });
        updateAuction(data.auction);
        newEndsAt.value = "";
        notify?.("Auction extended.", "success");
    } catch (e) {
        adminAuctionError.value =
            e.data?.message || e.data?.errors?.ends_at?.[0] || "Failed to extend.";
    } finally {
        adminAuctionSaving.value = false;
    }
}

async function deleteLeftoverPurchase(purchase) {
    if (!confirm(`Delete purchase by ${purchase.user.username}?`)) return;
    try {
        const data = await api(`/admin/leftover-purchases/${purchase.id}`, { method: "DELETE" });
        updateAuction(data.auction);
        notify?.("Purchase deleted.", "success");
    } catch (e) {
        notify?.("Failed to delete purchase.", "error");
    }
}

async function submitAddPurchase() {
    adminPurchaseError.value = "";
    adminPurchaseSaving.value = true;
    try {
        const data = await api(`/admin/auctions/${props.id}/leftover-purchases`, {
            method: "POST",
            body: JSON.stringify({
                username: addPurchaseUsername.value,
                quantity: Number(addPurchaseQuantity.value),
            }),
        });
        updateAuction(data.auction);
        showAddPurchase.value = false;
        addPurchaseUsername.value = "";
        addPurchaseQuantity.value = 1;
        notify?.("Purchase added.", "success");
    } catch (e) {
        adminPurchaseError.value =
            e.data?.message ||
            e.data?.errors?.username?.[0] ||
            e.data?.errors?.quantity?.[0] ||
            "Failed to add purchase.";
    } finally {
        adminPurchaseSaving.value = false;
    }
}

function formatDate(d) {
    if (!d) return "";
    return d.slice(0, 16).replace("T", " ");
}

function formatMoney(value) {
    return Number(value).toFixed(2);
}

function watchingText(count) {
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
        offerQuantity.value = Math.min(Math.max(Number(offerQuantity.value) || 1, 1), maxQuantity);
    },
);

// Ending-soon alert: fires once when < 5 minutes remain for an auction the user has bid on
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
    const timeLeft = new Date(auction.value.ends_at) - now.value;
    if (timeLeft > 0 && timeLeft <= 5 * 60 * 1000) {
        endingSoonNotified.value = true;
        notify(`"${auction.value.title}" ends in less than 5 minutes!`, "warning", 6000);
    }
});
</script>

<template>
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
                                :class="auctionStatus.tone"
                            >
                                {{ auctionStatus.label }}
                            </span>
                            <span
                                class="rounded-full bg-amber-50 dark:bg-amber-900/30 px-3 py-1 text-xs font-semibold text-amber-700 dark:text-amber-400"
                            >
                                {{ watchingText(auction.watcher_count) }}
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
                            {{ auctionStatus.summary }}
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

                <div v-if="auction.images.length" class="mt-4">
                    <img
                        :src="auction.images[activeImage].url"
                        :alt="auction.title"
                        class="w-full max-h-96 object-contain rounded bg-gray-50 dark:bg-gray-700"
                    />
                    <div v-if="auction.images.length > 1" class="flex gap-2 mt-2">
                        <button
                            v-for="(img, i) in auction.images"
                            :key="img.id"
                            @click="activeImage = i"
                            class="w-16 h-16 rounded overflow-hidden border-2"
                            :class="
                                i === activeImage
                                    ? 'border-blue-500'
                                    : 'border-transparent opacity-60 hover:opacity-100'
                            "
                        >
                            <img :src="img.url" class="w-full h-full object-cover" />
                        </button>
                    </div>
                </div>

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
                v-if="auction.is_active && user && user.id !== auction.seller.id"
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
                            <span v-if="auction.max_per_bidder > 1">
                                for {{ myBid.quantity }} item{{
                                    myBid.quantity !== 1 ? "s" : ""
                                }}</span
                            >
                            <span v-if="auction.max_per_bidder > 1" class="ml-1"
                                >(up to {{ currencySymbol
                                }}{{ formatMoney(myBid.amount * myBid.quantity) }} total)</span
                            >
                            <span
                                v-if="myBid.won_quantity > 0"
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
                        <div v-if="auction.max_per_bidder > 1">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Quantity</label
                            >
                            <input
                                v-model="bidQuantity"
                                type="number"
                                min="1"
                                :max="auction.max_per_bidder"
                                required
                                class="border rounded px-3 py-2 w-20"
                            />
                        </div>
                        <div
                            v-if="auction.max_per_bidder > 1"
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
                    v-if="auction.leftover_enabled && auction.leftover_quantity === 0"
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
                        <div v-else class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div
                                class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4"
                            >
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                        Buy now
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
                                        {{ buyingLeftover ? "Buying..." : "Buy now" }}
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
                                    <span
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
                                    <span class="font-medium">{{ purchase.user.username }}</span>
                                    <span
                                        v-if="purchase.user.id === user?.id"
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
                                    <span class="font-medium">{{ offer.user.username }}</span>
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
                        v-if="user?.is_admin && auction.leftover_quantity > 0"
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
                <p v-if="auction.bids.length === 0" class="text-gray-500 dark:text-gray-400">
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
                            bid.won_quantity > 0
                                ? 'bg-green-50 dark:bg-green-900/20 -mx-2 px-2 rounded'
                                : '',
                            highlightedBids.has(bid.id) ? 'bid-flash' : '',
                        ]"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ bid.user.username }}</span>
                            <span
                                v-if="bid.user.id === user?.id"
                                class="text-xs text-blue-600 dark:text-blue-400"
                                >(you)</span
                            >
                            <span
                                v-if="auction.max_per_bidder > 1"
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
                                        bid.won_quantity > 0
                                            ? 'text-green-700 dark:text-green-400'
                                            : 'text-gray-500 dark:text-gray-400'
                                    "
                                >
                                    {{ currencySymbol }}{{ Number(bid.amount).toFixed(2) }}
                                </span>
                                <span
                                    v-if="bid.won_quantity > 0 && auction.quantity > 1"
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

        <div class="mt-6 xl:mt-0 xl:sticky xl:top-6">
            <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Frequently Asked Questions</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Answered responses stay here for future buyers.
                        </p>
                    </div>
                    <span class="text-sm text-gray-500 dark:text-gray-400"
                        >{{ answeredQuestions.length }} answered</span
                    >
                </div>

                <div
                    v-if="questionError"
                    class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-3 rounded mt-4"
                >
                    {{ questionError }}
                </div>

                <div v-if="answeredQuestions.length" class="mt-4 space-y-4">
                    <div
                        v-for="question in answeredQuestions"
                        :key="question.id"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 p-4"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                        >
                            Question
                        </p>
                        <p class="mt-2 font-medium whitespace-pre-line">
                            {{ question.question }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Asked by {{ question.user.username }} ·
                            {{ formatDate(question.created_at) }}
                        </p>

                        <div v-if="editingQuestionId === question.id" class="mt-4">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Answer</label
                            >
                            <textarea
                                v-model="answerDrafts[question.id]"
                                rows="4"
                                class="w-full border rounded px-3 py-2"
                            ></textarea>
                            <div class="mt-3 flex gap-3">
                                <button
                                    @click="submitAnswer(question)"
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60"
                                    :disabled="savingAnswerId === question.id"
                                >
                                    {{
                                        savingAnswerId === question.id ? "Saving..." : "Save answer"
                                    }}
                                </button>
                                <button
                                    @click="cancelAnswer(question.id)"
                                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                        <div v-else class="mt-4 rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                            >
                                Answer
                            </p>
                            <p class="mt-2 text-gray-700 dark:text-gray-300 whitespace-pre-line">
                                {{ question.answer }}
                            </p>
                            <p
                                v-if="question.answered_at"
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                Answered {{ formatDate(question.answered_at) }}
                            </p>
                        </div>

                        <div v-if="canModerateQuestions" class="mt-4 flex gap-3">
                            <button
                                v-if="editingQuestionId !== question.id"
                                @click="startAnswer(question)"
                                class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                            >
                                Update answer
                            </button>
                            <button
                                @click="deleteQuestion(question)"
                                class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 disabled:opacity-60"
                                :disabled="deletingQuestionId === question.id"
                            >
                                {{
                                    deletingQuestionId === question.id
                                        ? "Deleting..."
                                        : "Delete question"
                                }}
                            </button>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    No answered questions yet.
                </p>

                <div class="mt-6 border-t dark:border-gray-700 pt-6">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="font-semibold">Open questions</h3>
                        <span class="text-sm text-gray-500 dark:text-gray-400"
                            >{{ openQuestions.length }} awaiting an answer</span
                        >
                    </div>

                    <div v-if="openQuestions.length" class="mt-4 space-y-4">
                        <div
                            v-for="question in openQuestions"
                            :key="question.id"
                            class="rounded-lg border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium">
                                        {{ question.user.username }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatDate(question.created_at) }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-amber-50 dark:bg-amber-900/30 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-400"
                                >
                                    Awaiting answer
                                </span>
                            </div>

                            <p class="mt-3 text-gray-700 dark:text-gray-300 whitespace-pre-line">
                                {{ question.question }}
                            </p>

                            <div v-if="editingQuestionId === question.id" class="mt-4">
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                    >Answer</label
                                >
                                <textarea
                                    v-model="answerDrafts[question.id]"
                                    rows="4"
                                    class="w-full border rounded px-3 py-2"
                                ></textarea>
                                <div class="mt-3 flex gap-3">
                                    <button
                                        @click="submitAnswer(question)"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60"
                                        :disabled="savingAnswerId === question.id"
                                    >
                                        {{
                                            savingAnswerId === question.id
                                                ? "Saving..."
                                                : "Publish answer"
                                        }}
                                    </button>
                                    <button
                                        @click="cancelAnswer(question.id)"
                                        class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            <div v-else-if="canModerateQuestions" class="mt-4 flex gap-3">
                                <button
                                    @click="startAnswer(question)"
                                    class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                >
                                    Answer question
                                </button>
                                <button
                                    @click="deleteQuestion(question)"
                                    class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 disabled:opacity-60"
                                    :disabled="deletingQuestionId === question.id"
                                >
                                    {{
                                        deletingQuestionId === question.id
                                            ? "Deleting..."
                                            : "Delete question"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        No open questions right now.
                    </p>
                </div>

                <div class="mt-6 border-t dark:border-gray-700 pt-6">
                    <h3 class="font-semibold">Ask a question</h3>

                    <form v-if="canAskQuestion" @submit.prevent="submitQuestion" class="mt-4">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Your question</label
                        >
                        <textarea
                            v-model="questionText"
                            rows="3"
                            maxlength="2000"
                            class="w-full border rounded px-3 py-2"
                            placeholder="Ask about condition, pickup, included accessories, or anything else buyers should know."
                        ></textarea>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Answered questions move into the FAQ above.
                            </p>
                            <button
                                type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60"
                                :disabled="askingQuestion"
                            >
                                {{ askingQuestion ? "Sending..." : "Send question" }}
                            </button>
                        </div>
                    </form>
                    <p v-else-if="isSeller" class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        You can answer or remove questions from the lists above.
                    </p>
                    <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        <router-link
                            to="/login"
                            class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                            >Log in</router-link
                        >
                        to ask a question.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* New bids slide in and fade */
.bid-list-enter-from {
    opacity: 0;
    transform: translateY(-20px);
}
.bid-list-enter-active {
    transition: all 0.4s ease-out;
}
/* Removed bids fade out */
.bid-list-leave-active {
    transition: all 0.3s ease-in;
    position: absolute;
    width: 100%;
}
.bid-list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
/* Reorder animation */
.bid-list-move {
    transition: transform 0.4s ease;
}
/* Flash highlight for new or updated bids */
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
