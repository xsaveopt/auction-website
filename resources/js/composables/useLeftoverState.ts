import { computed } from "vue";
import { getLeftoverDiscountPercent, hasAvailableLeftovers } from "../lib/auctionPresentation";
import { injectUser } from "../lib/injection";
import type { Auction, LeftoverPriceOffer, LeftoverPurchase } from "../lib/types";

export function useLeftoverState(source: () => Auction | null) {
    const user = injectUser();
    const auction = computed(source);

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

    const roundIsClosed = computed(() => auction.value?.round?.status === "ended");

    const effectiveLeftoverAvailable = computed(
        () => hasLeftoversAvailable.value && !(roundIsClosed.value && !user.value?.is_admin),
    );

    return {
        myLeftoverPurchase,
        myPriceOffer,
        pendingOffers,
        allOffers,
        hasLeftoversAvailable,
        leftoverSold,
        leftoverDiscountPercent,
        roundIsClosed,
        effectiveLeftoverAvailable,
    };
}
