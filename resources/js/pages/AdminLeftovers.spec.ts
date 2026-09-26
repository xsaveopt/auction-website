import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises } from "@vue/test-utils";
import type { Auction, AuctionRound, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    route: { path: "/admin/leftovers", query: {} as Record<string, string> },
    router: { push: vi.fn(), replace: vi.fn() },
}));

vi.mock("../api", () => ({ api: state.apiMock, ApiError: class extends Error {} }));
vi.mock("vue-router", () => ({
    useRoute: () => state.route,
    useRouter: () => state.router,
}));

import AdminLeftovers from "./AdminLeftovers.vue";

const admin: User = { id: 1, username: "admin", is_admin: true };
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
) {
    state.apiMock.mockImplementation(async (url: string) => {
        if (url === "/rounds") return { rounds: [older, newer] };
        if (url === "/rounds/current") return { active };
        if (url.startsWith("/auctions/leftovers")) return { auctions: leftovers(url) };
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
            "ChairFurniture",
            "2026-02-03",
            "Hall",
            "10",
            "6",
            "4",
            "$5.00",
        ]);
        expect(desk.text()).toContain("$12.50");
        expect(desk.findAll("td")[2].text()).toBe("—");
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
});
