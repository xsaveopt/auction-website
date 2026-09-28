import { ref } from "vue";
import { api } from "../lib/api";
import { apiError } from "../lib/apiError";
import { injectNotifyOptional } from "../lib/injection";
import type { Auction, AuctionActionOptions, Bid } from "../lib/types";

export function useAdminBids(options: AuctionActionOptions) {
    const notify = injectNotifyOptional();
    const editingBidId = ref<number | null>(null);
    const editBidAmount = ref("");
    const editBidQuantity = ref(1);
    const showAddBid = ref(false);
    const addBidUsername = ref("");
    const addBidAmount = ref("");
    const addBidQuantity = ref(1);
    const adminBidError = ref("");
    const adminBidSaving = ref(false);

    function toggleAddBid(): boolean {
        showAddBid.value = !showAddBid.value;
        return showAddBid.value;
    }

    function startEditBid(bid: Bid) {
        editingBidId.value = bid.id;
        editBidAmount.value = String(Number(bid.amount).toFixed(2));
        editBidQuantity.value = bid.quantity;
        adminBidError.value = "";
    }

    function cancelEditBid() {
        editingBidId.value = null;
        adminBidError.value = "";
    }

    async function saveBid(bid: Bid) {
        adminBidError.value = "";
        adminBidSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(`/admin/bids/${bid.id}`, {
                method: "PUT",
                body: JSON.stringify({
                    amount: Number(editBidAmount.value),
                    quantity: Number(editBidQuantity.value),
                }),
            });
            options.onUpdate(data.auction);
            editingBidId.value = null;
            notify?.("Bid updated.", "success");
        } catch (e) {
            adminBidError.value = apiError(e) || "Failed to update bid.";
        } finally {
            adminBidSaving.value = false;
        }
    }

    function deleteBid(bid: Bid) {
        options.confirm({
            message: `Delete bid by ${bid.user?.username}?`,
            confirmLabel: "Delete",
            danger: true,
            onConfirm: async () => {
                adminBidError.value = "";
                try {
                    const data = await api<{ auction: Auction }>(`/admin/bids/${bid.id}`, {
                        method: "DELETE",
                    });
                    options.onUpdate(data.auction);
                    notify?.("Bid deleted.", "success");
                } catch (e) {
                    adminBidError.value = apiError(e) || "Failed to delete bid.";
                }
            },
        });
    }

    async function submitAddBid() {
        adminBidError.value = "";
        adminBidSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(
                `/admin/auctions/${options.auction().id}/bids`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        username: addBidUsername.value,
                        amount: Number(addBidAmount.value),
                        quantity: Number(addBidQuantity.value),
                    }),
                },
            );
            options.onUpdate(data.auction);
            showAddBid.value = false;
            addBidUsername.value = "";
            addBidAmount.value = "";
            addBidQuantity.value = 1;
            notify?.("Bid added.", "success");
        } catch (e) {
            adminBidError.value = apiError(e, "username", "amount") || "Failed to add bid.";
        } finally {
            adminBidSaving.value = false;
        }
    }

    return {
        editingBidId,
        editBidAmount,
        editBidQuantity,
        showAddBid,
        addBidUsername,
        addBidAmount,
        addBidQuantity,
        adminBidError,
        adminBidSaving,
        toggleAddBid,
        startEditBid,
        cancelEditBid,
        saveBid,
        deleteBid,
        submitAddBid,
    };
}
