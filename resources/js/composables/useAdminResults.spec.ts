import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { defineComponent, h, reactive, ref, type Ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import type { Auction, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    route: {} as { path: string; query: Record<string, string | undefined> },
    router: { push: vi.fn(), replace: vi.fn() },
}));

vi.mock("../api", () => ({ api: state.apiMock, ApiError: class extends Error {} }));
vi.mock("vue-router", () => ({
    useRoute: () => state.route,
    useRouter: () => state.router,
}));

import { useAdminResults } from "./useAdminResults";

type Results = ReturnType<typeof useAdminResults>;

const admin: User = { id: 1, username: "admin", is_admin: true };

function auction(overrides: Partial<Auction> = {}): Auction {
    return {
        id: 1,
        title: "Laptop",
        starting_price: "10.00",
        quantity: 1,
        ends_at: "2026-01-01T10:00:00Z",
        status: "ended",
        images: [],
        bids: [],
        ...overrides,
    };
}

function mountResults(
    options: { user?: User | null; active?: boolean; responses?: Record<string, unknown> } = {},
) {
    const responses: Record<string, unknown> = {
        "/rounds": { rounds: [] },
        "/rounds/current": { active: null },
        ...options.responses,
    };
    state.apiMock.mockImplementation(async (url: string) => {
        if (url in responses) return responses[url];
        if (url.startsWith("/auctions/ended")) {
            return responses["/auctions/ended"] ?? { auctions: [], summary: null };
        }
        throw new Error(`Unexpected ${url}`);
    });

    const user: Ref<User | null> = ref(options.user === undefined ? admin : options.user);
    const props = reactive({ active: options.active ?? true });
    let result!: Results;
    const Harness = defineComponent({
        setup() {
            result = useAdminResults(props);
            return () => h("div");
        },
    });
    const wrapper = mount(Harness, {
        global: { provide: { user, currencySymbol: ref("€") } },
    });
    return {
        wrapper,
        props,
        get result() {
            return result;
        },
    };
}

enableAutoUnmount(afterEach);

