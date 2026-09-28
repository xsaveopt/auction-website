import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../lib/api";
import { alice, makeAuction as auction, mountComposable } from "../testing";
import type { ConfirmDialogState, LeftoverPriceOffer } from "../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../lib/api")>()),
    api: apiMock,
}));

import { useAdminOfferActions, useAdminOfferForm } from "./useAdminPriceOffers";

const offer: LeftoverPriceOffer = {
    id: 3,
    auction_id: 7,
    user_id: 10,
    quantity: 2,
    offered_price_per_item: "6",
    status: "pending",
    user: alice,
};

enableAutoUnmount(afterEach);

describe("useAdminOfferForm", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    function mountForm() {
        const notify = vi.fn();
        const onUpdate = vi.fn();
        const { result } = mountComposable(
            () =>
                useAdminOfferForm({
                    auction: () => auction({ leftover_price: "5.00" }),
                    onUpdate,
                }),
            { notify },
        );
        return { result, notify, onUpdate };
    }

    it("adds an offer and resets the form", async () => {
        const { result, notify, onUpdate } = mountForm();
        apiMock.mockResolvedValueOnce({ auction: auction() });

        expect(result.priceOfferLimit.value).toBe("4.99");
        expect(result.toggleAdminOfferForm()).toBe(true);
        result.adminOfferUsername.value = "alice";
        result.adminOfferQuantity.value = 2;
        result.adminOfferPrice.value = "3.5";
        await result.submitAdminOffer();

        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/leftover-price-offers", {
            method: "POST",
            body: JSON.stringify({ username: "alice", quantity: 2, offered_price_per_item: 3.5 }),
        });
        expect(onUpdate).toHaveBeenCalled();
        expect(notify).toHaveBeenCalledWith("Offer added.", "success");
        expect(result.showAdminOfferForm.value).toBe(false);
        expect(result.adminOfferUsername.value).toBe("");
        expect(result.adminOfferQuantity.value).toBe(1);
        expect(result.adminOfferPrice.value).toBe("");
        expect(result.adminOfferSaving.value).toBe(false);
    });

    it("surfaces errors when adding an offer fails", async () => {
        const { result } = mountForm();

        apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { username: ["Unknown user."] } }),
        );
        await result.submitAdminOffer();
        expect(result.adminOfferError.value).toBe("Unknown user.");
        expect(result.adminOfferSaving.value).toBe(false);

        apiMock.mockRejectedValueOnce(new Error("offline"));
        await result.submitAdminOffer();
        expect(result.adminOfferError.value).toBe("Failed to add offer.");

        result.toggleAdminOfferForm();
        expect(result.adminOfferError.value).toBe("");
    });
});

describe("useAdminOfferActions", () => {
    beforeEach(() => {
        apiMock.mockReset();
        apiMock.mockResolvedValue({ auction: auction() });
    });

    it("accepts, rejects and deletes offers through the confirm dialog", async () => {
        const notify = vi.fn();
        const onUpdate = vi.fn();
        let dialog: ConfirmDialogState | null = null;
        const { result } = mountComposable(
            () =>
                useAdminOfferActions({
                    onUpdate,
                    confirm: (next) => {
                        dialog = next;
                    },
                }),
            { notify, currencySymbol: ref("€") },
        );
        const current = () => dialog;

        result.acceptPriceOffer(offer);
        expect(current()?.message).toBe("Accept offer of €6.00 × 2 from alice?");
        expect(current()?.danger).toBe(false);
        await current()?.onConfirm();
        expect(apiMock).toHaveBeenCalledWith("/admin/leftover-price-offers/3/accept", {
            method: "POST",
        });
        expect(notify).toHaveBeenCalledWith("Offer accepted.", "success");

        result.rejectPriceOffer(offer);
        expect(current()?.message).toBe("Reject offer from alice?");
        await current()?.onConfirm();
        expect(apiMock).toHaveBeenCalledWith("/admin/leftover-price-offers/3/reject", {
            method: "POST",
        });
        expect(notify).toHaveBeenCalledWith("Offer rejected.", "success");

        result.deletePriceOffer(offer);
        expect(current()?.message).toBe("Delete offer from alice?");
        apiMock.mockRejectedValueOnce(new Error("x"));
        await current()?.onConfirm();
        expect(notify).toHaveBeenCalledWith("Failed to delete offer.", "error");

        result.acceptPriceOffer(offer);
        apiMock.mockRejectedValueOnce(new ApiError(409, { message: "Already sold." }));
        await current()?.onConfirm();
        expect(notify).toHaveBeenCalledWith("Already sold.", "error");
        expect(onUpdate).toHaveBeenCalledTimes(2);
    });
});
