import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../lib/api";
import { alice, makeAuction as auction, mountComposable } from "../testing";
import type { ConfirmDialogState } from "../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../lib/api")>()),
    api: apiMock,
}));

import { useAdminBids } from "./useAdminBids";

function mountAdminBids() {
    const notify = vi.fn();
    const onUpdate = vi.fn();
    let dialog: ConfirmDialogState | null = null;
    const { result } = mountComposable(
        () =>
            useAdminBids({
                auction: () => auction(),
                onUpdate,
                confirm: (next) => {
                    dialog = next;
                },
            }),
        { notify },
    );
    return { result, notify, onUpdate, dialog: () => dialog };
}

enableAutoUnmount(afterEach);

describe("useAdminBids", () => {
    beforeEach(() => {
        apiMock.mockReset();
        apiMock.mockResolvedValue({ auction: auction() });
    });

    it("edits a bid inline", async () => {
        const { result, onUpdate, notify } = mountAdminBids();
        const bid = { id: 50, amount: "12", quantity: 2, user: alice };

        result.startEditBid(bid);
        expect(result.editingBidId.value).toBe(50);
        expect(result.editBidAmount.value).toBe("12.00");
        expect(result.editBidQuantity.value).toBe(2);
        result.cancelEditBid();
        expect(result.editingBidId.value).toBeNull();

        result.startEditBid(bid);
        result.editBidAmount.value = "14";
        await result.saveBid(bid);
        expect(apiMock).toHaveBeenCalledWith("/admin/bids/50", {
            method: "PUT",
            body: JSON.stringify({ amount: 14, quantity: 2 }),
        });
        expect(onUpdate).toHaveBeenCalled();
        expect(notify).toHaveBeenCalledWith("Bid updated.", "success");
        expect(result.editingBidId.value).toBeNull();

        apiMock.mockRejectedValueOnce(new Error("offline"));
        await result.saveBid(bid);
        expect(result.adminBidError.value).toBe("Failed to update bid.");
        expect(result.adminBidSaving.value).toBe(false);
    });

    it("adds a bid and resets the form", async () => {
        const { result } = mountAdminBids();

        expect(result.toggleAddBid()).toBe(true);
        result.addBidUsername.value = "bob";
        result.addBidAmount.value = "11";
        result.addBidQuantity.value = 1;
        await result.submitAddBid();

        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/bids", {
            method: "POST",
            body: JSON.stringify({ username: "bob", amount: 11, quantity: 1 }),
        });
        expect(result.showAddBid.value).toBe(false);
        expect(result.addBidUsername.value).toBe("");
        expect(result.addBidAmount.value).toBe("");

        apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { username: ["Unknown user."] } }),
        );
        await result.submitAddBid();
        expect(result.adminBidError.value).toBe("Unknown user.");
    });

    it("deletes a bid through the confirm dialog", async () => {
        const { result, dialog, notify } = mountAdminBids();
        const bid = { id: 50, amount: "12", quantity: 2, user: alice };

        result.deleteBid(bid);
        expect(dialog()?.message).toBe("Delete bid by alice?");
        await dialog()?.onConfirm();
        expect(apiMock).toHaveBeenCalledWith("/admin/bids/50", { method: "DELETE" });
        expect(notify).toHaveBeenCalledWith("Bid deleted.", "success");

        result.deleteBid(bid);
        apiMock.mockRejectedValueOnce(new ApiError(403, { message: "Denied" }));
        await dialog()?.onConfirm();
        expect(result.adminBidError.value).toBe("Denied");
    });
});
