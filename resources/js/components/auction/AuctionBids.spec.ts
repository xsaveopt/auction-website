import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import { alice, bob, labelTargets, makeAuction, seller } from "../../testing";
import type { Auction, User } from "../../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../../lib/api")>()),
    api: apiMock,
}));

import AuctionBids from "./AuctionBids.vue";

const auction = makeAuction({
    bid_count: 2,
    items_allocated: 1,
    leftover_enabled: true,
    bids: [
        {
            id: 1,
            amount: "14.00",
            quantity: 2,
            won_quantity: 1,
            price: "12.00",
            user: bob,
            created_at: "2026-01-01T09:00:00Z",
        },
        { id: 2, amount: "11.00", quantity: 1, user: alice, created_at: "2026-01-01T09:30:00Z" },
    ],
});

function mountBids(
    user: User | null,
    props: Partial<{ auction: Auction; highlighted: number[] }> = {},
) {
    return mount(AuctionBids, {
        props: {
            auction: props.auction ?? auction,
            highlightedBids: new Set(props.highlighted ?? []),
            leftoverSold: 1,
            users: [alice, bob],
        },
        global: {
            provide: { user: ref(user), currencySymbol: ref("€"), notify: vi.fn() },
            stubs: { TransitionGroup: false },
        },
    });
}

enableAutoUnmount(afterEach);

describe("AuctionBids", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("lists bids with winners, highlights and allocation", () => {
        const wrapper = mountBids(alice, { highlighted: [2] });

        const text = wrapper.text();
        expect(text).toContain("Bids (2)");
        expect(text).toContain("1 / 3 allocated");
        expect(text).toContain("1 sold (buy now)");
        expect(text).toContain("wants 2");
        expect(text).toContain("2026-01-01 09:00");
        expect(text).toContain("wins 1 @ €12.00");
        expect(text).toContain("(you)");
        expect(text).not.toContain("+ Add bid");

        const rows = wrapper.findAll("li");
        expect(rows[0].classes()).toContain("bg-green-50");
        expect(rows[1].classes()).toContain("bid-flash");
        expect(rows[0].classes()).not.toContain("bid-flash");
    });

    it("says when there are no bids", () => {
        const wrapper = mountBids(null, { auction: { ...auction, bids: [] } });

        expect(wrapper.text()).toContain("No bids yet.");
    });

    it("lets admins edit a bid inline", async () => {
        const wrapper = mountBids(seller);
        apiMock.mockResolvedValueOnce({ auction });

        await wrapper.get("button[title='Edit bid']").trigger("click");
        const inputs = wrapper.findAll("li input");
        expect((inputs[0].element as HTMLInputElement).value).toBe("14.00");
        await inputs[0].setValue("15");
        const save = wrapper.findAll("button").find((b) => b.text() === "Save");
        await save?.trigger("click");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/admin/bids/1", {
            method: "PUT",
            body: JSON.stringify({ amount: 15, quantity: 2 }),
        });
        expect(wrapper.emitted("update")).toEqual([[auction]]);
        expect(wrapper.find("li input").exists()).toBe(false);
    });

    it("asks for confirmation before deleting a bid", async () => {
        const wrapper = mountBids(seller);

        await wrapper.get("button[title='Delete bid']").trigger("click");

        expect(wrapper.emitted("confirm")?.[0][0]).toMatchObject({
            message: "Delete bid by bob?",
            danger: true,
        });
    });

    it("lets admins add a bid on behalf of a user", async () => {
        const wrapper = mountBids(seller);

        const toggle = wrapper.findAll("button").find((b) => b.text() === "+ Add bid");
        await toggle?.trigger("click");
        expect(wrapper.emitted("loadUsers")).toHaveLength(1);
        expect(labelTargets(wrapper).map((el) => el.tagName)).toEqual(["SELECT", "INPUT", "INPUT"]);

        await wrapper.get("form select").setValue("alice");
        const inputs = wrapper.findAll("form input");
        await inputs[0].setValue("20");
        await inputs[1].setValue("1");
        apiMock.mockResolvedValueOnce({ auction });
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/bids", {
            method: "POST",
            body: JSON.stringify({ username: "alice", amount: 20, quantity: 1 }),
        });
        expect(wrapper.find("form").exists()).toBe(false);
    });
});
