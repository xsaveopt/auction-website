import { describe, it, expect, vi, beforeEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises } from "@vue/test-utils";
import type { Auction, AuctionRound, Category, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    route: { path: "/admin/auctions", query: {} as Record<string, string> },
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

import AdminAuctions from "./AdminAuctions.vue";
import { ApiError } from "../api";

const admin: User = { id: 1, username: "admin", is_admin: true };
const spring: AuctionRound = { id: 1, name: "Spring", status: "active" };
const autumn: AuctionRound = { id: 2, name: "Autumn", status: "ended" };
const laptops: Category = { id: 7, name: "Laptops", slug: "laptops" };

function auction(overrides: Partial<Auction>): Auction {
    return {
        id: 1,
        title: "Item",
        starting_price: "10.00",
        quantity: 1,
        ends_at: "2026-05-01T10:00:00Z",
        status: "active",
        is_active: true,
        images: [],
        round: null,
        ...overrides,
    };
}

function mountAuctions(
    auctions: Auction[],
    options: { active?: AuctionRound | null; bulk?: () => Promise<unknown> } = {},
) {
    state.apiMock.mockImplementation(async (url: string) => {
        if (url === "/auctions") return { auctions };
        if (url === "/rounds") return { rounds: [spring, autumn] };
        if (url === "/rounds/current") return { active: options.active ?? null };
        if (url === "/categories") return { categories: [laptops] };
        if (url === "/admin/auctions/bulk") return options.bulk ? options.bulk() : {};
        throw new Error(`Unexpected ${url}`);
    });
    return mount(AdminAuctions, {
        global: {
            provide: { user: ref(admin), currencySymbol: ref("€") },
            stubs: { "router-link": { template: "<a><slot /></a>" } },
        },
    });
}

function rows(wrapper: ReturnType<typeof mountAuctions>) {
    return wrapper.findAll("tbody tr");
}

function applyButton(wrapper: ReturnType<typeof mountAuctions>) {
    return wrapper.findAll("button").find((b) => ["Apply", "Saving…"].includes(b.text()))!;
}

function bulkBody() {
    const call = state.apiMock.mock.calls.find((c) => c[0] === "/admin/auctions/bulk");
    expect(call?.[1]?.method).toBe("PATCH");
    return JSON.parse(call?.[1]?.body as string);
}

const selects = (wrapper: ReturnType<typeof mountAuctions>) => wrapper.findAll("select");

describe("AdminAuctions", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
        state.router.replace.mockReset();
        state.route = { path: "/admin/auctions", query: {} };
    });

    it("redirects non-admins to the home page", () => {
        state.apiMock.mockResolvedValue({ auctions: [], rounds: [], categories: [] });
        mount(AdminAuctions, {
            global: {
                provide: { user: ref({ id: 2, username: "bob" }), currencySymbol: ref("€") },
                stubs: { "router-link": true },
            },
        });

        expect(state.router.push).toHaveBeenCalledWith("/");
    });

    it("defaults the round filter to the active round and syncs it to the query", async () => {
        const wrapper = mountAuctions(
            [
                auction({ id: 1, title: "Spring laptop", round: spring }),
                auction({ id: 2, title: "Autumn chair", round: autumn }),
            ],
            { active: spring },
        );
        await flushPromises();

        expect(rows(wrapper)).toHaveLength(1);
        expect(rows(wrapper)[0].text()).toContain("Spring laptop");
        expect(rows(wrapper)[0].text()).toContain("€10.00");
        expect(state.router.replace).toHaveBeenCalledWith({
            path: "/admin/auctions",
            query: { round_id: "1" },
        });
    });

    it("respects a round_id already present in the query", async () => {
        state.route.query = { round_id: "2" };
        const wrapper = mountAuctions(
            [
                auction({ id: 1, title: "Spring laptop", round: spring }),
                auction({ id: 2, title: "Autumn chair", round: autumn }),
            ],
            { active: spring },
        );
        await flushPromises();

        expect(rows(wrapper)).toHaveLength(1);
        expect(rows(wrapper)[0].text()).toContain("Autumn chair");
    });

    it("filters by status and unassigned round", async () => {
        const wrapper = mountAuctions([
            auction({ id: 1, title: "Live", is_active: true }),
            auction({ id: 2, title: "Done", is_active: false, status: "ended", round: spring }),
            auction({ id: 3, title: "Pulled", is_active: false, status: "cancelled" }),
        ]);
        await flushPromises();

        const [statusFilter, roundFilter] = selects(wrapper);
        await statusFilter.setValue("ended");
        expect(rows(wrapper).map((r) => r.text())).toEqual([expect.stringContaining("Done")]);

        await statusFilter.setValue("cancelled");
        expect(rows(wrapper).map((r) => r.text())).toEqual([expect.stringContaining("Pulled")]);

        await statusFilter.setValue("all");
        await roundFilter.setValue("unassigned");
        expect(rows(wrapper)).toHaveLength(2);
        expect(wrapper.text()).not.toContain("Done");
        expect(state.router.replace).toHaveBeenLastCalledWith({
            path: "/admin/auctions",
            query: { round_id: "unassigned" },
        });
    });

    it("assigns the selected auctions to a round", async () => {
        const wrapper = mountAuctions([
            auction({ id: 1, title: "A" }),
            auction({ id: 2, title: "B" }),
            auction({ id: 3, title: "C" }),
        ]);
        await flushPromises();

        await rows(wrapper)[0].trigger("click");
        await rows(wrapper)[2].trigger("click");
        await selects(wrapper)[3].setValue("2");
        await applyButton(wrapper).trigger("click");
        await flushPromises();

        expect(bulkBody()).toEqual({ action: "assign_round", auction_ids: [1, 3], round_id: 2 });
        expect(wrapper.text()).toContain("2 auctions updated.");
        expect(rows(wrapper)[0].text()).toContain("Autumn");
        expect(rows(wrapper)[1].text()).not.toContain("Autumn");
    });

    it("selects every visible auction with the header checkbox", async () => {
        const wrapper = mountAuctions([auction({ id: 1 }), auction({ id: 2 })]);
        await flushPromises();

        await wrapper.find("thead input[type='checkbox']").trigger("change");
        await selects(wrapper)[2].setValue("assign_location");
        await wrapper
            .find("input[placeholder='Location (leave blank to clear)']")
            .setValue(" Hall ");
        await applyButton(wrapper).trigger("click");
        await flushPromises();

        expect(bulkBody()).toEqual({
            action: "assign_location",
            auction_ids: [1, 2],
            location: "Hall",
        });
    });

    it("assigns a category to the selected auctions", async () => {
        const wrapper = mountAuctions([auction({ id: 4 })]);
        await flushPromises();

        await rows(wrapper)[0].trigger("click");
        await selects(wrapper)[2].setValue("assign_category");
        await selects(wrapper)[3].setValue("7");
        await applyButton(wrapper).trigger("click");
        await flushPromises();

        expect(bulkBody()).toEqual({
            action: "assign_category",
            auction_ids: [4],
            category_id: 7,
        });
        expect(wrapper.text()).toContain("1 auction updated.");
    });

    it("confirms before ending auctions and marks them ended", async () => {
        const wrapper = mountAuctions([auction({ id: 1, title: "A" })]);
        await flushPromises();

        await rows(wrapper)[0].trigger("click");
        await selects(wrapper)[2].setValue("end");
        await applyButton(wrapper).trigger("click");

        expect(wrapper.text()).toContain("End 1 active auction? This cannot be undone.");
        expect(state.apiMock.mock.calls.some((c) => c[0] === "/admin/auctions/bulk")).toBe(false);

        await wrapper
            .findAll(".fixed button")
            .find((b) => b.text() === "End")!
            .trigger("click");
        await flushPromises();

        expect(bulkBody()).toEqual({ action: "end", auction_ids: [1] });
        expect(rows(wrapper)[0].text()).toContain("Ended");
    });

    it("refuses to cancel when none of the selected auctions are active", async () => {
        const wrapper = mountAuctions([auction({ id: 1, is_active: false, status: "ended" })]);
        await flushPromises();

        await rows(wrapper)[0].trigger("click");
        await selects(wrapper)[2].setValue("cancel");
        await applyButton(wrapper).trigger("click");

        expect(wrapper.text()).toContain("None of the selected auctions are active.");
        expect(wrapper.find(".fixed").exists()).toBe(false);
    });

    it("shows the API error message when the bulk update fails", async () => {
        const wrapper = mountAuctions([auction({ id: 1 })], {
            bulk: () => Promise.reject(new ApiError(422, { message: "Round is closed." })),
        });
        await flushPromises();

        await rows(wrapper)[0].trigger("click");
        await applyButton(wrapper).trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain("Round is closed.");
        expect(wrapper.text()).toContain("1 selected");
    });

    it("keeps Apply disabled until something is selected", async () => {
        const wrapper = mountAuctions([auction({ id: 1 })]);
        await flushPromises();

        expect(applyButton(wrapper).attributes("disabled")).toBeDefined();
        await rows(wrapper)[0].trigger("click");
        expect(applyButton(wrapper).attributes("disabled")).toBeUndefined();
    });
});
