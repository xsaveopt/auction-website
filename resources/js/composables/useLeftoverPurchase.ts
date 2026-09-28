import { computed, ref, watch } from "vue";
import { api } from "../lib/api";
import { apiError } from "../lib/apiError";
import { clampToStock } from "../lib/auctionPresentation";
import { injectNotifyOptional } from "../lib/injection";
import type { Auction, AuctionActionOptions } from "../lib/types";

export function useLeftoverPurchase(options: Omit<AuctionActionOptions, "confirm">) {
    const notify = injectNotifyOptional();
    const leftoverQuantity = ref(1);
    const leftoverError = ref("");
    const buyingLeftover = ref(false);

    const leftoverBuyTotal = computed(() => {
        const auction = options.auction();
        const quantity =
            Number(auction.leftover_quantity) > 1 ? Number(leftoverQuantity.value || 1) : 1;

        return quantity * Number(auction.leftover_price);
    });

    watch(
        () => options.auction().leftover_quantity,
        (quantity) => {
            leftoverQuantity.value = clampToStock(leftoverQuantity.value, quantity);
        },
    );

    async function buyLeftover() {
        leftoverError.value = "";
        try {
            buyingLeftover.value = true;
            const data = await api<{ auction: Auction }>(
                `/auctions/${options.auction().id}/leftover-purchases`,
                {
                    method: "POST",
                    body: JSON.stringify({ quantity: Number(leftoverQuantity.value) }),
                },
            );
            options.onUpdate(data.auction);
            notify?.("Purchase successful!", "success");
        } catch (e) {
            leftoverError.value = apiError(e, "quantity") || "Purchase failed.";
        } finally {
            buyingLeftover.value = false;
        }
    }

    return {
        leftoverQuantity,
        leftoverError,
        buyingLeftover,
        leftoverBuyTotal,
        buyLeftover,
    };
}
