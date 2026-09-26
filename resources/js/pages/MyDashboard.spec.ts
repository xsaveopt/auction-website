import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { defineComponent, h, reactive, ref } from "vue";
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

import MyDashboard from "./MyDashboard.vue";

const RouterLinkStub = defineComponent({
    props: { to: { type: String, required: true } },
    setup(props, { slots }) {
        return () => h("a", { href: props.to }, slots.default?.());
    },
});

const alice: User = { id: 10, username: "alice" };
const other: User = { id: 11, username: "bob" };

function auction(overrides: Partial<Auction> = {}): Auction {
    return {
        id: 1,
        title: "Laptop",
        starting_price: "10.00",
        current_price: "10.00",
        quantity: 1,
        ends_at: "2026-01-01T01:30:00Z",
        status: "active",
        images: [],
        ...overrides,
    };
}

function respond(responses: Record<string, unknown>) {
    state.apiMock.mockImplementation(async (url: string) => {
        if (!(url in responses)) throw new Error(`Unexpected ${url}`);
        return responses[url];
    });
}

function mountDashboard(user: User | null = alice) {
    return mount(MyDashboard, {
        global: {
            provide: {
                user: ref(user),
                currencySymbol: ref("€"),
                now: ref(new Date("2026-01-01T00:00:00Z")),
            },
            stubs: { "router-link": RouterLinkStub },
        },
    });
}

enableAutoUnmount(afterEach);

describe("MyDashboard", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
        state.router.replace.mockReset();
        state.route = reactive({ path: "/dashboard", query: {} });
    });

    it("sends guests to the login page", async () => {
        respond({
            "/rounds/current": { active: null, ended: [] },
            "/my-auctions": { active: [], won: [], lost: [] },
        });
        mountDashboard(null);
        await flushPromises();

        expect(state.router.push).toHaveBeenCalledWith("/login");
    });

    it("shows an empty state when the user has no activity", async () => {
        respond({
            "/rounds/current": { active: null, ended: [] },
            "/my-auctions": { active: [], won: [], lost: [] },
        });
        const wrapper = mountDashboard();
        await flushPromises();

        expect(wrapper.text()).toContain("No activity yet.");
        expect(wrapper.find("select").exists()).toBe(false);
        expect(state.router.replace).toHaveBeenCalledWith({ path: "/dashboard", query: {} });
    });

    it("loads the active round and renders every section with the user's own figures", async () => {
        respond({
            "/rounds/current": {
                active: { id: 5, name: "Spring", status: "active" },
                ended: [{ id: 4, name: "Winter", status: "ended" }],
            },
            "/my-auctions?round_id=5": {
                active: [
                    auction({
                        id: 1,
                        title: "Winning lot",
                        current_price: "20",
                        bids: [
                            { id: 1, amount: "22", quantity: 1, won_quantity: 1, user: alice },
                            { id: 2, amount: "30", quantity: 1, won_quantity: 1, user: other },
                        ],
                    }),
                    auction({
                        id: 2,
                        title: "Outbid lot",
                        bids: [{ id: 3, amount: "5", quantity: 1, won_quantity: 0, user: alice }],
                    }),
                ],
                won: [
                    auction({
                        id: 3,
                        title: "Won lot",
                        status: "ended",
                        bids: [
                            {
                                id: 4,
                                amount: "15",
                                price: "12.5",
                                quantity: 3,
                                won_quantity: 2,
                                user: alice,
                            },
                        ],
                    }),
                ],
                lost: [
                    auction({
                        id: 4,
                        title: "Lost lot",
                        status: "ended",
                        current_price: "50",
                        ends_at: "2025-12-20T10:00:00Z",
                        bids: [{ id: 5, amount: "40", quantity: 1, user: alice }],
                    }),
                ],
                purchased: [
                    auction({
                        id: 6,
                        title: "Offer lot",
                        status: "ended",
                        leftover_price_offers: [
                            {
                                id: 1,
                                auction_id: 6,
                                user_id: 11,
                                quantity: 9,
                                offered_price_per_item: "1",
                                status: "accepted",
                                user: other,
                            },
                            {
                                id: 2,
                                auction_id: 6,
                                user_id: 10,
                                quantity: 3,
                                offered_price_per_item: "4",
                                status: "accepted",
                                user: alice,
                            },
                        ],
                    }),
                ],
            },
        });
        const wrapper = mountDashboard();
        await flushPromises();

        expect(state.router.replace).toHaveBeenCalledWith({
            path: "/dashboard",
            query: { round_id: "5" },
        });
        expect((wrapper.find("select").element as HTMLSelectElement).value).toBe("5");
        expect(wrapper.findAll("option").map((o) => o.text())).toEqual([
            "All rounds",
            "Spring",
            "Winter",
        ]);

        const card = (id: number) => wrapper.get(`a[href='/auctions/${id}']`).text();

        expect(card(1)).toContain("Winning");
        expect(card(1)).toContain("Your bid: €22.00");
        expect(card(1)).toContain("1h 30m left");
        expect(card(1)).toContain("€20.00");
        expect(card(2)).toContain("Outbid");
        expect(card(3)).toContain("Won 2 items @ €12.50");
        expect(card(3)).toContain("Total: €25.00");
        expect(card(4)).toContain("Ended 2025-12-20");
        expect(card(4)).toContain("Your bid: €40.00");
        expect(card(4)).toContain("Final: €50.00");
        expect(card(6)).toContain("Bought 3 items @ €4.00");
        expect(card(6)).toContain("(price offer)");
        expect(card(6)).toContain("Total: €12.00");
    });

    it("honours round_id from the query and reloads when the round changes", async () => {
        state.route.query = { round_id: "4" };
        respond({
            "/rounds/current": {
                active: { id: 5, name: "Spring", status: "active" },
                ended: [{ id: 4, name: "Winter", status: "ended" }],
            },
            "/my-auctions?round_id=4": {
                active: [],
                won: [],
                lost: [],
                purchased: [
                    auction({
                        id: 7,
                        title: "Direct buy",
                        leftover_purchases: [
                            { id: 1, quantity: 1, price_per_item: "8", user: alice },
                        ],
                    }),
                ],
            },
            "/my-auctions": { active: [], won: [], lost: [] },
        });
        const wrapper = mountDashboard();
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/my-auctions?round_id=4");
        expect(wrapper.text()).toContain("Bought 1 item @ €8.00");
        expect(wrapper.text()).not.toContain("(price offer)");

        await wrapper.findAll("option")[0].setValue();
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/my-auctions");
        expect(state.router.replace).toHaveBeenLastCalledWith({ path: "/dashboard", query: {} });
        expect(wrapper.text()).toContain("No activity yet.");
    });
});
