import { computed, ref, watch } from "vue";
import { api } from "../lib/api";
import { apiError } from "../lib/apiError";
import { clampToStock, getPriceOfferLimit } from "../lib/auctionPresentation";
import { injectNotifyOptional } from "../lib/injection";
import type { Auction, AuctionActionOptions, LeftoverPriceOffer } from "../lib/types";

export function usePriceOffer(
    options: Omit<AuctionActionOptions, "confirm"> & {
        offer: () => LeftoverPriceOffer | null;
    },
) {
    const notify = injectNotifyOptional();
    const showOfferForm = ref(false);
    const offerQuantity = ref(1);
    const offerPrice = ref("");
    const offerError = ref("");
    const submittingOffer = ref(false);

    const needsRebid = computed(() => {
        const offer = options.offer();
        return offer?.status === "pending" && offer.rebid_requested_at != null;
    });

    const priceOfferLimit = computed(() => getPriceOfferLimit(options.auction()));

    const rebidMinPrice = computed(() => {
        const current = Number(options.offer()?.offered_price_per_item ?? 0);
        return (current + 0.01).toFixed(2);
    });

    const offerTotal = computed(() => {
        const quantity =
            Number(options.auction().leftover_quantity) > 1 ? Number(offerQuantity.value || 1) : 1;

        return quantity * Number(offerPrice.value || 0);
    });

    watch(
        () => options.auction().leftover_quantity,
        (quantity) => {
            offerQuantity.value = clampToStock(offerQuantity.value, quantity);
        },
    );

    function toggleOfferForm() {
        showOfferForm.value = !showOfferForm.value;
        offerError.value = "";
    }

    async function submitPriceOffer() {
        offerError.value = "";
        submittingOffer.value = true;
        try {
            const data = await api<{ auction: Auction }>(
                `/auctions/${options.auction().id}/leftover-price-offers`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        quantity: Number(offerQuantity.value),
                        offered_price_per_item: Number(offerPrice.value),
                    }),
                },
            );
            options.onUpdate(data.auction);
            showOfferForm.value = false;
            notify?.("Offer submitted!", "success");
        } catch (e) {
            offerError.value =
                apiError(e, "quantity", "offered_price_per_item") || "Failed to submit offer.";
        } finally {
            submittingOffer.value = false;
        }
    }

    return {
        showOfferForm,
        offerQuantity,
        offerPrice,
        offerError,
        submittingOffer,
        needsRebid,
        priceOfferLimit,
        rebidMinPrice,
        offerTotal,
        toggleOfferForm,
        submitPriceOffer,
    };
}
