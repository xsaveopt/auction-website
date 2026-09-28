import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../lib/api";
import { makeAuction as auction, mountComposable } from "../testing";
import type { ConfirmDialogState } from "../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../lib/api")>()),
    api: apiMock,
}));

import { useAdminAuctionControls } from "./useAdminAuctionControls";

enableAutoUnmount(afterEach);

describe("useAdminAuctionControls", () => {
    beforeEach(() => {
        apiMock.mockReset();
        apiMock.mockResolvedValue({ auction: auction() });
    });

    it("ends, cancels, reactivates and extends the auction", async () => {
        const notify = vi.fn();
        const onUpdate = vi.fn();
        let dialog: ConfirmDialogState | null = null;
        const { result } = mountComposable(
            () =>
                useAdminAuctionControls({
                    auction: () => auction(),
                    onUpdate,
                    confirm: (next) => {
                        dialog = next;
                    },
                }),
            { notify },
        );
        const current = () => dialog;

        result.endAuction();
        expect(current()?.message).toBe("End this auction now?");
        expect(current()?.confirmLabel).toBe("End Auction");
        await current()?.onConfirm();
        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/end", { method: "POST" });
        expect(notify).toHaveBeenCalledWith("Auction ended.", "success");

        result.endAuction(true);
        expect(current()?.confirmLabel).toBe("Cancel Auction");
        apiMock.mockRejectedValueOnce(new Error("x"));
        await current()?.onConfirm();
        expect(result.adminAuctionError.value).toBe("Failed to cancel auction.");
        expect(result.adminAuctionSaving.value).toBe(false);

        await result.reactivateAuction();
        expect(result.adminAuctionError.value).toBe("Set a new end time first.");
        await result.extendAuction();
        expect(result.adminAuctionError.value).toBe("Set a new end time first.");

        result.newEndsAt.value = "2026-02-01T10:00";
        await result.reactivateAuction();
        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/reactivate", {
            method: "POST",
            body: JSON.stringify({ ends_at: "2026-02-01T10:00" }),
        });
        expect(notify).toHaveBeenCalledWith("Auction reactivated.", "success");
        expect(result.newEndsAt.value).toBe("");
        expect(result.adminAuctionError.value).toBe("");

        result.newEndsAt.value = "2026-03-01T10:00";
        apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { ends_at: ["Must be later."] } }),
        );
        await result.extendAuction();
        expect(result.adminAuctionError.value).toBe("Must be later.");
        expect(result.adminAuctionSaving.value).toBe(false);

        await result.extendAuction();
        expect(apiMock).toHaveBeenLastCalledWith("/admin/auctions/7/extend", {
            method: "POST",
            body: JSON.stringify({ ends_at: "2026-03-01T10:00" }),
        });
        expect(notify).toHaveBeenCalledWith("Auction extended.", "success");

        result.newEndsAt.value = "2026-04-01T10:00";
        apiMock.mockRejectedValueOnce(new Error("offline"));
        await result.reactivateAuction();
        expect(result.adminAuctionError.value).toBe("Failed to reactivate.");
        expect(onUpdate).toHaveBeenCalledTimes(3);
    });
});
