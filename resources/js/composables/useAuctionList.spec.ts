import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { defineComponent, h, reactive, ref, type Ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import type { Auction, HeartbeatData } from "../types";

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

import { useAuctionList } from "./useAuctionList";

type List = ReturnType<typeof useAuctionList>;

function auction(overrides: Partial<Auction> = {}): Auction {
    return {
        id: 1,
        title: "Laptop",
        starting_price: "10.00",
        quantity: 1,
        ends_at: "2026-01-01T10:00:00Z",
        status: "active",
        is_active: true,
        images: [],
        ...overrides,
    };
}

function mountList(responses: Record<string, unknown> = {}) {
    const all: Record<string, unknown> = {
        "/rounds/current": { active: null, ended: [] },
        "/categories": { categories: [] },
        "/announcement": { announcement: null },
        "/auctions": { auctions: [] },
        ...responses,
    };
    state.apiMock.mockImplementation(async (url: string, init?: RequestInit) => {
        const key = init?.method ? `${init.method} ${url}` : url;
        if (key in all) {
            const value = all[key];
            if (value instanceof Error) throw value;
            return value;
        }
        if (url.startsWith("/auctions?round_id=")) return all["/auctions"];
        throw new Error(`Unexpected ${key}`);
    });

    const heartbeatData: Ref<HeartbeatData | null> = ref(null);
    const now = ref(new Date("2026-01-01T00:00:00Z"));
    let result!: List;
    const Harness = defineComponent({
        setup() {
            result = useAuctionList();
            return () => h("div");
        },
    });
    mount(Harness, {
        global: {
            provide: {
                heartbeatData,
                currencySymbol: ref("$"),
                user: ref(null),
                now,
                currentRound: ref({ active: null, ended: [] }),
            },
        },
    });
    return {
        heartbeatData,
        now,
        get result() {
            return result;
        },
    };
}

enableAutoUnmount(afterEach);

describe("useAuctionList", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.replace.mockReset();
        state.route = reactive({ path: "/", query: {} });
    });

    it("loads the active round, categories, announcement and auctions on mount", async () => {
        const { result } = mountList({
            "/rounds/current": {
                active: { id: 5, name: "Active", status: "active" },
                ended: [{ id: 4, name: "Old", status: "ended" }],
            },
            "/announcement": { announcement: { id: 1, message: "Hi", is_active: true } },
            "/auctions": { auctions: [auction()] },
        });
        await flushPromises();

        expect(result.allRounds.value.map((r) => r.id)).toEqual([5, 4]);
        expect(result.selectedRoundId.value).toBe(5);
        expect(result.announcement.value?.message).toBe("Hi");
        expect(state.apiMock).toHaveBeenCalledWith("/auctions?round_id=5");
        expect(state.router.replace).toHaveBeenCalledWith({ path: "/", query: { round_id: "5" } });
        expect(result.auctions.value).toHaveLength(1);
        expect(result.loading.value).toBe(false);
    });

    it("keeps working when the announcement request fails", async () => {
        const { result } = mountList({ "/announcement": new Error("boom") });
        await flushPromises();

        expect(result.announcement.value).toBeNull();
        expect(state.apiMock).toHaveBeenCalledWith("/auctions");
        expect(state.router.replace).toHaveBeenCalledWith({ path: "/", query: {} });
    });

    it("prefers round_id from the query and reloads when the round changes", async () => {
        state.route.query = { round_id: "3" };
        const { result } = mountList();
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions?round_id=3");

        result.selectedRoundId.value = null;
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/auctions");
        expect(state.router.replace).toHaveBeenLastCalledWith({ path: "/", query: {} });
    });

    it("saves, edits and removes the announcement", async () => {
        const { result } = mountList({
            "POST /announcement": { announcement: { id: 9, message: "New", is_active: true } },
            "DELETE /announcements/9": {},
        });
        await flushPromises();

        result.announcementDraft.value = "   ";
        await result.saveAnnouncement();
        expect(state.apiMock).not.toHaveBeenCalledWith("/announcement", expect.anything());

        result.editingAnnouncement.value = true;
        result.announcementDraft.value = "  New  ";
        await result.saveAnnouncement();
        expect(state.apiMock).toHaveBeenCalledWith("/announcement", {
            method: "POST",
            body: JSON.stringify({ message: "New" }),
        });
        expect(result.announcement.value?.id).toBe(9);
        expect(result.editingAnnouncement.value).toBe(false);
        expect(result.announcementDraft.value).toBe("");
        expect(result.announcementSaving.value).toBe(false);

        result.startEditAnnouncement();
        expect(result.announcementDraft.value).toBe("New");
        expect(result.editingAnnouncement.value).toBe(true);

        await result.removeAnnouncement();
        expect(state.apiMock).toHaveBeenCalledWith("/announcements/9", { method: "DELETE" });
        expect(result.announcement.value).toBeNull();
    });

    it("patches auctions in place from heartbeat updates", async () => {
        const { result, heartbeatData } = mountList({
            "/auctions": { auctions: [auction({ id: 1, current_price: "10.00" })] },
        });
        await flushPromises();
        state.apiMock.mockClear();

        heartbeatData.value = {
            auction_ids: [1],
            auction_updates: [{ id: 1, current_price: "25.00" }],
        };
        await flushPromises();

        expect(result.auctions.value[0].current_price).toBe("25.00");
        expect(state.apiMock).not.toHaveBeenCalled();
    });

    it("reloads auctions when the heartbeat reports a different set of ids", async () => {
        const { heartbeatData } = mountList({ "/auctions": { auctions: [auction({ id: 1 })] } });
        await flushPromises();
        state.apiMock.mockClear();

        heartbeatData.value = { auction_ids: [1, 2], auction_updates: [] };
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions");
    });

    it("groups auctions by category, hides ended ones and filters by location", async () => {
        const { result } = mountList({
            "/categories": {
                categories: [
                    { id: 1, name: "Laptops", slug: "laptops" },
                    { id: 2, name: "Empty", slug: "empty" },
                ],
            },
            "/auctions": {
                auctions: [
                    auction({ id: 1, category_id: 1, location: "Ghent" }),
                    auction({ id: 2, category_id: null, location: "Antwerp" }),
                    auction({ id: 3, category_id: 99, is_active: false, status: "ended" }),
                    auction({
                        id: 4,
                        category_id: 1,
                        is_active: false,
                        leftover_enabled: true,
                        leftover_quantity: 2,
                        location: "Ghent",
                    }),
                ],
            },
        });
        await flushPromises();

        expect(result.hiddenEndedCount.value).toBe(1);
        expect(result.availableLocations.value).toEqual(["Antwerp", "Ghent"]);
        expect(
            result.groupedAuctions.value.map((g) => [g.slug, g.auctions.map((a) => a.id)]),
        ).toEqual([
            ["laptops", [1, 4]],
            ["other", [2]],
        ]);

        result.showSoldOut.value = true;
        expect(result.groupedAuctions.value.at(-1)?.auctions.map((a) => a.id)).toEqual([2, 3]);

        result.selectedLocation.value = "Ghent";
        expect(result.groupedAuctions.value.map((g) => g.slug)).toEqual(["laptops"]);
    });

    it("derives price and status labels", async () => {
        const { result } = mountList();
        await flushPromises();

        const live = auction({ current_price: "12.00" });
        const leftover = auction({
            is_active: false,
            leftover_enabled: true,
            leftover_quantity: 1,
            starting_price: "20.00",
            leftover_price: "15.00",
        });
        const soldOut = auction({ is_active: false, leftover_enabled: true, leftover_quantity: 0 });
        const closedRound = auction({
            is_active: false,
            leftover_enabled: true,
            leftover_quantity: 1,
            round: { id: 1, name: "R", status: "ended" },
        });
        const endedWithBids = auction({ is_active: false, bid_count: 3 });
        const endedNoBids = auction({ is_active: false, bid_count: 0 });

        expect(result.priceLabel(live)).toBe("Current price");
        expect(result.priceValue(live)).toBe("12.00");
        expect(result.statusText(live)).toBe("Live");

        expect(result.isLeftoverSale(leftover)).toBe(true);
        expect(result.priceLabel(leftover)).toBe("Buy now");
        expect(result.priceValue(leftover)).toBe("15.00");
        expect(result.statusText(leftover)).toBe("Leftover sale");
        expect(result.leftoverDiscountText(leftover)).toBe("25% off");
        expect(result.leftoverDiscountText(live)).toBeNull();

        expect(result.statusText(soldOut)).toBe("Sold out");
        expect(result.roundClosed(closedRound)).toBe(true);
        expect(result.isLeftoverSale(closedRound)).toBe(false);
        expect(result.statusText(closedRound)).toBe("Ended");

        expect(result.priceLabel(endedWithBids)).toBe("Final price");
        expect(result.priceLabel(endedNoBids)).toBe("Starting price");
        expect(result.watchingText(3)).toBe("3 currently watching");
    });

    it("formats the time left relative to the injected clock", async () => {
        const { result } = mountList();
        await flushPromises();

        expect(result.timeLeft("2025-12-31T00:00:00Z")).toBe("Ended");
        expect(result.timeLeft("2026-01-01T02:30:00Z")).toBe("2h 30m left");
        expect(result.timeLeft("2026-01-03T05:00:00Z")).toBe("2d 5h left");
    });
});
