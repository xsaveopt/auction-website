import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { defineComponent, h, reactive, ref, type Ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import type { Auction, HeartbeatData, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    router: { push: vi.fn(), replace: vi.fn() },
}));

vi.mock("../api", () => {
    class ApiError extends Error {
        status: number;
        data: { message?: string; errors?: Record<string, string[]> };
        constructor(status: number, data: { message?: string; errors?: Record<string, string[]> }) {
            super(data.message ?? "Request failed");
            this.status = status;
            this.data = data;
        }
    }
    return { api: state.apiMock, ApiError };
});
vi.mock("vue-router", () => ({ useRouter: () => state.router }));

import { useAuctionDetail } from "./useAuctionDetail";
import { ApiError } from "../api";

type Detail = ReturnType<typeof useAuctionDetail>;

const alice: User = { id: 10, username: "alice" };
const bob: User = { id: 11, username: "bob" };
const seller: User = { id: 1, username: "seller", is_admin: true };

function auction(overrides: Partial<Auction> = {}): Auction {
    return {
        id: 7,
        title: "Laptop",
        starting_price: "10.00",
        quantity: 3,
        max_per_bidder: 2,
        ends_at: "2026-01-01T12:00:00Z",
        status: "active",
        is_active: true,
        current_price: "10.00",
        images: [],
        bids: [],
        questions: [],
        seller,
        ...overrides,
    };
}

function mountDetail(initial: Auction, options: { user?: User | null; notify?: boolean } = {}) {
    state.apiMock.mockResolvedValue({ auction: initial });
    const user: Ref<User | null> = ref(options.user === undefined ? alice : options.user);
    const heartbeatData: Ref<HeartbeatData | null> = ref(null);
    const now = ref(new Date("2026-01-01T10:00:00Z"));
    const notify = vi.fn();
    const props = reactive({ id: "7" });
    let result!: Detail;
    const Harness = defineComponent({
        setup() {
            result = useAuctionDetail(props);
            return () => h("div");
        },
    });
    const provide: Record<string, unknown> = {
        user,
        schedule: ref(null),
        currencySymbol: ref("€"),
        heartbeatData,
        now,
    };
    if (options.notify !== false) provide.notify = notify;
    mount(Harness, { global: { provide } });
    return {
        user,
        heartbeatData,
        now,
        notify,
        props,
        get result() {
            return result;
        },
    };
}

enableAutoUnmount(afterEach);

