import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { defineComponent, h, reactive, ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import type { Auction, CurrentRound, User } from "../types";

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

import AuctionList from "./AuctionList.vue";

const RouterLinkStub = defineComponent({
    props: { to: { type: String, required: true } },
    setup(props, { slots }) {
        return () => h("a", { href: props.to }, slots.default?.());
    },
});

const admin: User = { id: 1, username: "admin", is_admin: true };
const bidder: User = { id: 2, username: "bob" };

function auction(overrides: Partial<Auction> = {}): Auction {
    return {
        id: 1,
        title: "Laptop",
        description: "Fast machine",
        starting_price: "10.00",
        current_price: "12",
        quantity: 1,
        bid_count: 1,
        ends_at: "2026-01-01T02:30:00Z",
        status: "active",
        is_active: true,
        images: [],
        ...overrides,
    };
}

function mountList(
    responses: Record<string, unknown> = {},
    options: { user?: User | null; currentRound?: CurrentRound } = {},
) {
    const all: Record<string, unknown> = {
        "/rounds/current": { active: null, ended: [] },
        "/categories": { categories: [] },
        "/announcement": { announcement: null },
        "/auctions": { auctions: [] },
        ...responses,
    };
    state.apiMock.mockImplementation(async (url: string, init?: RequestInit) => {
        const key = init?.method ? `${init.method} ${url}` : url;
        if (!(key in all)) throw new Error(`Unexpected ${key}`);
        return all[key];
    });

    return mount(AuctionList, {
        global: {
            provide: {
                heartbeatData: ref(null),
                currencySymbol: ref("$"),
                user: ref(options.user === undefined ? bidder : options.user),
                now: ref(new Date("2026-01-01T00:00:00Z")),
                currentRound: ref(options.currentRound ?? { active: null, ended: [] }),
            },
            stubs: { "router-link": RouterLinkStub },
        },
    });
}

enableAutoUnmount(afterEach);

describe("AuctionList", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.replace.mockReset();
        state.route = reactive({ path: "/", query: {} });
    });

    it("renders auction cards grouped under their category headings", async () => {
        const wrapper = mountList({
            "/categories": { categories: [{ id: 3, name: "Laptops", slug: "laptops" }] },
            "/auctions": {
                auctions: [
                    auction({
                        id: 1,
                        category_id: 3,
                        quantity: 3,
                        bid_count: 2,
                        watcher_count: 4,
                        images: [{ id: 1, path: "a.jpg", url: "/storage/a.jpg" }],
                    }),
                    auction({ id: 2, title: "Chair", category_id: null, bid_count: 1 }),
                ],
            },
        });
        expect(wrapper.text()).toContain("Loading...");
        await flushPromises();

        expect(wrapper.findAll("h2").map((h2) => h2.text())).toEqual(["Laptops", "Other"]);

        const laptop = wrapper.get("a[href='/auctions/1']");
        expect(laptop.find("img").attributes("src")).toBe("/storage/a.jpg");
        expect(laptop.text()).toContain("Live");
        expect(laptop.text()).toContain("Fast machine");
        expect(laptop.text()).toContain("4 currently watching");
        expect(laptop.text()).toContain("Current price");
        expect(laptop.text()).toContain("$12.00");
        expect(laptop.text()).toContain("2h 30m left");
        expect(laptop.text()).toMatch(/3 items ·\s+2\s+bids/);

        expect(wrapper.get("a[href='/auctions/2']").text()).toMatch(/1\s+bid$/);
    });

    it("advertises leftover sales with their discount", async () => {
        const wrapper = mountList({
            "/auctions": {
                auctions: [
                    auction({
                        is_active: false,
                        status: "ended",
                        starting_price: "20",
                        leftover_enabled: true,
                        leftover_quantity: 2,
                        leftover_price: "15",
                    }),
                ],
            },
        });
        await flushPromises();

        const card = wrapper.get("a[href='/auctions/1']").text();
        expect(card).toContain("Leftover sale");
        expect(card).toContain("Buy now");
        expect(card).toContain("$15.00");
        expect(card).toContain("2 items left");
        expect(card).toContain("25% off");
        expect(card).toContain("Buy now available");
    });

    it("hides ended auctions behind a toggle", async () => {
        const wrapper = mountList({
            "/auctions": {
                auctions: [auction({ id: 1 }), auction({ id: 2, is_active: false })],
            },
        });
        await flushPromises();

        expect(wrapper.find("a[href='/auctions/2']").exists()).toBe(false);

        const toggle = wrapper.get("button");
        expect(toggle.text()).toBe("Show 1 ended");
        await toggle.trigger("click");

        expect(wrapper.get("a[href='/auctions/2']").text()).toContain("Ended");
        expect(wrapper.get("button").text()).toBe("Hide ended");
    });

    it("filters the cards by pickup location", async () => {
        const wrapper = mountList({
            "/auctions": {
                auctions: [
                    auction({ id: 1, location: "Ghent" }),
                    auction({ id: 2, location: "Antwerp" }),
                ],
            },
        });
        await flushPromises();

        const select = wrapper.get("select");
        expect(select.findAll("option").map((o) => o.text())).toEqual([
            "All locations",
            "Antwerp",
            "Ghent",
        ]);

        await select.setValue("Ghent");

        expect(wrapper.find("a[href='/auctions/1']").exists()).toBe(true);
        expect(wrapper.find("a[href='/auctions/2']").exists()).toBe(false);
    });

    it("announces an ended round and switches rounds from the selector", async () => {
        const rounds = {
            active: null,
            ended: [{ id: 4, name: "Winter", status: "ended" }],
        };
        const wrapper = mountList(
            { "/rounds/current": rounds, "/auctions": { auctions: [] } },
            { currentRound: rounds },
        );
        await flushPromises();

        expect(wrapper.text()).toContain('The "Winter" auction round has ended.');
        expect(wrapper.text()).toContain("No auctions yet.");

        state.apiMock.mockResolvedValueOnce({ auctions: [] });
        await wrapper.get("select").setValue("4");
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/auctions?round_id=4");
        expect(wrapper.text()).toContain("No auctions yet for this round.");
    });

    it("shows the announcement without admin controls to regular users", async () => {
        const wrapper = mountList({
            "/announcement": {
                announcement: { id: 1, message: "Pickup on Friday", author: "admin" },
            },
        });
        await flushPromises();

        expect(wrapper.text()).toContain("Pickup on Friday");
        expect(wrapper.text()).toContain("Posted by admin");
        expect(wrapper.find("button[title='Edit']").exists()).toBe(false);
        expect(wrapper.find("button[title='Remove']").exists()).toBe(false);
        expect(wrapper.text()).not.toContain("Add announcement");
    });

    it("lets admins publish, edit and remove the announcement", async () => {
        const wrapper = mountList(
            {
                "POST /announcement": {
                    announcement: { id: 9, message: "Office closed", author: "admin" },
                },
                "DELETE /announcements/9": {},
            },
            { user: admin },
        );
        await flushPromises();

        await wrapper.get("button").trigger("click");
        const publish = wrapper.findAll("button").find((b) => b.text() === "Publish")!;
        expect(publish.attributes("disabled")).toBeDefined();

        await wrapper.get("textarea").setValue("Office closed");
        expect(wrapper.text()).toContain("13/1000");
        await publish.trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/announcement", {
            method: "POST",
            body: JSON.stringify({ message: "Office closed" }),
        });
        expect(wrapper.find("textarea").exists()).toBe(false);
        expect(wrapper.text()).toContain("Office closed");

        await wrapper.get("button[title='Edit']").trigger("click");
        expect((wrapper.get("textarea").element as HTMLTextAreaElement).value).toBe(
            "Office closed",
        );
        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Cancel")!
            .trigger("click");

        await wrapper.get("button[title='Remove']").trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/announcements/9", { method: "DELETE" });
        expect(wrapper.text()).not.toContain("Office closed");
        expect(wrapper.text()).toContain("Add announcement");
    });
});
