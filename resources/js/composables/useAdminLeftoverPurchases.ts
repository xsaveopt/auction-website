import { ref } from "vue";
import { api } from "../lib/api";
import { apiError } from "../lib/apiError";
import { injectNotifyOptional } from "../lib/injection";
import type { Auction, AuctionActionOptions, LeftoverPurchase } from "../lib/types";

export function useAdminPurchaseForm(options: Omit<AuctionActionOptions, "confirm">) {
    const notify = injectNotifyOptional();
    const showAddPurchase = ref(false);
    const addPurchaseUsername = ref("");
    const addPurchaseQuantity = ref(1);
    const adminPurchaseError = ref("");
    const adminPurchaseSaving = ref(false);

    function toggleAddPurchase(): boolean {
        showAddPurchase.value = !showAddPurchase.value;
        adminPurchaseError.value = "";
        return showAddPurchase.value;
    }

    async function submitAddPurchase() {
        adminPurchaseError.value = "";
        adminPurchaseSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(
                `/admin/auctions/${options.auction().id}/leftover-purchases`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        username: addPurchaseUsername.value,
                        quantity: Number(addPurchaseQuantity.value),
                    }),
                },
            );
            options.onUpdate(data.auction);
            showAddPurchase.value = false;
            addPurchaseUsername.value = "";
            addPurchaseQuantity.value = 1;
            notify?.("Purchase added.", "success");
        } catch (e) {
            adminPurchaseError.value =
                apiError(e, "username", "quantity") || "Failed to add purchase.";
        } finally {
            adminPurchaseSaving.value = false;
        }
    }

    return {
        showAddPurchase,
        addPurchaseUsername,
        addPurchaseQuantity,
        adminPurchaseError,
        adminPurchaseSaving,
        toggleAddPurchase,
        submitAddPurchase,
    };
}

export function useAdminPurchaseActions(options: Omit<AuctionActionOptions, "auction">) {
    const notify = injectNotifyOptional();

    function deleteLeftoverPurchase(purchase: LeftoverPurchase) {
        options.confirm({
            message: `Delete purchase by ${purchase.user?.username}?`,
            confirmLabel: "Delete",
            danger: true,
            onConfirm: async () => {
                try {
                    const data = await api<{ auction: Auction }>(
                        `/admin/leftover-purchases/${purchase.id}`,
                        { method: "DELETE" },
                    );
                    options.onUpdate(data.auction);
                    notify?.("Purchase deleted.", "success");
                } catch {
                    notify?.("Failed to delete purchase.", "error");
                }
            },
        });
    }

    return { deleteLeftoverPurchase };
}
