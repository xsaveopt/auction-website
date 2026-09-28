import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises } from "@vue/test-utils";
import type { Auction, AuctionRound, OverrideSale, User } from "../lib/types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    route: { path: "/admin/leftovers", query: {} as Record<string, string> },
    router: { push: vi.fn(), replace: vi.fn() },
}));

vi.mock("../lib/api", () => ({ api: state.apiMock, ApiError: class extends Error {} }));
vi.mock("vue-router", () => ({
    useRoute: () => state.route,
    useRouter: () => state.router,
}));

import ConfirmDialog from "../components/ConfirmDialog.vue";
import AdminLeftovers from "./AdminLeftovers.vue";

const admin: User = { id: 1, username: "admin", is_admin: true };
const bob: User = { id: 2, username: "bob", is_admin: false };
const created: OverrideSale = {
    id: 9,
    user: { id: 2, username: "bob" },
    items: [
        {
            auction_id: 1,
            auction_title: "Chair",
            quantity: 2,
            price_per_item: "3.00",
            total: "6.00",
        },
        {
            auction_id: 2,
            auction_title: "Desk",
            quantity: 1,
            price_per_item: "10.00",
            total: "10.00",
        },
    ],
    total: "16.00",
    created_at: "2026-02-04T10:00:00Z",
};
const older: AuctionRound = { id: 1, name: "Spring", status: "ended" };
const newer: AuctionRound = { id: 2, name: "Autumn", status: "active" };

function auction(overrides: Partial<Auction>): Auction {
    return {
        id: 1,
        title: "Chair",
        starting_price: "5",
        quantity: 10,
        leftover_quantity: 4,
        ends_at: "2026-02-03T10:00:00Z",
        status: "ended",
        images: [],
        ...overrides,
    };
}

function mountLeftovers(
    leftovers: (url: string) => Auction[],
    active: AuctionRound | null = null,
    user: User = admin,
    sales: OverrideSale[] = [],
) {
    state.apiMock.mockImplementation(async (url: string, init?: RequestInit) => {
        if (init?.method === "POST") return { override_sale: created };
        if (init?.method === "DELETE") return {};
        if (url === "/rounds") return { rounds: [older, newer] };
        if (url === "/rounds/current") return { active };
        if (url.startsWith("/auctions/leftovers")) return { auctions: leftovers(url) };
        if (url === "/admin/override-sales") return { override_sales: sales };
        if (url === "/admin/users") return { users: [admin, bob] };
        throw new Error(`Unexpected ${url}`);
    });
    return mount(AdminLeftovers, {
        global: {
            provide: { user: ref(user), currencySymbol: ref("$") },
            stubs: { "router-link": { template: "<a><slot /></a>" } },
        },
    });
}