describe("useAdminResults", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
        state.router.replace.mockReset();
        state.route = reactive({ path: "/admin/results", query: {} });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("redirects non-admin users to the home page", async () => {
        mountResults({ user: { id: 2, username: "bob", is_admin: false } });
        await flushPromises();

        expect(state.router.push).toHaveBeenCalledWith("/");
    });

    it("does not redirect admins", async () => {
        mountResults();
        await flushPromises();

        expect(state.router.push).not.toHaveBeenCalled();
    });

    it("loads rounds sorted descending and the ended auctions for the active round", async () => {
        const { result } = mountResults({
            responses: {
                "/rounds": {
                    rounds: [
                        { id: 1, name: "R1", status: "ended" },
                        { id: 3, name: "R3", status: "active" },
                        { id: 2, name: "R2", status: "ended" },
                    ],
                },
                "/rounds/current": { active: { id: 3, name: "R3", status: "active" } },
                "/auctions/ended": {
                    auctions: [auction()],
                    summary: {
                        revenue_after_tax: 50,
                        total_value_after_tax: 100,
                        revenue_before_tax: 40,
                        total_value_before_tax: 160,
                    },
                },
            },
        });
        await flushPromises();

        expect(result.allRounds.value.map((r) => r.id)).toEqual([3, 2, 1]);
        expect(result.selectedRoundId.value).toBe(3);
        expect(state.apiMock).toHaveBeenCalledWith("/auctions/ended?round_id=3");
        expect(result.auctions.value).toHaveLength(1);
        expect(result.loading.value).toBe(false);
        expect(state.router.replace).toHaveBeenCalledWith({
            path: "/admin/results",
            query: { round_id: "3" },
        });
    });

    it("uses the round_id and view from the query string", async () => {
        state.route.query = { round_id: "7", view: "auctions" };
        const { result } = mountResults({
            responses: { "/rounds/current": { active: { id: 9, name: "R9", status: "active" } } },
        });
        await flushPromises();

        expect(result.view.value).toBe("auctions");
        expect(result.selectedRoundId.value).toBe(7);
        expect(state.apiMock).toHaveBeenCalledWith("/auctions/ended?round_id=7");
    });

    it("does not touch the query string when the tab is inactive", async () => {
        mountResults({ active: false });
        await flushPromises();

        expect(state.router.replace).not.toHaveBeenCalled();
        expect(state.apiMock).toHaveBeenCalledWith("/auctions/ended");
    });

    it("reloads results and resets expansion when the round changes", async () => {
        const { result } = mountResults();
        await flushPromises();
        result.toggle(1);
        expect(result.expanded.value[1]).toBe(true);

        result.selectedRoundId.value = 4;
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/ended?round_id=4");
        expect(result.expanded.value).toEqual({});
        expect(result.userQuoteUrl(5)).toBe("/api/users/5/quotes?round_id=4");
    });

    it("aggregates winning bids, leftover purchases and accepted offers per user", async () => {
        const alice: User = { id: 10, username: "alice" };
        const bob: User = { id: 11, username: "bob" };
        const { result } = mountResults({
            responses: {
                "/auctions/ended": {
                    summary: null,
                    auctions: [
                        auction({
                            id: 1,
                            bids: [
                                {
                                    id: 100,
                                    amount: "20.00",
                                    price: "15.00",
                                    quantity: 2,
                                    won_quantity: 2,
                                    user: alice,
                                },
                                {
                                    id: 101,
                                    amount: "5.00",
                                    quantity: 1,
                                    won_quantity: 0,
                                    user: bob,
                                },
                            ],
                            leftover_purchases: [
                                { id: 200, quantity: 1, price_per_item: "7.50", user: bob },
                            ],
                        }),
                        auction({
                            id: 2,
                            title: "Monitor",
                            leftover_price_offers: [
                                {
                                    id: 300,
                                    auction_id: 2,
                                    user_id: 11,
                                    quantity: 2,
                                    offered_price_per_item: "4.00",
                                    status: "accepted",
                                    user: bob,
                                },
                                {
                                    id: 301,
                                    auction_id: 2,
                                    user_id: 10,
                                    quantity: 1,
                                    offered_price_per_item: "99.00",
                                    status: "pending",
                                    user: alice,
                                },
                            ],
                        }),
                        auction({ id: 3, title: "Unsold" }),
                    ],
                },
            },
        });
        await flushPromises();

        const summaries = result.userSummaries.value;
        expect(summaries.map((s) => s.username)).toEqual(["alice", "bob"]);
        expect(summaries[0]).toMatchObject({ totalItems: 2, totalOwed: 30 });
        expect(summaries[1]).toMatchObject({ totalItems: 3, totalOwed: 15.5 });
        expect(summaries[1].items.map((i) => i.isLeftover)).toEqual([true, true]);
        expect(summaries[1].items[1]).toMatchObject({ offerId: 300, fromPriceOffer: true });

        expect(result.auctionsWithSales.value.map((a) => a.id)).toEqual([1, 2]);
        expect(result.winners(result.auctions.value[0]).map((b) => b.id)).toEqual([100]);
        expect(result.hasAnyWinners()).toBe(true);
    });

    it("builds stats cards from the summary", async () => {
        const { result } = mountResults({
            responses: {
                "/auctions/ended": {
                    auctions: [],
                    summary: {
                        revenue_after_tax: 50,
                        total_value_after_tax: 200,
                        revenue_before_tax: 0,
                        total_value_before_tax: 0,
                    },
                },
            },
        });
        await flushPromises();

        const cards = result.statsCards.value;
        expect(cards).toHaveLength(2);
        expect(cards[0].value).toBe("€50.00 of €200.00 earned");
        expect(cards[0].progress).toBe(25);
        expect(cards[0].detail).toBe("25.0% of all auction value");
        expect(cards[1].progress).toBe(0);
    });

    it("returns no stats cards without a summary", async () => {
        const { result } = mountResults();
        await flushPromises();

        expect(result.statsCards.value).toEqual([]);
        expect(result.hasAnyWinners()).toBe(false);
    });

    it("formats helpers and quote urls", async () => {
        const { result } = mountResults();
        await flushPromises();

        expect(result.formatDate("2026-03-04T05:06:07Z")).toBe("2026-03-04 05:06");
        expect(result.formatDate(null)).toBe("");
        expect(result.formatMoney("3")).toBe("€3.00");
        expect(result.formatMoney(null)).toBe("€0.00");
        expect(result.quoteUrl(1, 2)).toBe("/api/auctions/1/quotes/2");
        expect(result.leftoverQuoteUrl(1, 2)).toBe("/api/auctions/1/leftover-purchases/2/quotes");
        expect(result.priceOfferQuoteUrl(1, 2)).toBe(
            "/api/auctions/1/leftover-price-offers/2/quotes",
        );
        expect(result.userQuoteUrl(9)).toBe("/api/users/9/quotes");

        result.toggleUser("alice");
        expect(result.expandedUsers.value.alice).toBe(true);
        result.toggleUser("alice");
        expect(result.expandedUsers.value.alice).toBe(false);
    });

    it("opens a quote window per winning bid", async () => {
        const openMock = vi.spyOn(window, "open").mockImplementation(() => null);
        const { result } = mountResults({
            responses: {
                "/auctions/ended": {
                    summary: null,
                    auctions: [
                        auction({
                            id: 4,
                            bids: [
                                {
                                    id: 40,
                                    amount: "1",
                                    quantity: 1,
                                    won_quantity: 1,
                                    user: { id: 1, username: "a" },
                                },
                                {
                                    id: 41,
                                    amount: "1",
                                    quantity: 1,
                                    won_quantity: 1,
                                    user: { id: 2, username: "b" },
                                },
                            ],
                        }),
                    ],
                },
            },
        });
        await flushPromises();

        result.downloadAllQuotes(result.auctions.value[0]);
        expect(openMock).toHaveBeenCalledTimes(2);
        expect(openMock).toHaveBeenCalledWith("/api/auctions/4/quotes/40", "_blank");

        openMock.mockClear();
        result.downloadEveryQuote();
        expect(openMock).toHaveBeenCalledTimes(2);

        openMock.mockClear();
        result.downloadAllUserQuotes();
        expect(openMock.mock.calls.map((c) => c[0])).toEqual([
            "/api/users/1/quotes",
            "/api/users/2/quotes",
        ]);
    });

    it("follows the view query when it changes and resyncs when reactivated", async () => {
        const { result, props } = mountResults({ active: false });
        await flushPromises();

        state.route.query = { view: "auctions", round_id: "12" };
        await flushPromises();
        expect(result.view.value).toBe("auctions");

        props.active = true;
        await flushPromises();

        expect(result.selectedRoundId.value).toBe(12);
        expect(state.router.replace).toHaveBeenCalledWith({
            path: "/admin/results",
            query: expect.objectContaining({ view: "auctions" }),
        });
    });
});
