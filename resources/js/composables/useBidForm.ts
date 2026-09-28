import { computed, ref, watch, type Ref } from "vue";
import { api } from "../lib/api";
import { apiError } from "../lib/apiError";
import { injectNotifyOptional, injectUser } from "../lib/injection";
import type { Auction, Bid } from "../lib/types";

export function clampBidQuantity(
    quantity: number | string | null | undefined,
    maxPerBidder: number | string | null | undefined,
) {
    const max = Math.max(1, Number(maxPerBidder || 1));
    const normalized = Math.trunc(Number(quantity || 1));
    return Math.min(Math.max(normalized || 1, 1), max);
}

export function useBidForm(options: {
    auction: () => Auction;
    reload: () => Promise<Auction | null>;
    error: Ref<string>;
}) {
    const user = injectUser();
    const notify = injectNotifyOptional();
    const error = options.error;
    const bidAmount = ref("");
    const bidQuantity = ref(1);

    const myBid = computed<Bid | null>(() => {
        if (!user.value) return null;
        return options.auction().bids?.find((b) => b.user?.id === user.value?.id) ?? null;
    });

    const allowsMultiple = computed(() => (options.auction().max_per_bidder ?? 0) > 1);

    const selectedBidTotal = computed(
        () => Number(bidAmount.value || 0) * Number(bidQuantity.value || 0),
    );

    function seed(auction: Auction) {
        const my = (auction.bids ?? []).find((b) => b.user?.id === user.value?.id);
        bidAmount.value = my
            ? (Number(my.amount) + 1).toFixed(2)
            : Number(auction.starting_price).toFixed(2);
        bidQuantity.value = my ? clampBidQuantity(my.quantity, auction.max_per_bidder) : 1;
    }

    seed(options.auction());

    watch(options.auction, (auction) => {
        bidQuantity.value = clampBidQuantity(bidQuantity.value, auction.max_per_bidder);
    });

    async function placeBid() {
        error.value = "";
        try {
            const auction = options.auction();
            const quantity =
                Number(auction.max_per_bidder) > 1
                    ? clampBidQuantity(bidQuantity.value, auction.max_per_bidder)
                    : 1;

            bidQuantity.value = quantity;

            await api(`/auctions/${auction.id}/bids`, {
                method: "POST",
                body: JSON.stringify({
                    amount: Number(bidAmount.value),
                    quantity,
                }),
            });
            const reloaded = await options.reload();
            if (reloaded) seed(reloaded);
            notify?.("Bid placed successfully!", "success");
        } catch (e) {
            error.value = apiError(e, "amount", "quantity") || "Failed to place bid.";
        }
    }

    return {
        bidAmount,
        bidQuantity,
        myBid,
        allowsMultiple,
        selectedBidTotal,
        seed,
        placeBid,
    };
}
