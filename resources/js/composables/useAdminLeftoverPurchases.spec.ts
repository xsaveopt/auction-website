import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../lib/api";
import { bob, makeAuction as auction, mountComposable } from "../testing";
import type { ConfirmDialogState } from "../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../lib/api")>()),
    api: apiMock,
}));

import { useAdminPurchaseActions, useAdminPurchaseForm } from "./useAdminLeftoverPurchases";

enableAutoUnmount(afterEach);

describe("useAdminPurchaseForm", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("adds a purchase and resets the form", async () => {
        const notify = vi.fn();
        const onUpdate = vi.fn();
        const { result } = mountComposable(
            () => useAdminPurchaseForm({ auction: () => auction(), onUpdate }),
            { notify },
        );
        apiMock.mockResolvedValueOnce({ auction: auction() });

        expect(result.toggleAddPurchase()).toBe(true);
        result.addPurchaseUsername.value = "bob";
        result.addPurchaseQuantity.value = 2;
        await result.submitAddPurchase();

        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/leftover-purchases", {
            method: "POST",
            body: JSON.stringify({ username: "bob", quantity: 2 }),
        });
        expect(onUpdate).toHaveBeenCalled();
        expect(notify).toHaveBeenCalledWith("Purchase added.", "success");
        expect(result.showAddPurchase.value).toBe(false);
        expect(result.addPurchaseUsername.value).toBe("");
        expect(result.addPurchaseQuantity.value).toBe(1);

        apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { quantity: ["Only 1 left."] } }),
        );
        await result.submitAddPurchase();
        expect(result.adminPurchaseError.value).toBe("Only 1 left.");
        expect(result.adminPurchaseSaving.value).toBe(false);

        apiMock.mockRejectedValueOnce(new Error("offline"));
        await result.submitAddPurchase();
        expect(result.adminPurchaseError.value).toBe("Failed to add purchase.");

        result.toggleAddPurchase();
        expect(result.adminPurchaseError.value).toBe("");
    });
});

describe("useAdminPurchaseActions", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("deletes a purchase through the confirm dialog", async () => {
        const notify = vi.fn();
        const onUpdate = vi.fn();
        let dialog: ConfirmDialogState | null = null;
        const { result } = mountComposable(
            () =>
                useAdminPurchaseActions({
                    onUpdate,
                    confirm: (next) => {
                        dialog = next;
                    },
                }),
            { notify },
        );
        const current = () => dialog;
        const purchase = { id: 8, quantity: 1, price_per_item: "5", user: bob };

        result.deleteLeftoverPurchase(purchase);
        expect(current()?.message).toBe("Delete purchase by bob?");
        apiMock.mockResolvedValueOnce({ auction: auction() });
        await current()?.onConfirm();
        expect(apiMock).toHaveBeenCalledWith("/admin/leftover-purchases/8", { method: "DELETE" });
        expect(notify).toHaveBeenCalledWith("Purchase deleted.", "success");
        expect(onUpdate).toHaveBeenCalled();

        apiMock.mockRejectedValueOnce(new Error("x"));
        await current()?.onConfirm();
        expect(notify).toHaveBeenCalledWith("Failed to delete purchase.", "error");
    });
});