describe("useAuctionDetail", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("loads the auction and seeds the bid form from the starting price", async () => {
        const { result } = mountDetail(auction());
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7");
        expect(result.loading.value).toBe(false);
        expect(result.bidAmount.value).toBe("10.00");
        expect(result.bidQuantity.value).toBe(1);
        expect(result.canAskQuestion.value).toBe(true);
        expect(result.isSeller.value).toBe(false);
        expect(result.canModerateQuestions.value).toBeFalsy();
        expect(result.auctionStatus.value?.label).toBe("Live auction");
        expect(result.primaryPriceLabel.value).toBe("Current clearing price");
    });

    it("seeds the bid form from the user's existing bid", async () => {
        const { result } = mountDetail(
            auction({ bids: [{ id: 1, amount: "20.00", quantity: 5, user: alice }] }),
        );
        await flushPromises();

        expect(result.myBid.value?.id).toBe(1);
        expect(result.bidAmount.value).toBe("21.00");
        expect(result.bidQuantity.value).toBe(2);
    });

    it("places a clamped bid and reloads", async () => {
        const { result, notify } = mountDetail(auction());
        await flushPromises();

        result.bidAmount.value = "12.50";
        result.bidQuantity.value = 9;
        await result.placeBid();
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7/bids", {
            method: "POST",
            body: JSON.stringify({ amount: 12.5, quantity: 2 }),
        });
        expect(notify).toHaveBeenCalledWith("Bid placed successfully!", "success");
        expect(result.error.value).toBe("");
    });

    it("surfaces validation errors when bidding fails", async () => {
        const { result } = mountDetail(auction());
        await flushPromises();

        state.apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { amount: ["Amount too low."] } }),
        );
        await result.placeBid();
        expect(result.error.value).toBe("Amount too low.");

        state.apiMock.mockRejectedValueOnce(new Error("offline"));
        await result.placeBid();
        expect(result.error.value).toBe("Failed to place bid.");
    });

    it("notifies about overbids, answered questions and highlights new bids", async () => {
        vi.useFakeTimers({ toFake: ["setTimeout", "clearTimeout"] });
        const { result, notify, heartbeatData } = mountDetail(
            auction({
                bids: [{ id: 1, amount: "20.00", quantity: 1, won_quantity: 1, user: alice }],
                questions: [{ id: 5, question: "Q?", answer: null, user: alice }],
            }),
        );
        await flushPromises();

        heartbeatData.value = {
            auction: auction({
                bids: [
                    { id: 1, amount: "20.00", quantity: 1, won_quantity: 0, user: alice },
                    { id: 2, amount: "30.00", quantity: 1, won_quantity: 1, user: bob },
                ],
                questions: [{ id: 5, question: "Q?", answer: "Yes", user: alice }],
            }),
        };
        await flushPromises();

        expect(notify).toHaveBeenCalledWith('You\'ve been overbid on "Laptop"!', "warning", 6000);
        expect(notify).toHaveBeenCalledWith("Your question has been answered!", "info", 6000);
        expect([...result.highlightedBids.value]).toEqual([2]);

        vi.advanceTimersByTime(1500);
        expect(result.highlightedBids.value.size).toBe(0);
    });

    it("ignores heartbeat data for other auctions", async () => {
        const { result, heartbeatData } = mountDetail(auction());
        await flushPromises();

        heartbeatData.value = { auction: auction({ id: 99, title: "Other" }) };
        await flushPromises();

        expect(result.auction.value?.title).toBe("Laptop");
    });

    it("announces the auction result when it ends", async () => {
        const { notify, heartbeatData } = mountDetail(
            auction({
                bids: [{ id: 1, amount: "20.00", quantity: 1, won_quantity: 1, user: alice }],
            }),
        );
        await flushPromises();

        heartbeatData.value = {
            auction: auction({
                is_active: false,
                status: "ended",
                bids: [{ id: 1, amount: "20.00", quantity: 1, won_quantity: 1, user: alice }],
            }),
        };
        await flushPromises();

        expect(notify).toHaveBeenCalledWith('You won "Laptop"!', "success", 10000);
    });

    it("warns bidders when the auction ends within five minutes", async () => {
        const { notify, now } = mountDetail(
            auction({ bids: [{ id: 1, amount: "20.00", quantity: 1, user: alice }] }),
        );
        await flushPromises();
        expect(notify).not.toHaveBeenCalled();

        now.value = new Date("2026-01-01T11:57:00Z");
        await flushPromises();
        now.value = new Date("2026-01-01T11:58:00Z");
        await flushPromises();

        expect(notify).toHaveBeenCalledTimes(1);
        expect(notify).toHaveBeenCalledWith(
            '"Laptop" ends in less than 5 minutes!',
            "warning",
            6000,
        );
    });

    it("computes leftover sale state", async () => {
        const { result } = mountDetail(
            auction({
                is_active: false,
                status: "ended",
                quantity: 5,
                items_allocated: 2,
                leftover_enabled: true,
                leftover_quantity: 2,
                starting_price: "20.00",
                leftover_price: "15.00",
            }),
        );
        await flushPromises();

        expect(result.hasLeftoversAvailable.value).toBe(true);
        expect(result.effectiveLeftoverAvailable.value).toBe(true);
        expect(result.leftoverSold.value).toBe(1);
        expect(result.leftoverDiscountPercent.value).toBe(25);
        expect(result.leftoverSavingsPerItem.value).toBe(5);
        expect(result.priceOfferLimit.value).toBe("14.99");
        expect(result.auctionStatus.value?.label).toBe("Leftover sale");
        expect(result.auctionStatus.value?.summary).toBe("2 items still available at €15.00 each.");
        expect(result.primaryPriceLabel.value).toBe("Buy now price");
        expect(result.primaryPriceValue.value).toBe("15.00");
        expect(result.shouldShowLeftoverSection.value).toBe(true);

        result.leftoverQuantity.value = 2;
        expect(result.leftoverBuyTotal.value).toBe(30);
        result.offerQuantity.value = 2;
        result.offerPrice.value = "4.5";
        expect(result.offerTotal.value).toBe(9);
    });

    it("hides leftovers from bidders once the round is closed", async () => {
        const { result } = mountDetail(
            auction({
                is_active: false,
                status: "ended",
                leftover_enabled: true,
                leftover_quantity: 2,
                leftover_price: "5.00",
                bid_count: 2,
                round: { id: 1, name: "R", status: "ended" },
            }),
        );
        await flushPromises();

        expect(result.effectiveLeftoverAvailable.value).toBe(false);
        expect(result.shouldShowLeftoverSection.value).toBe(false);
        expect(result.auctionStatus.value?.label).toBe("Ended");
        expect(result.primaryPriceLabel.value).toBe("Final clearing price");
    });

    it("reports sold out when all leftovers are gone", async () => {
        const { result } = mountDetail(
            auction({ is_active: false, leftover_enabled: true, leftover_quantity: 0 }),
        );
        await flushPromises();

        expect(result.auctionStatus.value?.label).toBe("Sold out");
        expect(result.primaryPriceLabel.value).toBe("Starting price");
    });

    it("tracks the user's price offer and rebid state", async () => {
        const { result } = mountDetail(
            auction({
                is_active: false,
                leftover_enabled: true,
                leftover_quantity: 1,
                leftover_price: "10.00",
                leftover_price_offers: [
                    {
                        id: 3,
                        auction_id: 7,
                        user_id: 10,
                        quantity: 1,
                        offered_price_per_item: "6.00",
                        status: "pending",
                        rebid_requested_at: "2026-01-01T00:00:00Z",
                        user: alice,
                    },
                    {
                        id: 4,
                        auction_id: 7,
                        user_id: 11,
                        quantity: 1,
                        offered_price_per_item: "5.00",
                        status: "rejected",
                        user: bob,
                    },
                ],
            }),
        );
        await flushPromises();

        expect(result.myPriceOffer.value?.id).toBe(3);
        expect(result.myPriceOfferNeedsRebid.value).toBe(true);
        expect(result.rebidMinPrice.value).toBe("6.01");
        expect(result.pendingOffers.value.map((o) => o.id)).toEqual([3]);
        expect(result.allOffers.value).toHaveLength(2);
    });

    it("buys leftovers and reports failures", async () => {
        const initial = auction({ is_active: false, leftover_enabled: true, leftover_quantity: 3 });
        const { result, notify } = mountDetail(initial);
        await flushPromises();

        result.leftoverQuantity.value = 2;
        state.apiMock.mockResolvedValueOnce({ auction: { ...initial, leftover_quantity: 1 } });
        await result.buyLeftover();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7/leftover-purchases", {
            method: "POST",
            body: JSON.stringify({ quantity: 2 }),
        });
        expect(notify).toHaveBeenCalledWith("Purchase successful!", "success");
        expect(result.buyingLeftover.value).toBe(false);
        await flushPromises();
        expect(result.leftoverQuantity.value).toBe(1);

        state.apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { quantity: ["Too many."] } }),
        );
        await result.buyLeftover();
        expect(result.leftoverError.value).toBe("Too many.");
    });

    it("submits a price offer", async () => {
        const initial = auction({ is_active: false, leftover_enabled: true, leftover_quantity: 3 });
        const { result } = mountDetail(initial);
        await flushPromises();

        result.showOfferForm.value = true;
        result.offerQuantity.value = 2;
        result.offerPrice.value = "4.00";
        await result.submitPriceOffer();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7/leftover-price-offers", {
            method: "POST",
            body: JSON.stringify({ quantity: 2, offered_price_per_item: 4 }),
        });
        expect(result.showOfferForm.value).toBe(false);

        state.apiMock.mockRejectedValueOnce(new ApiError(422, { message: "Closed" }));
        await result.submitPriceOffer();
        expect(result.offerError.value).toBe("Closed");
    });

    it("deletes the auction through the confirm dialog", async () => {
        const { result } = mountDetail(auction());
        await flushPromises();

        result.deleteAuction();
        expect(result.confirmDialog.value?.danger).toBe(true);
        await result.confirmDialog.value?.onConfirm();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7", { method: "DELETE" });
        expect(state.router.push).toHaveBeenCalledWith("/");

        state.apiMock.mockRejectedValueOnce(new Error("nope"));
        result.deleteAuction();
        await result.confirmDialog.value?.onConfirm();
        expect(result.error.value).toBe("Failed to delete auction.");
    });

    it("runs admin offer actions through the confirm dialog", async () => {
        const initial = auction();
        const { result, notify } = mountDetail(initial, { user: seller });
        await flushPromises();
        const offer = {
            id: 3,
            auction_id: 7,
            user_id: 10,
            quantity: 2,
            offered_price_per_item: "6",
            status: "pending",
            user: alice,
        };

        result.acceptPriceOffer(offer);
        expect(result.confirmDialog.value?.message).toBe("Accept offer of €6.00 × 2 from alice?");
        await result.confirmDialog.value?.onConfirm();
        expect(state.apiMock).toHaveBeenCalledWith("/admin/leftover-price-offers/3/accept", {
            method: "POST",
        });
        expect(notify).toHaveBeenCalledWith("Offer accepted.", "success");

        result.rejectPriceOffer(offer);
        await result.confirmDialog.value?.onConfirm();
        expect(state.apiMock).toHaveBeenCalledWith("/admin/leftover-price-offers/3/reject", {
            method: "POST",
        });

        result.deletePriceOffer(offer);
        state.apiMock.mockRejectedValueOnce(new Error("x"));
        await result.confirmDialog.value?.onConfirm();
        expect(notify).toHaveBeenCalledWith("Failed to delete offer.", "error");
    });

    it("adds an admin offer and resets the form", async () => {
        const { result } = mountDetail(auction(), { user: seller });
        await flushPromises();

        result.showAdminOfferForm.value = true;
        result.adminOfferUsername.value = "alice";
        result.adminOfferQuantity.value = 2;
        result.adminOfferPrice.value = "3.5";
        await result.submitAdminOffer();

        expect(state.apiMock).toHaveBeenCalledWith("/admin/auctions/7/leftover-price-offers", {
            method: "POST",
            body: JSON.stringify({ username: "alice", quantity: 2, offered_price_per_item: 3.5 }),
        });
        expect(result.showAdminOfferForm.value).toBe(false);
        expect(result.adminOfferUsername.value).toBe("");
        expect(result.adminOfferQuantity.value).toBe(1);
        expect(result.adminOfferPrice.value).toBe("");
        expect(result.adminOfferSaving.value).toBe(false);
    });

    it("edits, adds and deletes bids as an admin", async () => {
        const { result } = mountDetail(auction(), { user: seller });
        await flushPromises();
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
        expect(state.apiMock).toHaveBeenCalledWith("/admin/bids/50", {
            method: "PUT",
            body: JSON.stringify({ amount: 14, quantity: 2 }),
        });
        expect(result.editingBidId.value).toBeNull();

        result.showAddBid.value = true;
        result.addBidUsername.value = "bob";
        result.addBidAmount.value = "11";
        result.addBidQuantity.value = 1;
        await result.submitAddBid();
        expect(state.apiMock).toHaveBeenCalledWith("/admin/auctions/7/bids", {
            method: "POST",
            body: JSON.stringify({ username: "bob", amount: 11, quantity: 1 }),
        });
        expect(result.showAddBid.value).toBe(false);

        result.deleteBid(bid);
        state.apiMock.mockRejectedValueOnce(new ApiError(403, { message: "Denied" }));
        await result.confirmDialog.value?.onConfirm();
        expect(result.adminBidError.value).toBe("Denied");
    });

    it("ends, cancels, reactivates and extends the auction", async () => {
        const { result } = mountDetail(auction(), { user: seller });
        await flushPromises();

        result.endAuction();
        expect(result.confirmDialog.value?.confirmLabel).toBe("End Auction");
        await result.confirmDialog.value?.onConfirm();
        expect(state.apiMock).toHaveBeenCalledWith("/admin/auctions/7/end", { method: "POST" });

        result.endAuction(true);
        expect(result.confirmDialog.value?.confirmLabel).toBe("Cancel Auction");
        state.apiMock.mockRejectedValueOnce(new Error("x"));
        await result.confirmDialog.value?.onConfirm();
        expect(result.adminAuctionError.value).toBe("Failed to cancel auction.");

        await result.reactivateAuction();
        expect(result.adminAuctionError.value).toBe("Set a new end time first.");
        await result.extendAuction();
        expect(result.adminAuctionError.value).toBe("Set a new end time first.");

        result.newEndsAt.value = "2026-02-01T10:00";
        await result.reactivateAuction();
        expect(state.apiMock).toHaveBeenCalledWith("/admin/auctions/7/reactivate", {
            method: "POST",
            body: JSON.stringify({ ends_at: "2026-02-01T10:00" }),
        });
        expect(result.newEndsAt.value).toBe("");

        result.newEndsAt.value = "2026-03-01T10:00";
        state.apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { ends_at: ["Must be later."] } }),
        );
        await result.extendAuction();
        expect(result.adminAuctionError.value).toBe("Must be later.");
        expect(result.adminAuctionSaving.value).toBe(false);
    });

    it("adds and deletes leftover purchases as an admin", async () => {
        const { result, notify } = mountDetail(auction(), { user: seller });
        await flushPromises();

        result.showAddPurchase.value = true;
        result.addPurchaseUsername.value = "bob";
        result.addPurchaseQuantity.value = 2;
        await result.submitAddPurchase();
        expect(state.apiMock).toHaveBeenCalledWith("/admin/auctions/7/leftover-purchases", {
            method: "POST",
            body: JSON.stringify({ username: "bob", quantity: 2 }),
        });
        expect(result.showAddPurchase.value).toBe(false);
        expect(result.addPurchaseQuantity.value).toBe(1);

        result.deleteLeftoverPurchase({ id: 8, quantity: 1, price_per_item: "5", user: bob });
        expect(result.confirmDialog.value?.message).toBe("Delete purchase by bob?");
        state.apiMock.mockRejectedValueOnce(new Error("x"));
        await result.confirmDialog.value?.onConfirm();
        expect(notify).toHaveBeenCalledWith("Failed to delete purchase.", "error");
    });

    it("loads users only once", async () => {
        const { result } = mountDetail(auction(), { user: seller });
        await flushPromises();

        state.apiMock.mockResolvedValueOnce({ users: [alice, bob] });
        await result.loadUsers();
        await result.loadUsers();

        expect(result.allUsers.value).toHaveLength(2);
        expect(result.usersLoaded.value).toBe(true);
        expect(state.apiMock.mock.calls.filter(([url]) => url === "/admin/users")).toHaveLength(1);
    });

    it("reloads when the id prop changes", async () => {
        const { props, result } = mountDetail(auction());
        await flushPromises();
        result.activeImage.value = 3;

        props.id = "8";
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/auctions/8");
        expect(result.activeImage.value).toBe(0);
    });

    it("works without a notify provider and formats values", async () => {
        const { result } = mountDetail(auction(), { user: null, notify: false });
        await flushPromises();

        expect(result.notify).toBeUndefined();
        expect(result.canAskQuestion.value).toBe(false);
        expect(result.myBid.value).toBeNull();
        expect(result.formatDate("2026-01-01T10:20:30Z")).toBe("2026-01-01 10:20");
        expect(result.formatDate(undefined)).toBe("");
        expect(result.formatMoney("3")).toBe("3.00");
        expect(result.watchingText(2)).toBe("2 currently watching");
        result.bidAmount.value = "2.5";
        result.bidQuantity.value = 2;
        expect(result.selectedBidTotal.value).toBe(5);
    });
});
