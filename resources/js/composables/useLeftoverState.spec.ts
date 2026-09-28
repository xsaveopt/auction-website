import { describe, it, expect, afterEach } from "vitest";
import { ref } from "vue";
import { enableAutoUnmount } from "@vue/test-utils";
import { alice, bob, makeAuction as auction, mountComposable, seller } from "../testing";
import type { Auction, User } from "../lib/types";
import { useLeftoverState } from "./useLeftoverState";

function mountState(initial: Auction | null, user: User | null = alice) {
    const current = ref<Auction | null>(initial);
    const { result } = mountComposable(() => useLeftoverState(() => current.value), {
        user: ref(user),
    });
    return { result, current };
}

enableAutoUnmount(afterEach);

const offers = [
    {
        id: 3,
        auction_id: 7,
        user_id: 10,
        quantity: 1,
        offered_price_per_item: "6.00",
        status: "pending",
        rebid_requested_at: "2026-01-01T00:00:00Z",
        user: alice,
    },
    {
        id: 4,
        auction_id: 7,
        user_id: 11,
        quantity: 1,
        offered_price_per_item: "5.00",
        status: "rejected",
        user: bob,
    },
];

describe("useLeftoverState", () => {
    it("finds the user's offer and summarises all offers", () => {
        const { result } = mountState(
            auction({
                is_active: false,
                leftover_enabled: true,
                leftover_quantity: 1,
                leftover_price: "10.00",
                leftover_price_offers: offers,
            }),
        );

        expect(result.myPriceOffer.value?.id).toBe(3);
        expect(result.pendingOffers.value.map((o) => o.id)).toEqual([3]);
        expect(result.allOffers.value).toHaveLength(2);
    });

    it("finds the user's purchase", () => {
        const { result } = mountState(
            auction({
                leftover_purchases: [{ id: 9, quantity: 1, price_per_item: "7.50", user: alice }],
            }),
            alice,
        );

        expect(result.myLeftoverPurchase.value?.id).toBe(9);
    });

    it("is empty without an auction or a user", () => {
        const { result } = mountState(null, null);

        expect(result.myLeftoverPurchase.value).toBeNull();
        expect(result.myPriceOffer.value).toBeNull();
        expect(result.pendingOffers.value).toEqual([]);
        expect(result.allOffers.value).toEqual([]);
        expect(result.leftoverSold.value).toBe(0);
        expect(result.hasLeftoversAvailable.value).toBe(false);
    });

    it("keeps leftovers available to admins after the round closes", () => {
        const closed = auction({
            leftover_enabled: true,
            leftover_quantity: 2,
            round: { id: 1, name: "R", status: "ended" },
        });

        expect(mountState(closed, seller).result.effectiveLeftoverAvailable.value).toBe(true);
        expect(mountState(closed, bob).result.effectiveLeftoverAvailable.value).toBe(false);
    });
});
