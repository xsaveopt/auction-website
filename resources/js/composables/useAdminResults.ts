import { computed, ref, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "../api";
import { injectUser, injectCurrencySymbol } from "../injection";
import type { Auction, AuctionImage, AuctionRound, Id, Money } from "../types";

interface ResultsSummary {
    revenue_after_tax: number;
    total_value_after_tax: number;
    revenue_before_tax: number;
    total_value_before_tax: number;
    [key: string]: unknown;
}

interface UserSummaryItem {
    auctionId: Id;
    auctionTitle: string;
    auctionImages?: AuctionImage[];
    wonQuantity: number;
    totalOwed: number;
    bidId: Id | null;
    offerId?: Id;
    isLeftover: boolean;
    fromPriceOffer?: boolean;
}

interface UserSummary {
    username: string;
    userId: Id;
    items: UserSummaryItem[];
    totalItems: number;
    totalOwed: number;
}

export function useAdminResults(props: { active?: boolean }) {
    const router = useRouter();
    const route = useRoute();
    const user = injectUser();
    const currencySymbol = injectCurrencySymbol();
    const allAuctions = ref<Auction[]>([]);
    const allRounds = ref<AuctionRound[]>([]);
    const summary = ref<ResultsSummary | null>(null);
    const loading = ref(true);
    const expanded = ref<Record<number, boolean>>({});
    const view = ref<"users" | "auctions">(route.query.view === "auctions" ? "auctions" : "users");
    const expandedUsers = ref<Record<string, boolean>>({});
    const selectedRoundId = ref<number | null>(
        route.query.round_id ? Number(route.query.round_id) : null,
    );

    const auctions = allAuctions;

    function syncViewQuery(v: string) {
        if (!props.active) {
            return;
        }

        const query = { ...route.query };
        query.view = v;
        router.replace({ path: route.path, query });
    }

    function syncRoundQuery(roundId: number | null) {
        if (!props.active) {
            return;
        }

        const query = { ...route.query };
        if (roundId !== null) {
            query.round_id = String(roundId);
        } else {
            delete query.round_id;
        }
        router.replace({ path: route.path, query });
    }

    watch(view, syncViewQuery, { immediate: true });

    watch(
        () => route.query.view,
        (v) => {
            view.value = v === "auctions" ? "auctions" : "users";
        },
    );

    watch(
        () => props.active,
        (isActive) => {
            if (isActive) {
                syncViewQuery(view.value);
                const qRound = route.query.round_id ? Number(route.query.round_id) : null;
                if (qRound !== selectedRoundId.value) {
                    selectedRoundId.value = qRound;
                }
            }
        },
    );

    if (!user.value?.is_admin) {
        router.push("/");
    }

    async function loadEnded(roundId: number | null = null) {
        const url = roundId !== null ? `/auctions/ended?round_id=${roundId}` : "/auctions/ended";
        const data = await api<{ auctions: Auction[]; summary: ResultsSummary | null }>(url);
        return data;
    }

    let initialized = false;

    onMounted(async () => {
        try {
            const [roundsData, currentData] = await Promise.all([
                api<{ rounds: AuctionRound[] }>("/rounds"),
                api<{ active: AuctionRound | null }>("/rounds/current"),
            ]);
            allRounds.value = (roundsData.rounds ?? []).sort(
                (a: AuctionRound, b: AuctionRound) => b.id - a.id,
            );

            let roundId = selectedRoundId.value;
            if (roundId === null) {
                roundId = currentData?.active?.id ?? null;
                selectedRoundId.value = roundId;
            }
            syncRoundQuery(roundId);
            const data = await loadEnded(roundId);
            allAuctions.value = data.auctions;
            summary.value = data.summary;
        } finally {
            loading.value = false;
            initialized = true;
        }
    });

    watch(selectedRoundId, async (roundId) => {
        if (!initialized) return;
        syncRoundQuery(roundId);
        loading.value = true;
        try {
            const data = await loadEnded(roundId);
            allAuctions.value = data.auctions;
            summary.value = data.summary;
            expanded.value = {};
        } finally {
            loading.value = false;
        }
    });

    function toggle(id: number) {
        expanded.value[id] = !expanded.value[id];
    }

    function winners(auction: Auction) {
        return (auction.bids ?? []).filter((b) => (b.won_quantity ?? 0) > 0);
    }

    function formatDate(d: string | null | undefined) {
        if (!d) return "";
        return d.slice(0, 16).replace("T", " ");
    }

    function quoteUrl(auctionId: Id, bidId: Id) {
        return `/api/auctions/${auctionId}/quotes/${bidId}`;
    }

    function userQuoteUrl(userId: Id) {
        const base = `/api/users/${userId}/quotes`;
        return selectedRoundId.value !== null ? `${base}?round_id=${selectedRoundId.value}` : base;
    }

    function leftoverQuoteUrl(auctionId: Id, purchaseId: Id) {
        return `/api/auctions/${auctionId}/leftover-purchases/${purchaseId}/quotes`;
    }

    function priceOfferQuoteUrl(auctionId: Id, offerId: Id) {
        return `/api/auctions/${auctionId}/leftover-price-offers/${offerId}/quotes`;
    }

    function downloadAllQuotes(auction: Auction) {
        for (const bid of winners(auction)) {
            window.open(quoteUrl(auction.id, bid.id), "_blank");
        }
    }

    function downloadEveryQuote() {
        for (const auction of auctions.value) {
            for (const bid of winners(auction)) {
                window.open(quoteUrl(auction.id, bid.id), "_blank");
            }
        }
    }

    function downloadAllUserQuotes() {
        for (const u of userSummaries.value) {
            window.open(userQuoteUrl(u.userId), "_blank");
        }
    }

    function hasAnyWinners() {
        return auctions.value.some((a) => winners(a).length > 0);
    }

    function formatMoney(amount: Money | null | undefined) {
        return `${currencySymbol.value}${Number(amount ?? 0).toFixed(2)}`;
    }

    function toggleUser(username: string) {
        expandedUsers.value[username] = !expandedUsers.value[username];
    }

    const userSummaries = computed<UserSummary[]>(() => {
        const map = new Map<string, UserSummary>();

        for (const auction of auctions.value) {
            for (const bid of auction.bids ?? []) {
                if ((bid.won_quantity ?? 0) <= 0) continue;
                const username = bid.user!.username;
                if (!map.has(username)) {
                    map.set(username, {
                        username,
                        userId: bid.user!.id,
                        items: [],
                        totalItems: 0,
                        totalOwed: 0,
                    });
                }
                const entry = map.get(username)!;
                const owed = (bid.won_quantity ?? 0) * Number(bid.price ?? bid.amount);
                entry.items.push({
                    auctionId: auction.id,
                    auctionTitle: auction.title,
                    auctionImages: auction.images,
                    wonQuantity: bid.won_quantity ?? 0,
                    totalOwed: owed,
                    bidId: bid.id,
                    isLeftover: false,
                });
                entry.totalItems += bid.won_quantity ?? 0;
                entry.totalOwed += owed;
            }

            for (const purchase of auction.leftover_purchases ?? []) {
                const username = purchase.user!.username;
                if (!map.has(username)) {
                    map.set(username, {
                        username,
                        userId: purchase.user!.id,
                        items: [],
                        totalItems: 0,
                        totalOwed: 0,
                    });
                }
                const entry = map.get(username)!;
                const owed = purchase.quantity * Number(purchase.price_per_item);
                entry.items.push({
                    auctionId: auction.id,
                    auctionTitle: auction.title,
                    auctionImages: auction.images,
                    wonQuantity: purchase.quantity,
                    totalOwed: owed,
                    bidId: null,
                    isLeftover: true,
                    fromPriceOffer: purchase.from_price_offer,
                });
                entry.totalItems += purchase.quantity;
                entry.totalOwed += owed;
            }

            for (const offer of (auction.leftover_price_offers ?? []).filter(
                (o) => o.status === "accepted",
            )) {
                const username = offer.user!.username;
                if (!map.has(username)) {
                    map.set(username, {
                        username,
                        userId: offer.user!.id,
                        items: [],
                        totalItems: 0,
                        totalOwed: 0,
                    });
                }
                const entry = map.get(username)!;
                const owed = offer.quantity * Number(offer.offered_price_per_item);
                entry.items.push({
                    auctionId: auction.id,
                    auctionTitle: auction.title,
                    auctionImages: auction.images,
                    wonQuantity: offer.quantity,
                    totalOwed: owed,
                    bidId: null,
                    offerId: offer.id,
                    isLeftover: true,
                    fromPriceOffer: true,
                });
                entry.totalItems += offer.quantity;
                entry.totalOwed += owed;
            }
        }

        return [...map.values()].sort((a, b) => b.totalOwed - a.totalOwed);
    });

    const auctionsWithSales = computed(() => {
        return auctions.value.filter(
            (a) =>
                winners(a).length > 0 ||
                (a.leftover_purchases?.length ?? 0) > 0 ||
                (a.leftover_price_offers ?? []).some((o) => o.status === "accepted"),
        );
    });

    const statsCards = computed(() => {
        if (!summary.value) {
            return [];
        }

        const afterTaxRatio = summary.value.total_value_after_tax
            ? (summary.value.revenue_after_tax / summary.value.total_value_after_tax) * 100
            : 0;
        const beforeTaxRatio = summary.value.total_value_before_tax
            ? (summary.value.revenue_before_tax / summary.value.total_value_before_tax) * 100
            : 0;

        return [
            {
                label: "Revenue after tax",
                value: `${formatMoney(summary.value.revenue_after_tax)} of ${formatMoney(summary.value.total_value_after_tax)} earned`,
                accent: "text-green-700 dark:text-green-400",
                detail: `${afterTaxRatio.toFixed(1)}% of all auction value`,
                progress: afterTaxRatio,
            },
            {
                label: "Revenue before tax",
                value: `${formatMoney(summary.value.revenue_before_tax)} of ${formatMoney(summary.value.total_value_before_tax)} earned`,
                accent: "text-blue-700 dark:text-blue-400",
                detail: `${beforeTaxRatio.toFixed(1)}% of all auction value`,
                progress: beforeTaxRatio,
            },
        ];
    });

    return {
        user,
        currencySymbol,
        allRounds,
        summary,
        loading,
        expanded,
        view,
        expandedUsers,
        selectedRoundId,
        auctions,
        toggle,
        winners,
        formatDate,
        quoteUrl,
        userQuoteUrl,
        leftoverQuoteUrl,
        priceOfferQuoteUrl,
        downloadAllQuotes,
        downloadEveryQuote,
        downloadAllUserQuotes,
        hasAnyWinners,
        formatMoney,
        toggleUser,
        userSummaries,
        auctionsWithSales,
        statsCards,
    };
}
