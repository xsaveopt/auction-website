import { describe, it, expect, vi, beforeEach } from "vitest";
import { defineComponent, h, ref } from "vue";
import { mount, flushPromises, type VueWrapper } from "@vue/test-utils";
import type { Auction, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    route: { path: "/admin/results", query: {} as Record<string, string | undefined> },
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

import AdminResults from "./AdminResults.vue";

const RouterLinkStub = defineComponent({
    props: { to: { type: String, required: true } },
    setup(props, { slots }) {
        return () => h("a", { href: props.to }, slots.default?.());
    },
});

const admin: User = { id: 1, username: "admin", is_admin: true };
const alice: User = { id: 2, username: "alice" };
const bob: User = { id: 3, username: "bob" };
const carol: User = { id: 4, username: "carol" };

const chairs: Auction = {
    id: 10,
    title: "Office chairs",
    starting_price: "20.00",
    quantity: 5,
    bid_count: 3,
    ends_at: "2026-01-15T16:00:00Z",
    status: "ended",
    images: [],
    bids: [
        { id: 100, amount: "30.00", price: "25.00", quantity: 2, won_quantity: 2, user: alice },
        { id: 101, amount: "22.00", quantity: 1, won_quantity: 1, user: bob },
        { id: 102, amount: "18.00", quantity: 3, won_quantity: 0, user: carol },
    ],
    leftover_purchases: [{ id: 200, quantity: 1, price_per_item: "15.00", user: carol }],
    leftover_price_offers: [
        {
            id: 300,
            auction_id: 10,
            user_id: 3,
            quantity: 1,
            offered_price_per_item: "12.00",
            status: "accepted",
            user: bob,
        },
        {
            id: 301,
            auction_id: 10,
            user_id: 4,
            quantity: 1,
            offered_price_per_item: "9.00",
            status: "rejected",
            user: carol,
        },
    ],
};

const unsold: Auction = {
    id: 11,
    title: "Broken printer",
    starting_price: "5.00",
    quantity: 1,
    bid_count: 0,
    ends_at: "2026-01-15T16:00:00Z",
    status: "ended",
    images: [],
    bids: [],
};

const summary = {
    revenue_after_tax: 50,
    total_value_after_tax: 200,
    revenue_before_tax: 60.5,
    total_value_before_tax: 242,
    ended_auctions: 2,
    auctions_with_sales: 1,
    sold_items: 6,
};

async function mountResults(auctions: Auction[], query: Record<string, string> = {}) {
    state.route.query = { ...query };
    state.apiMock.mockImplementation(async (url: string) => {
        if (url === "/rounds") return { rounds: [{ id: 7, name: "Winter", status: "ended" }] };
        if (url === "/rounds/current") return { active: null };
        if (url.startsWith("/auctions/ended")) return { auctions, summary };
        throw new Error(`Unexpected ${url}`);
    });
    const wrapper = mount(AdminResults, {
        props: { active: true },
        global: {
            provide: { user: ref(admin), currencySymbol: ref("€") },
            stubs: { "router-link": RouterLinkStub },
        },
    });
    await flushPromises();
    return wrapper;
}

function button(wrapper: VueWrapper, label: string) {
    const found = wrapper.findAll("button").find((b) => b.text().startsWith(label));
    expect(found).toBeDefined();
    return found!;
}

describe("AdminResults", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.replace.mockReset();
    });

    it("renders the revenue summary for the loaded results", async () => {
        const wrapper = await mountResults([chairs, unsold]);

        expect(wrapper.text()).toContain("Revenue after tax");
        expect(wrapper.text()).toContain("€50.00 of €200.00 earned");
        expect(wrapper.text()).toContain("25.0% of all auction value");
        expect(wrapper.text()).toContain("Revenue before tax");
        expect(wrapper.text()).toContain("Across 2 ended auctions, 1 with winner, 6 items sold.");
        expect(wrapper.findAll("option").map((o) => o.text())).toEqual(["All rounds", "Winter"]);
    });

    it("lists winners per user with their totals, highest first", async () => {
        const wrapper = await mountResults([chairs]);

        const rows = wrapper.findAll("div.font-semibold").map((d) => d.text());
        expect(rows).toEqual(["alice", "bob", "carol"]);
        expect(wrapper.text()).toContain("2 items · €50.00");
        expect(wrapper.text()).toContain("2 items · €34.00");
        expect(wrapper.text()).toContain("1 item · €15.00");
    });

    it("expands a user to show each item and how it was won", async () => {
        const wrapper = await mountResults([chairs]);

        await button(wrapper, "bob").trigger("click");

        const cells = wrapper.findAll("tbody tr").map((r) => r.text());
        expect(cells).toHaveLength(2);
        expect(cells[0]).toContain("Office chairs");
        expect(cells[0]).toContain("Bid win");
        expect(cells[0]).toContain("€22.00");
        expect(cells[1]).toContain("Price offer");
        expect(cells[1]).toContain("€12.00");
        expect(wrapper.find("tbody a").attributes("href")).toBe("/auctions/10");
    });

    it("shows only auctions with sales in the by-auction view", async () => {
        const wrapper = await mountResults([{ ...chairs, leftover_price_offers: [] }, unsold], {
            view: "auctions",
        });

        expect(wrapper.text()).toContain("Office chairs");
        expect(wrapper.text()).not.toContain("Broken printer");
        expect(wrapper.text()).toContain("4 sold · €87.00");
        expect(wrapper.text()).toContain("Ended 2026-01-15 16:00 · 5 items · 3 bids");
    });

    it("counts accepted price offers in the collapsed auction header", async () => {
        const wrapper = await mountResults([chairs], { view: "auctions" });

        expect(wrapper.text()).toContain("5 sold · €99.00");
    });

    it("shows an empty state in the by-auction view when nothing sold", async () => {
        const wrapper = await mountResults([unsold], { view: "auctions" });

        expect(wrapper.text()).toContain("No auctions with sales yet.");
    });

    it("expands an auction to show winners, losing bids and leftover sales", async () => {
        const wrapper = await mountResults([chairs], { view: "auctions" });

        await button(wrapper, "Office chairs").trigger("click");

        const tables = wrapper.findAll("table");
        expect(tables).toHaveLength(2);

        const winnerRows = tables[0].findAll("tbody tr").map((r) => r.text());
        expect(winnerRows[0]).toContain("alice");
        expect(winnerRows[0]).toContain("2 items");
        expect(winnerRows[0]).toContain("€50.00");
        expect(winnerRows[1]).toContain("bob");
        expect(tables[0].find("tfoot").text()).toContain("3 items");
        expect(tables[0].find("tfoot").text()).toContain("€72.00");

        expect(wrapper.text()).toContain("Unsuccessful bids");
        expect(wrapper.text()).toContain("carol — €18.00 for 3");

        const leftoverRows = tables[1].findAll("tbody tr").map((r) => r.text());
        expect(leftoverRows).toHaveLength(2);
        expect(leftoverRows[0]).toContain("Leftover buy");
        expect(leftoverRows[1]).toContain("Price offer");
        expect(tables[1].find("tfoot").text()).toContain("2 items");
        expect(tables[1].find("tfoot").text()).toContain("€27.00");

        await button(wrapper, "Office chairs").trigger("click");
        expect(wrapper.findAll("table")).toHaveLength(0);
    });

    it("switches between the user and auction views and syncs the query", async () => {
        const wrapper = await mountResults([chairs]);

        await button(wrapper, "By Auction").trigger("click");

        expect(state.router.replace).toHaveBeenLastCalledWith({
            path: "/admin/results",
            query: { view: "auctions" },
        });
        expect(wrapper.text()).toContain("Ended 2026-01-15 16:00");
        expect(wrapper.text()).not.toContain("2 items · €50.00");
    });
});
