import { describe, it, expect, vi, beforeEach } from "vitest";
import { defineComponent, h, ref } from "vue";
import { mount, flushPromises, type VueWrapper } from "@vue/test-utils";
import type { Auction, AuctionRound, LeftoverPriceOffer, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    route: { path: "/admin/price-offers", query: {} as Record<string, string | undefined> },
    router: { push: vi.fn(), replace: vi.fn() },
}));

vi.mock("../api", () => {
    class ApiError extends Error {
        status: number;
        data: { message?: string; errors?: Record<string, string[]> };
        constructor(status: number, data: { message?: string; errors?: Record<string, string[]> }) {
            super(data.message ?? "Request failed");
            this.name = "ApiError";
            this.status = status;
            this.data = data;
        }
    }
    return { api: state.apiMock, ApiError };
});
vi.mock("vue-router", () => ({
    useRoute: () => state.route,
    useRouter: () => state.router,
}));

import AdminPriceOffers from "./AdminPriceOffers.vue";
import { ApiError } from "../api";

const RouterLinkStub = defineComponent({
    props: { to: { type: String, required: true } },
    setup(props, { slots }) {
        return () => h("a", { href: props.to }, slots.default?.());
    },
});

const admin: User = { id: 1, username: "admin", is_admin: true };

const lamp: Auction = {
    id: 10,
    title: "Desk lamp",
    starting_price: "20.00",
    quantity: 5,
    ends_at: "2026-01-01T10:00:00Z",
    status: "ended",
    leftover_price: "15.00",
    images: [],
};

function offer(overrides: Partial<LeftoverPriceOffer> = {}): LeftoverPriceOffer {
    return {
        id: 1,
        auction_id: lamp.id,
        user_id: 2,
        quantity: 1,
        offered_price_per_item: "12.00",
        status: "pending",
        created_at: "2026-02-01T09:30:00Z",
        user: { id: 2, username: "alice" },
        auction: lamp,
        ...overrides,
    };
}

function mountOffers(
    options: {
        offers?: LeftoverPriceOffer[];
        rounds?: AuctionRound[];
        active?: AuctionRound | null;
    } = {},
) {
    state.apiMock.mockImplementation(async (url: string, init?: RequestInit) => {
        if (url === "/rounds") return { rounds: options.rounds ?? [] };
        if (url === "/rounds/current") return { active: options.active ?? null };
        if (url.startsWith("/admin/leftover-price-offers") && !init) {
            return { offers: options.offers ?? [] };
        }
        return {};
    });
    return mount(AdminPriceOffers, {
        global: {
            provide: { user: ref(admin), currencySymbol: ref("€") },
            stubs: { "router-link": RouterLinkStub },
        },
    });
}

function button(wrapper: VueWrapper, label: string) {
    const found = wrapper.findAll("button").find((b) => b.text() === label);
    expect(found).toBeDefined();
    return found!;
}

describe("AdminPriceOffers", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.replace.mockReset();
        state.route.query = {};
    });

    it("loads offers for the active round and reflects it in the query", async () => {
        const wrapper = mountOffers({
            rounds: [
                { id: 3, name: "Spring", status: "active" },
                { id: 4, name: "Summer", status: "ended" },
            ],
            active: { id: 3, name: "Spring", status: "active" },
        });
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/admin/leftover-price-offers?round_id=3");
        expect(state.router.replace).toHaveBeenCalledWith({
            path: "/admin/price-offers",
            query: { round_id: "3" },
        });
        const options = wrapper.findAll("option").map((o) => o.text());
        expect(options).toEqual(["All rounds", "Summer", "Spring"]);
        expect(wrapper.text()).toContain("No pending price offers.");
    });

    it("reloads offers when a different round is selected", async () => {
        const wrapper = mountOffers({
            rounds: [
                { id: 3, name: "Spring", status: "ended" },
                { id: 4, name: "Summer", status: "ended" },
            ],
        });
        await flushPromises();
        expect(state.apiMock).toHaveBeenCalledWith("/admin/leftover-price-offers");

        await wrapper.find("select").setValue("4");
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/admin/leftover-price-offers?round_id=4");
        expect(state.router.replace).toHaveBeenLastCalledWith({
            path: "/admin/price-offers",
            query: { round_id: "4" },
        });
    });

    it("groups offers per auction, highest price first, with line totals", async () => {
        const wrapper = mountOffers({
            offers: [
                offer({ id: 1, offered_price_per_item: "8.00" }),
                offer({
                    id: 2,
                    quantity: 3,
                    offered_price_per_item: "12.50",
                    user: { id: 3, username: "bob" },
                }),
            ],
        });
        await flushPromises();

        expect(wrapper.find("a").attributes("href")).toBe("/auctions/10");
        expect(wrapper.text()).toContain("Desk lamp");
        expect(wrapper.text()).toContain("list €15.00");
        const lines = wrapper.findAll("p.text-sm.text-gray-600").map((p) => p.text());
        expect(lines[0]).toContain("bob");
        expect(lines[0]).toContain("3 × €12.50");
        expect(lines[0]).toContain("= €37.50");
        expect(lines[1]).toContain("alice");
        expect(wrapper.text()).not.toContain("Tied at");
    });

    it("flags tied offers and requests a rebid for the whole tied group", async () => {
        const wrapper = mountOffers({
            offers: [
                offer({ id: 1 }),
                offer({ id: 2, user: { id: 3, username: "bob" } }),
                offer({ id: 3, offered_price_per_item: "5.00" }),
            ],
        });
        await flushPromises();

        expect(wrapper.text()).toContain("Tied at €12.00");
        expect(wrapper.text()).toContain("2 offers tied");

        await button(wrapper, "Request Rebid").trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/admin/leftover-price-offers/request-rebid", {
            method: "POST",
            body: JSON.stringify({ offer_ids: [1, 2] }),
        });
        expect(wrapper.text()).toContain("awaiting rebid from tied users");
        expect(wrapper.findAll("button").some((b) => b.text() === "Request Rebid")).toBe(false);
        expect(wrapper.text().match(/Awaiting rebid/g)).toHaveLength(2);
    });

    it.each([
        ["Accept", "/admin/leftover-price-offers/1/accept", { method: "POST" }],
        ["Reject", "/admin/leftover-price-offers/1/reject", { method: "POST" }],
        ["Delete", "/admin/leftover-price-offers/1", { method: "DELETE" }],
    ])("%s calls the API and removes the offer", async (label, url, init) => {
        const wrapper = mountOffers({ offers: [offer()] });
        await flushPromises();

        await button(wrapper, label).trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith(url, init);
        expect(wrapper.text()).toContain("No pending price offers.");
    });

    it("keeps the offer and shows the API error when accepting fails", async () => {
        const wrapper = mountOffers({ offers: [offer()] });
        await flushPromises();

        state.apiMock.mockRejectedValueOnce(new ApiError(422, { message: "Not enough stock" }));
        await button(wrapper, "Accept").trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain("Not enough stock");
        expect(wrapper.text()).toContain("alice");
    });
});
