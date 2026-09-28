import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { nextTick, ref } from "vue";
import { enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../lib/api";
import { makeAuction as auction, mountComposable } from "../testing";
import type { Auction } from "../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../lib/api")>()),
    api: apiMock,
}));

import { useLeftoverPurchase } from "./useLeftoverPurchase";

function mountPurchase(initial: Auction) {
    const current = ref(initial);
    const notify = vi.fn();
    const onUpdate = vi.fn((next: Auction) => {
        current.value = next;
    });
    const { result } = mountComposable(
        () => useLeftoverPurchase({ auction: () => current.value, onUpdate }),
        { notify },
    );
    return { result, current, notify, onUpdate };
}

enableAutoUnmount(afterEach);

const ended = auction({
    is_active: false,
    leftover_enabled: true,
    leftover_quantity: 3,
    leftover_price: "15.00",
});

describe("useLeftoverPurchase", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("computes the buy now total", () => {
        const { result, current } = mountPurchase(ended);

        result.leftoverQuantity.value = 2;
        expect(result.leftoverBuyTotal.value).toBe(30);

        current.value = { ...ended, leftover_quantity: 1 };
        expect(result.leftoverBuyTotal.value).toBe(15);
    });

    it("buys leftovers, clamps to the remaining stock and reports failures", async () => {
        const { result, notify, onUpdate } = mountPurchase(ended);

        result.leftoverQuantity.value = 2;
        apiMock.mockResolvedValueOnce({ auction: { ...ended, leftover_quantity: 1 } });
        await result.buyLeftover();

        expect(apiMock).toHaveBeenCalledWith("/auctions/7/leftover-purchases", {
            method: "POST",
            body: JSON.stringify({ quantity: 2 }),
        });
        expect(onUpdate).toHaveBeenCalledWith({ ...ended, leftover_quantity: 1 });
        expect(notify).toHaveBeenCalledWith("Purchase successful!", "success");
        expect(result.buyingLeftover.value).toBe(false);
        await nextTick();
        expect(result.leftoverQuantity.value).toBe(1);

        apiMock.mockRejectedValueOnce(new ApiError(422, { errors: { quantity: ["Too many."] } }));
        await result.buyLeftover();
        expect(result.leftoverError.value).toBe("Too many.");

        apiMock.mockRejectedValueOnce(new Error("offline"));
        await result.buyLeftover();
        expect(result.leftoverError.value).toBe("Purchase failed.");
    });
});
