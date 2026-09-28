import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { nextTick, ref } from "vue";
import { enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../lib/api";
import { alice, bob, makeAuction as auction, mountComposable } from "../testing";
import type { Auction, User } from "../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../lib/api")>()),
    api: apiMock,
}));

import { clampBidQuantity, useBidForm } from "./useBidForm";

function mountBidForm(initial: Auction, options: { user?: User | null } = {}) {
    const current = ref(initial);
    const error = ref("");
    const notify = vi.fn();
    const reload = vi.fn<() => Promise<Auction | null>>().mockResolvedValue(null);
    const { result } = mountComposable(
        () => useBidForm({ auction: () => current.value, reload, error }),
        {
            user: ref(options.user === undefined ? alice : options.user),
            notify,
        },
    );
    return { result, current, error, notify, reload };
}

enableAutoUnmount(afterEach);

describe("clampBidQuantity", () => {
    it("keeps the quantity between one and the per-bidder maximum", () => {
        expect(clampBidQuantity(9, 2)).toBe(2);
        expect(clampBidQuantity(0, 2)).toBe(1);
        expect(clampBidQuantity("1.7", 3)).toBe(1);
        expect(clampBidQuantity(3, null)).toBe(1);
    });
});

describe("useBidForm", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("seeds the form from the starting price", () => {
        const { result } = mountBidForm(auction());

        expect(result.bidAmount.value).toBe("10.00");
        expect(result.bidQuantity.value).toBe(1);
        expect(result.myBid.value).toBeNull();
        expect(result.allowsMultiple.value).toBe(true);
    });

    it("seeds the form from the user's existing bid", () => {
        const { result } = mountBidForm(
            auction({ bids: [{ id: 1, amount: "20.00", quantity: 5, user: alice }] }),
        );

        expect(result.myBid.value?.id).toBe(1);
        expect(result.bidAmount.value).toBe("21.00");
        expect(result.bidQuantity.value).toBe(2);
    });

    it("clamps the quantity when the auction changes without reseeding", async () => {
        const { result, current } = mountBidForm(auction({ max_per_bidder: 5 }));
        result.bidAmount.value = "40.00";
        result.bidQuantity.value = 4;

        current.value = auction({
            max_per_bidder: 3,
            bids: [{ id: 1, amount: "20.00", quantity: 1, user: alice }],
        });
        await nextTick();

        expect(result.bidQuantity.value).toBe(3);
        expect(result.bidAmount.value).toBe("40.00");
    });

    it("places a clamped bid, reloads and reseeds from the reloaded auction", async () => {
        const { result, notify, reload, error } = mountBidForm(auction());
        reload.mockResolvedValueOnce(
            auction({ bids: [{ id: 2, amount: "12.50", quantity: 2, user: alice }] }),
        );
        apiMock.mockResolvedValueOnce({});

        result.bidAmount.value = "12.50";
        result.bidQuantity.value = 9;
        await result.placeBid();

        expect(apiMock).toHaveBeenCalledWith("/auctions/7/bids", {
            method: "POST",
            body: JSON.stringify({ amount: 12.5, quantity: 2 }),
        });
        expect(reload).toHaveBeenCalledTimes(1);
        expect(notify).toHaveBeenCalledWith("Bid placed successfully!", "success");
        expect(error.value).toBe("");
        expect(result.bidAmount.value).toBe("13.50");
        expect(result.bidQuantity.value).toBe(2);
    });

    it("keeps the form when the reload is discarded", async () => {
        const { result, reload } = mountBidForm(auction({ max_per_bidder: 1 }));
        apiMock.mockResolvedValueOnce({});
        result.bidAmount.value = "15.00";
        result.bidQuantity.value = 3;

        await result.placeBid();

        expect(reload).toHaveBeenCalled();
        expect(result.bidAmount.value).toBe("15.00");
        expect(result.bidQuantity.value).toBe(1);
    });

    it("surfaces validation errors when bidding fails", async () => {
        const { result, error } = mountBidForm(auction());

        apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { amount: ["Amount too low."] } }),
        );
        await result.placeBid();
        expect(error.value).toBe("Amount too low.");

        apiMock.mockRejectedValueOnce(new Error("offline"));
        await result.placeBid();
        expect(error.value).toBe("Failed to place bid.");
    });

    it("computes the maximum total for the selected quantity", () => {
        const { result } = mountBidForm(auction(), { user: bob });

        result.bidAmount.value = "2.5";
        result.bidQuantity.value = 2;
        expect(result.selectedBidTotal.value).toBe(5);
    });
});
