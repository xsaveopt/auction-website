import { ref } from "vue";
import { api } from "../lib/api";
import { apiError } from "../lib/apiError";
import { injectNotifyOptional } from "../lib/injection";
import type { Auction, AuctionActionOptions } from "../lib/types";

export function useAdminAuctionControls(options: AuctionActionOptions) {
    const notify = injectNotifyOptional();
    const newEndsAt = ref("");
    const adminAuctionError = ref("");
    const adminAuctionSaving = ref(false);

    function endAuction(cancel = false) {
        const label = cancel ? "cancel" : "end";
        options.confirm({
            message: `${cancel ? "Cancel" : "End"} this auction now?`,
            confirmLabel: cancel ? "Cancel Auction" : "End Auction",
            danger: true,
            onConfirm: async () => {
                adminAuctionError.value = "";
                adminAuctionSaving.value = true;
                try {
                    const data = await api<{ auction: Auction }>(
                        `/admin/auctions/${options.auction().id}/${label}`,
                        { method: "POST" },
                    );
                    options.onUpdate(data.auction);
                    notify?.(`Auction ${cancel ? "cancelled" : "ended"}.`, "success");
                } catch (e) {
                    adminAuctionError.value = apiError(e) || `Failed to ${label} auction.`;
                } finally {
                    adminAuctionSaving.value = false;
                }
            },
        });
    }

    async function reschedule(action: "reactivate" | "extend", success: string, failure: string) {
        if (!newEndsAt.value) {
            adminAuctionError.value = "Set a new end time first.";
            return;
        }
        adminAuctionError.value = "";
        adminAuctionSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(
                `/admin/auctions/${options.auction().id}/${action}`,
                {
                    method: "POST",
                    body: JSON.stringify({ ends_at: newEndsAt.value }),
                },
            );
            options.onUpdate(data.auction);
            newEndsAt.value = "";
            notify?.(success, "success");
        } catch (e) {
            adminAuctionError.value = apiError(e, "ends_at") || failure;
        } finally {
            adminAuctionSaving.value = false;
        }
    }

    function reactivateAuction() {
        return reschedule("reactivate", "Auction reactivated.", "Failed to reactivate.");
    }

    function extendAuction() {
        return reschedule("extend", "Auction extended.", "Failed to extend.");
    }

    return {
        newEndsAt,
        adminAuctionError,
        adminAuctionSaving,
        endAuction,
        reactivateAuction,
        extendAuction,
    };
}
