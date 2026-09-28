import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import { alice, bob, labelTargets, makeAuction, seller } from "../../testing";
import type { LeftoverPriceOffer, LeftoverPurchase, User } from "../../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../../lib/api")>()),
    api: apiMock,
}));

import AuctionAdminOfferForm from "./AuctionAdminOfferForm.vue";
import AuctionAdminOffers from "./AuctionAdminOffers.vue";
import AuctionAdminPurchaseForm from "./AuctionAdminPurchaseForm.vue";
import AuctionLeftoverPurchases from "./AuctionLeftoverPurchases.vue";

const auction = makeAuction({
    is_active: false,
    leftover_enabled: true,
    leftover_quantity: 3,
    leftover_price: "8.00",
});

function provide(user: User | null = seller) {
    return { user: ref(user), currencySymbol: ref("€"), notify: vi.fn() };
}

enableAutoUnmount(afterEach);

describe("AuctionAdminOfferForm", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("adds an offer for the selected user", async () => {
        const wrapper = mount(AuctionAdminOfferForm, {
            props: { auction, users: [alice, bob] },
            global: { provide: provide() },
        });

        await wrapper.get("button").trigger("click");
        expect(wrapper.emitted("loadUsers")).toHaveLength(1);
        expect(wrapper.text()).toContain("Qty (max 3)");
        expect(wrapper.text()).toContain("Price/item (max €7.99)");
        expect(labelTargets(wrapper)).toHaveLength(3);

        await wrapper.get("select").setValue("bob");
        const inputs = wrapper.findAll("input");
        await inputs[0].setValue("2");
        await inputs[1].setValue("6.5");
        apiMock.mockResolvedValueOnce({ auction });
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/leftover-price-offers", {
            method: "POST",
            body: JSON.stringify({ username: "bob", quantity: 2, offered_price_per_item: 6.5 }),
        });
        expect(wrapper.emitted("update")).toEqual([[auction]]);
        expect(wrapper.find("form").exists()).toBe(false);

        await wrapper.get("button").trigger("click");
        await wrapper.get("button").trigger("click");
        expect(wrapper.emitted("loadUsers")).toHaveLength(2);
    });
});

describe("AuctionAdminPurchaseForm", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("adds a purchase for the selected user", async () => {
        const wrapper = mount(AuctionAdminPurchaseForm, {
            props: { auction, users: [alice, bob] },
            global: { provide: provide() },
        });

        await wrapper.get("button").trigger("click");
        expect(wrapper.emitted("loadUsers")).toHaveLength(1);
        expect(labelTargets(wrapper).map((el) => el.tagName)).toEqual(["SELECT", "INPUT"]);

        await wrapper.get("select").setValue("alice");
        await wrapper.get("input").setValue("2");
        apiMock.mockResolvedValueOnce({ auction });
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/leftover-purchases", {
            method: "POST",
            body: JSON.stringify({ username: "alice", quantity: 2 }),
        });
        expect(wrapper.emitted("update")).toEqual([[auction]]);
    });
});

describe("AuctionAdminOffers", () => {
    it("offers accept and reject only for pending offers", async () => {
        const offers: LeftoverPriceOffer[] = [
            {
                id: 3,
                auction_id: 7,
                user_id: 10,
                quantity: 1,
                offered_price_per_item: "6",
                status: "pending",
                user: alice,
                created_at: "2026-01-02T08:30:00Z",
            },
            {
                id: 4,
                auction_id: 7,
                user_id: 11,
                quantity: 2,
                offered_price_per_item: "5",
                status: "rejected",
                user: bob,
            },
        ];
        const wrapper = mount(AuctionAdminOffers, {
            props: { offers, pendingCount: 1 },
            global: { provide: provide() },
        });

        expect(wrapper.text()).toContain("Price offers (1 pending)");
        expect(wrapper.text()).toContain("2026-01-02 08:30");
        expect(wrapper.text()).toContain("2 × €5.00");
        expect(wrapper.findAll("button").filter((b) => b.text() === "Accept")).toHaveLength(1);

        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Reject")
            ?.trigger("click");
        await wrapper.findAll("button[title='Delete offer']")[1].trigger("click");

        expect(wrapper.emitted("confirm")).toMatchObject([
            [{ message: "Reject offer from alice?" }],
            [{ message: "Delete offer from bob?" }],
        ]);
    });
});

describe("AuctionLeftoverPurchases", () => {
    const purchases: LeftoverPurchase[] = [
        {
            id: 8,
            quantity: 2,
            price_per_item: "5",
            from_price_offer: true,
            user: alice,
            created_at: "2026-01-03T10:00:00Z",
        },
    ];

    it("lists purchases without delete actions for regular users", () => {
        const wrapper = mount(AuctionLeftoverPurchases, {
            props: { purchases },
            global: { provide: provide(alice) },
        });

        const text = wrapper.text();
        expect(text).toContain("1 total");
        expect(text).toContain("(you)");
        expect(text).toContain("Accepted offer");
        expect(text).toContain("2 × €5.00");
        expect(text).toContain("2026-01-03 10:00");
        expect(wrapper.find("button").exists()).toBe(false);
    });

    it("lets admins delete a purchase after confirming", async () => {
        const wrapper = mount(AuctionLeftoverPurchases, {
            props: { purchases },
            global: { provide: provide(seller) },
        });

        await wrapper.get("button[title='Delete purchase']").trigger("click");

        expect(wrapper.emitted("confirm")?.[0][0]).toMatchObject({
            message: "Delete purchase by alice?",
        });
    });
});
