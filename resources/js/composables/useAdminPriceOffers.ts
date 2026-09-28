import { computed, ref } from "vue";
import { api } from "../lib/api";
import { apiError } from "../lib/apiError";
import { getPriceOfferLimit } from "../lib/auctionPresentation";
import { formatMoney } from "../lib/format";
import { injectCurrencySymbol, injectNotifyOptional } from "../lib/injection";
import type { Auction, AuctionActionOptions, LeftoverPriceOffer } from "../lib/types";

export function useAdminOfferForm(options: Omit<AuctionActionOptions, "confirm">) {
    const notify = injectNotifyOptional();
    const showAdminOfferForm = ref(false);
    const adminOfferUsername = ref("");
    const adminOfferQuantity = ref(1);
    const adminOfferPrice = ref("");
    const adminOfferError = ref("");
    const adminOfferSaving = ref(false);

    const priceOfferLimit = computed(() => getPriceOfferLimit(options.auction()));

    function toggleAdminOfferForm(): boolean {
        showAdminOfferForm.value = !showAdminOfferForm.value;
        adminOfferError.value = "";
        return showAdminOfferForm.value;
    }

    async function submitAdminOffer() {
        adminOfferError.value = "";
        adminOfferSaving.value = true;
        try {
            const data = await api<{ auction: Auction }>(
                `/admin/auctions/${options.auction().id}/leftover-price-offers`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        username: adminOfferUsername.value,
                        quantity: Number(adminOfferQuantity.value),
                        offered_price_per_item: Number(adminOfferPrice.value),
                    }),
                },
            );
            options.onUpdate(data.auction);
            showAdminOfferForm.value = false;
            adminOfferUsername.value = "";
            adminOfferQuantity.value = 1;
            adminOfferPrice.value = "";
            notify?.("Offer added.", "success");
        } catch (e) {
            adminOfferError.value =
                apiError(e, "username", "quantity", "offered_price_per_item") ||
                "Failed to add offer.";
        } finally {
            adminOfferSaving.value = false;
        }
    }

    return {
        showAdminOfferForm,
        adminOfferUsername,
        adminOfferQuantity,
        adminOfferPrice,
        adminOfferError,
        adminOfferSaving,
        priceOfferLimit,
        toggleAdminOfferForm,
        submitAdminOffer,
    };
}

export function useAdminOfferActions(options: Omit<AuctionActionOptions, "auction">) {
    const notify = injectNotifyOptional();
    const currencySymbol = injectCurrencySymbol();

    function runOfferAction(
        offer: LeftoverPriceOffer,
        path: string,
        method: "POST" | "DELETE",
        success: string,
        failure: string,
    ) {
        return async () => {
            try {
                const data = await api<{ auction: Auction }>(
                    `/admin/leftover-price-offers/${offer.id}${path}`,
                    { method },
                );
                options.onUpdate(data.auction);
                notify?.(success, "success");
            } catch (e) {
                notify?.(apiError(e) || failure, "error");
            }
        };
    }

    function acceptPriceOffer(offer: LeftoverPriceOffer) {
        options.confirm({
            message: `Accept offer of ${formatMoney(offer.offered_price_per_item, currencySymbol.value)} × ${offer.quantity} from ${offer.user?.username}?`,
            confirmLabel: "Accept",
            danger: false,
            onConfirm: runOfferAction(
                offer,
                "/accept",
                "POST",
                "Offer accepted.",
                "Failed to accept offer.",
            ),
        });
    }

    function rejectPriceOffer(offer: LeftoverPriceOffer) {
        options.confirm({
            message: `Reject offer from ${offer.user?.username}?`,
            confirmLabel: "Reject",
            danger: true,
            onConfirm: runOfferAction(
                offer,
                "/reject",
                "POST",
                "Offer rejected.",
                "Failed to reject offer.",
            ),
        });
    }

    function deletePriceOffer(offer: LeftoverPriceOffer) {
        options.confirm({
            message: `Delete offer from ${offer.user?.username}?`,
            confirmLabel: "Delete",
            danger: true,
            onConfirm: runOfferAction(
                offer,
                "",
                "DELETE",
                "Offer deleted.",
                "Failed to delete offer.",
            ),
        });
    }

    return { acceptPriceOffer, rejectPriceOffer, deletePriceOffer };
}
