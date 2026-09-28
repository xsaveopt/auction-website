import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { nextTick, ref } from "vue";
import { enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../lib/api";
import { alice, makeAuction as auction, mountComposable } from "../testing";
import type { Auction, LeftoverPriceOffer } from "../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../lib/api")>()),
    api: apiMock,
}));

import { usePriceOffer } from "./usePriceOffer";

const pendingRebid: LeftoverPriceOffer = {
    id: 3,
    auction_id: 7,
    user_id: 10,
    quantity: 1,
    offered_price_per_item: "6.00",
    status: "pending",
    rebid_requested_at: "2026-01-01T00:00:00Z",
    user: alice,
};

function mountOffer(initial: Auction, offer: LeftoverPriceOffer | null = null) {
    const current = ref(initial);
    const currentOffer = ref<LeftoverPriceOffer | null>(offer);
    const notify = vi.fn();
    const onUpdate = vi.fn();
    const { result } = mountComposable(
        () =>
            usePriceOffer({
                auction: () => current.value,
                offer: () => currentOffer.value,
                onUpdate,
            }),
        { notify },
    );
    return { result, current, currentOffer, notify, onUpdate };
}

enableAutoUnmount(afterEach);

const ended = auction({
    is_active: false,
    leftover_enabled: true,
    leftover_quantity: 3,
    leftover_price: "15.00",
});

describe("usePriceOffer", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("computes the offer limit and total", () => {
        const { result } = mountOffer(ended);

        expect(result.priceOfferLimit.value).toBe("14.99");
        result.offerQuantity.value = 2;
        result.offerPrice.value = "4.5";
        expect(result.offerTotal.value).toBe(9);
    });

    it("tracks the rebid state of the user's offer", () => {
        const { result, currentOffer } = mountOffer(ended, pendingRebid);

        expect(result.needsRebid.value).toBe(true);
        expect(result.rebidMinPrice.value).toBe("6.01");

        currentOffer.value = { ...pendingRebid, rebid_requested_at: null };
        expect(result.needsRebid.value).toBe(false);
    });

    it("never offers a price limit below one cent", () => {
        const { result } = mountOffer({ ...ended, leftover_price: "0.01" });

        expect(result.priceOfferLimit.value).toBe("0.01");
        expect(result.rebidMinPrice.value).toBe("0.01");
    });

    it("toggles the form and clears the previous error", () => {
        const { result } = mountOffer(ended);
        result.offerError.value = "Old";

        result.toggleOfferForm();
        expect(result.showOfferForm.value).toBe(true);
        expect(result.offerError.value).toBe("");
    });

    it("clamps the quantity to the remaining stock", async () => {
        const { result, current } = mountOffer(ended);
        result.offerQuantity.value = 3;

        current.value = { ...ended, leftover_quantity: 2 };
        await nextTick();

        expect(result.offerQuantity.value).toBe(2);
    });

    it("submits a price offer", async () => {
        const { result, notify, onUpdate } = mountOffer(ended);
        apiMock.mockResolvedValueOnce({ auction: ended });

        result.showOfferForm.value = true;
        result.offerQuantity.value = 2;
        result.offerPrice.value = "4.00";
        await result.submitPriceOffer();

        expect(apiMock).toHaveBeenCalledWith("/auctions/7/leftover-price-offers", {
            method: "POST",
            body: JSON.stringify({ quantity: 2, offered_price_per_item: 4 }),
        });
        expect(onUpdate).toHaveBeenCalledWith(ended);
        expect(notify).toHaveBeenCalledWith("Offer submitted!", "success");
        expect(result.showOfferForm.value).toBe(false);

        apiMock.mockRejectedValueOnce(new ApiError(422, { message: "Closed" }));
        await result.submitPriceOffer();
        expect(result.offerError.value).toBe("Closed");
        expect(result.submittingOffer.value).toBe(false);

        apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { offered_price_per_item: ["Too high."] } }),
        );
        await result.submitPriceOffer();
        expect(result.offerError.value).toBe("Too high.");
    });
});
