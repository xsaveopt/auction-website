import { ref } from "vue";
import { api } from "../lib/api";
import { apiError } from "../lib/apiError";
import { injectNotifyOptional } from "../lib/injection";
import type { Auction, AuctionActionOptions, Id } from "../lib/types";

export function overrideQuoteUrl(auctionId: Id, purchaseId: Id) {
    return `/api/auctions/${auctionId}/leftover-purchases/${purchaseId}/quotes`;
}

export function useAdminOverrideSaleForm(options: Omit<AuctionActionOptions, "confirm">) {
    const notify = injectNotifyOptional();
    const showForm = ref(false);
    const username = ref("");
    const quantity = ref(1);
    const pricePerItem = ref("");
    const error = ref("");
    const saving = ref(false);

    function toggleForm(): boolean {
        showForm.value = !showForm.value;
        error.value = "";
        if (showForm.value) {
            pricePerItem.value = String(options.auction().leftover_price ?? "");
        }
        return showForm.value;
    }

    async function submit() {
        error.value = "";
        saving.value = true;
        try {
            const data = await api<{ auction: Auction }>(
                `/admin/auctions/${options.auction().id}/override-sales`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        username: username.value,
                        quantity: Number(quantity.value),
                        price_per_item: pricePerItem.value,
                    }),
                },
            );
            options.onUpdate(data.auction);
            showForm.value = false;
            username.value = "";
            quantity.value = 1;
            notify?.("Override sale added.", "success");
        } catch (e) {
            error.value =
                apiError(e, "username", "quantity", "price_per_item") ||
                "Failed to add override sale.";
        } finally {
            saving.value = false;
        }
    }

    return { showForm, username, quantity, pricePerItem, error, saving, toggleForm, submit };
}
