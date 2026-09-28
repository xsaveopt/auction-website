import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../lib/api";
import { makeAuction as auction, mountComposable } from "../testing";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../lib/api")>()),
    api: apiMock,
}));

import { overrideQuoteUrl, useAdminOverrideSaleForm } from "./useAdminOverrideSales";

enableAutoUnmount(afterEach);

describe("useAdminOverrideSaleForm", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("prefills the leftover price and posts an override sale", async () => {
        const notify = vi.fn();
        const onUpdate = vi.fn();
        const { result } = mountComposable(
            () =>
                useAdminOverrideSaleForm({
                    auction: () => auction({ leftover_price: "7.50" }),
                    onUpdate,
                }),
            { notify },
        );
        apiMock.mockResolvedValueOnce({ auction: auction() });

        expect(result.toggleForm()).toBe(true);
        expect(result.pricePerItem.value).toBe("7.50");
        result.username.value = "bob";
        result.quantity.value = 2;
        result.pricePerItem.value = "5.25";
        await result.submit();

        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/override-sales", {
            method: "POST",
            body: JSON.stringify({ username: "bob", quantity: 2, price_per_item: "5.25" }),
        });
        expect(onUpdate).toHaveBeenCalled();
        expect(notify).toHaveBeenCalledWith("Override sale added.", "success");
        expect(result.showForm.value).toBe(false);
        expect(result.username.value).toBe("");
        expect(result.quantity.value).toBe(1);

        apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { price_per_item: ["Invalid price."] } }),
        );
        await result.submit();
        expect(result.error.value).toBe("Invalid price.");
        expect(result.saving.value).toBe(false);

        apiMock.mockRejectedValueOnce(new Error("offline"));
        await result.submit();
        expect(result.error.value).toBe("Failed to add override sale.");

        result.toggleForm();
        expect(result.error.value).toBe("");
    });

    it("builds the quote url for a single override sale", () => {
        expect(overrideQuoteUrl(7, 3)).toBe("/api/auctions/7/leftover-purchases/3/quotes");
    });
});