describe("AdminLeftovers", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
        state.router.replace.mockReset();
        state.route = { path: "/admin/leftovers", query: {} };
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("redirects non-admins to the home page", () => {
        mountLeftovers(() => [], null, { id: 3, username: "bob", is_admin: false });

        expect(state.router.push).toHaveBeenCalledWith("/");
    });

    it("loads leftovers for the active round and lists rounds newest first", async () => {
        const wrapper = mountLeftovers(() => [auction({})], newer);
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/leftovers?round_id=2");
        expect(state.router.replace).toHaveBeenCalledWith({
            path: "/admin/leftovers",
            query: { round_id: "2" },
        });
        expect(wrapper.findAll("option").map((o) => o.text())).toEqual([
            "All rounds",
            "Autumn",
            "Spring",
        ]);
    });

    it("renders per-row sold and leftover counts with totals", async () => {
        const wrapper = mountLeftovers(() => [
            auction({
                id: 1,
                title: "Chair",
                location: "Hall",
                category: { id: 1, name: "Furniture", slug: "f" },
            }),
            auction({
                id: 2,
                title: "Desk",
                quantity: 3,
                leftover_quantity: 1,
                starting_price: "12.5",
            }),
        ]);
        await flushPromises();

        const [chair, desk] = wrapper.findAll("tbody tr");
        expect(chair.findAll("td").map((td) => td.text())).toEqual([
            "",
            "ChairFurniture",
            "2026-02-03",
            "Hall",
            "10",
            "6",
            "4",
            "$5.00",
        ]);
        expect(desk.text()).toContain("$12.50");
        expect(desk.findAll("td")[3].text()).toBe("—");
        expect(
            wrapper
                .find("tfoot")
                .findAll("td")
                .map((td) => td.text()),
        ).toEqual(["Total", "13", "8", "5", ""]);
    });

    it("reloads when a different round is picked", async () => {
        const wrapper = mountLeftovers((url) =>
            url.endsWith("round_id=1") ? [auction({ title: "Old stock" })] : [],
        );
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/leftovers");
        expect(wrapper.text()).toContain("No leftover items");

        await wrapper.find("select").setValue("1");
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/auctions/leftovers?round_id=1");
        expect(state.router.replace).toHaveBeenLastCalledWith({
            path: "/admin/leftovers",
            query: { round_id: "1" },
        });
        expect(wrapper.text()).toContain("Old stock");
    });

    it("exports the visible leftovers as CSV", async () => {
        const blobs: Blob[] = [];
        vi.stubGlobal("URL", {
            ...URL,
            createObjectURL: (blob: Blob) => {
                blobs.push(blob);
                return "blob:csv";
            },
            revokeObjectURL: vi.fn(),
        });
        const click = vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => {});
        const wrapper = mountLeftovers(() => [auction({ title: 'Say "hi"', location: "Hall" })]);
        await flushPromises();

        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Export CSV")!
            .trigger("click");
        vi.unstubAllGlobals();

        expect(click).toHaveBeenCalled();
        const text = await blobs[0].text();
        expect(text.split("\n")).toEqual([
            '"Auction","Ended","Location","Total qty","Sold","Leftover","Starting price"',
            '"Say ""hi""","2026-02-03","Hall","10","6","4","5.00"',
        ]);
    });

    it("creates one override sale from several selected leftovers", async () => {
        const wrapper = mountLeftovers(() => [
            auction({ id: 1, title: "Chair", leftover_price: "3.75" }),
            auction({ id: 2, title: "Desk", leftover_quantity: 1, leftover_price: "9.00" }),
        ]);
        await flushPromises();

        expect(wrapper.text()).not.toContain("Create override sale");
        const boxes = wrapper.findAll('tbody input[type="checkbox"]');
        await boxes[0].setValue(true);
        await boxes[1].setValue(true);

        const form = wrapper.find("form");
        await form.find('[aria-label="Quantity of Chair"]').setValue("2");
        await form.find('[aria-label="Price per item of Chair"]').setValue("3");
        await form.find('[aria-label="Price per item of Desk"]').setValue("10");
        await form.find("select").setValue("bob");
        expect(form.text()).toContain("Total $16.00");

        await form.trigger("submit");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/admin/override-sales", {
            method: "POST",
            body: JSON.stringify({
                username: "bob",
                items: [
                    { auction_id: 1, quantity: 2, price_per_item: 3 },
                    { auction_id: 2, quantity: 1, price_per_item: 10 },
                ],
            }),
        });
        expect(wrapper.find("form").exists()).toBe(false);
        expect(wrapper.text()).toContain("#9 · bob");
        expect(wrapper.text()).toContain("2 × Chair at $3.00");
        expect(wrapper.find('a[href="/api/override-sales/9/quotes"]').exists()).toBe(true);
    });

    it("deletes an override sale after confirming", async () => {
        const wrapper = mountLeftovers(() => [], null, admin, [created]);
        await flushPromises();

        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Delete")!
            .trigger("click");
        const dialog = wrapper.findComponent(ConfirmDialog);
        expect(dialog.props("message")).toContain("Delete override sale #9 to bob?");
        dialog.vm.$emit("confirm");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/admin/override-sales/9", { method: "DELETE" });
        expect(wrapper.text()).not.toContain("#9 · bob");
    });
});
