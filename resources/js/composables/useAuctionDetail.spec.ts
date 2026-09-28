import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { reactive, ref, type Ref } from "vue";
import { flushPromises, enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../lib/api";
import { alice, bob, makeAuction as auction, mountComposable, seller } from "../testing";
import type { Auction, HeartbeatData, User } from "../lib/types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    router: { push: vi.fn(), replace: vi.fn() },
}));

vi.mock("../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../lib/api")>()),
    api: state.apiMock,
}));
vi.mock("vue-router", () => ({ useRouter: () => state.router }));

import { useAuctionDetail } from "./useAuctionDetail";

function mountDetail(initial: Auction, options: { user?: User | null; notify?: boolean } = {}) {
    state.apiMock.mockResolvedValue({ auction: initial });
    const user: Ref<User | null> = ref(options.user === undefined ? alice : options.user);
    const heartbeatData: Ref<HeartbeatData | null> = ref(null);
    const now = ref(new Date("2026-01-01T10:00:00Z"));
    const notify = vi.fn();
    const props = reactive({ id: "7" });
    const provide: Record<string, unknown> = {
        user,
        currencySymbol: ref("€"),
        heartbeatData,
        now,
    };
    if (options.notify !== false) provide.notify = notify;
    const { result } = mountComposable(() => useAuctionDetail(props), provide);
    return { user, heartbeatData, now, notify, props, result };
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

    it("loads the auction and derives the viewer's permissions", async () => {
        const { result } = mountDetail(auction());
        expect(result.loading.value).toBe(true);
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7");
        expect(result.loading.value).toBe(false);
        expect(result.canAskQuestion.value).toBe(true);
        expect(result.canBid.value).toBe(true);
        expect(result.isSeller.value).toBe(false);
        expect(result.canModerateQuestions.value).toBe(false);
        expect(result.auctionStatus.value?.label).toBe("Live auction");
        expect(result.auctionStatus.value?.summary).toBe("Bidding closes 2026-01-01 12:00.");
        expect(result.primaryPriceLabel.value).toBe("Current clearing price");
    });

    it("lets the seller moderate but not bid", async () => {
        const { result } = mountDetail(auction(), { user: seller });
        await flushPromises();

        expect(result.isSeller.value).toBe(true);
        expect(result.canBid.value).toBe(false);
        expect(result.canAskQuestion.value).toBe(false);
        expect(result.canModerateQuestions.value).toBe(true);
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

    it("does not highlight bids that are unchanged", async () => {
        const bids = [{ id: 1, amount: "20.00", quantity: 1, user: alice }];
        const { result, heartbeatData } = mountDetail(auction({ bids }));
        await flushPromises();

        heartbeatData.value = { auction: auction({ bids: [...bids] }) };
        await flushPromises();

        expect(result.highlightedBids.value.size).toBe(0);
    });

    it("ignores heartbeat data for other auctions", async () => {
        const { result, heartbeatData } = mountDetail(auction());
        await flushPromises();

        heartbeatData.value = { auction: auction({ id: 99, title: "Other" }) };
        await flushPromises();

        expect(result.auction.value?.title).toBe("Laptop");
        expect(result.updateAuction(auction({ id: 99 }))).toBe(false);
        expect(result.updateAuction(null)).toBe(false);
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

    it("tells losing bidders and watchers that the auction ended", async () => {
        const losing = mountDetail(
            auction({ bids: [{ id: 1, amount: "20.00", quantity: 1, user: alice }] }),
        );
        await flushPromises();
        losing.result.updateAuction(
            auction({
                is_active: false,
                bids: [{ id: 1, amount: "20.00", quantity: 1, won_quantity: 0, user: alice }],
            }),
        );
        expect(losing.notify).toHaveBeenCalledWith(
            'Auction "Laptop" has ended — you didn\'t win.',
            "info",
            8000,
        );

        const watcher = mountDetail(auction(), { user: bob });
        await flushPromises();
        watcher.result.updateAuction(auction({ is_active: false }));
        expect(watcher.notify).toHaveBeenCalledWith('"Laptop" has ended.', "info");
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
        expect(result.auctionStatus.value?.label).toBe("Leftover sale");
        expect(result.auctionStatus.value?.summary).toBe("2 items still available at €15.00 each.");
        expect(result.primaryPriceLabel.value).toBe("Buy now price");
        expect(result.primaryPriceValue.value).toBe("15.00");
        expect(result.shouldShowLeftoverSection.value).toBe(true);
        expect(result.showSoldOutNotice.value).toBe(false);
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
        expect(result.showSoldOutNotice.value).toBe(true);
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

    it("opens any dialog passed to confirm", async () => {
        const { result } = mountDetail(auction());
        await flushPromises();

        const onConfirm = vi.fn();
        result.confirm({ message: "Sure?", onConfirm });
        expect(result.confirmDialog.value?.message).toBe("Sure?");
    });

    it("returns the reloaded auction, or null when the response is stale or fails", async () => {
        const { result } = mountDetail(auction());
        await flushPromises();

        state.apiMock.mockResolvedValueOnce({ auction: auction({ title: "Fresh" }) });
        expect((await result.load())?.title).toBe("Fresh");

        state.apiMock.mockRejectedValueOnce(new Error("offline"));
        expect(await result.load()).toBeNull();
        expect(result.loadError.value).toBe("Failed to load this auction.");
    });

    it("reloads and resets when the id prop changes", async () => {
        const { props, result } = mountDetail(auction());
        await flushPromises();
        result.activeImage.value = 3;
        result.error.value = "Old error";

        props.id = "8";
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/auctions/8");
        expect(result.activeImage.value).toBe(0);
        expect(result.error.value).toBe("");
    });

    it("does not compare a newly opened auction against the previous one", async () => {
        const { props, result, notify } = mountDetail(
            auction({
                bids: [{ id: 1, amount: "20.00", quantity: 1, won_quantity: 1, user: alice }],
            }),
        );
        await flushPromises();

        state.apiMock.mockResolvedValue({
            auction: auction({ id: 8, title: "Monitor", is_active: false, status: "ended" }),
        });
        props.id = "8";
        await flushPromises();

        expect(result.auction.value?.id).toBe(8);
        expect(notify).not.toHaveBeenCalled();
    });

    it("ignores a slow response for the previously opened auction", async () => {
        const { props, result } = mountDetail(auction());
        await flushPromises();

        let resolveOld!: (value: unknown) => void;
        state.apiMock.mockImplementation((url: string) =>
            url === "/auctions/7"
                ? new Promise((resolve) => (resolveOld = resolve))
                : Promise.resolve({ auction: auction({ id: 8, title: "Monitor" }) }),
        );

        const stale = result.load();
        props.id = "8";
        await flushPromises();
        resolveOld({ auction: auction({ title: "Stale laptop" }) });
        await flushPromises();

        expect(await stale).toBeNull();
        expect(result.auction.value?.title).toBe("Monitor");
        expect(result.loading.value).toBe(false);
    });

    it("shows an error instead of loading forever when the auction cannot be loaded", async () => {
        const { props, result } = mountDetail(auction());
        await flushPromises();

        state.apiMock.mockRejectedValue(new ApiError(404, { message: "Not found" }));
        props.id = "404";
        await flushPromises();

        expect(result.loading.value).toBe(false);
        expect(result.auction.value).toBeNull();
        expect(result.loadError.value).toBe("This auction could not be found.");

        state.apiMock.mockRejectedValue(new TypeError("Failed to fetch"));
        props.id = "405";
        await flushPromises();

        expect(result.loadError.value).toBe("Failed to load this auction.");
    });

    it("works without a notify provider or a user", async () => {
        const { result } = mountDetail(auction(), { user: null, notify: false });
        await flushPromises();

        expect(result.notify).toBeUndefined();
        expect(result.canAskQuestion.value).toBe(false);
        expect(result.canBid.value).toBe(false);
        expect(result.myBid.value).toBeNull();
    });

    it("keeps the leftover section for a buyer after the stock sells out", async () => {
        const { result } = mountDetail(
            auction({
                is_active: false,
                status: "ended",
                leftover_enabled: true,
                leftover_quantity: 0,
                leftover_purchases: [{ id: 9, quantity: 1, price_per_item: "7.50", user: alice }],
            }),
        );
        await flushPromises();

        expect(result.myLeftoverPurchase.value?.id).toBe(9);
        expect(result.hasLeftoversAvailable.value).toBe(false);
        expect(result.shouldShowLeftoverSection.value).toBe(true);
    });

    it("hides the leftover section from other users once the stock sells out", async () => {
        const { result } = mountDetail(
            auction({
                is_active: false,
                status: "ended",
                leftover_enabled: true,
                leftover_quantity: 0,
                leftover_purchases: [],
            }),
            { user: bob },
        );
        await flushPromises();

        expect(result.myLeftoverPurchase.value).toBeNull();
        expect(result.shouldShowLeftoverSection.value).toBe(false);
    });

    it("keeps leftovers visible to admins after the round is closed", async () => {
        const { result } = mountDetail(
            auction({
                is_active: false,
                status: "ended",
                leftover_enabled: true,
                leftover_quantity: 2,
                leftover_price: "5.00",
                round: { id: 1, name: "R", status: "ended" },
            }),
            { user: seller },
        );
        await flushPromises();

        expect(result.roundIsClosed.value).toBe(true);
        expect(result.effectiveLeftoverAvailable.value).toBe(true);
        expect(result.shouldShowLeftoverSection.value).toBe(true);
        expect(result.auctionStatus.value?.label).toBe("Leftover sale");
    });
});
