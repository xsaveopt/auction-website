import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import { alice, bob, labelTargets, makeAuction, seller } from "../../testing";
import type { Auction, LeftoverPriceOffer, User } from "../../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../../lib/api")>()),
    api: apiMock,
}));

import AuctionLeftoverSale from "./AuctionLeftoverSale.vue";
import AuctionAdminOfferForm from "./AuctionAdminOfferForm.vue";
import AuctionAdminOffers from "./AuctionAdminOffers.vue";
import AuctionAdminPurchaseForm from "./AuctionAdminPurchaseForm.vue";
import AuctionLeftoverBuy from "./AuctionLeftoverBuy.vue";
import AuctionPriceOffer from "./AuctionPriceOffer.vue";

const admin: User = { id: 2, username: "admin", is_admin: true };

function auction(overrides: Partial<Auction> = {}): Auction {
    return makeAuction({
        is_active: false,
        status: "ended",
        quantity: 5,
        items_allocated: 2,
        leftover_enabled: true,
        leftover_quantity: 3,
        starting_price: "10.00",
        leftover_price: "8.00",
        ...overrides,
    });
}

const acceptedOffer: LeftoverPriceOffer = {
    id: 4,
    auction_id: 7,
    user_id: 10,
    quantity: 2,
    offered_price_per_item: "6.00",
    status: "accepted",
    user: alice,
};

function mountSale(initial: Auction, user: User | null = alice) {
    return mount(AuctionLeftoverSale, {
        props: { auction: initial, users: [alice, bob] },
        global: {
            provide: { user: ref(user), currencySymbol: ref("€"), notify: vi.fn() },
            stubs: { "router-link": { props: ["to"], template: "<a :href='to'><slot /></a>" } },
        },
    });
}

enableAutoUnmount(afterEach);

describe("AuctionLeftoverSale", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("summarises the leftover price and stock", () => {
        const wrapper = mountSale(auction());

        const text = wrapper.text();
        expect(text).toContain("Leftover sale");
        expect(text).toContain("3 items left");
        expect(text).toContain("€8.00 per item");
        expect(text).toContain("20% off the original €10.00 price.");
        expect(text).toContain("3 items available");
        expect(text).toContain("0 items sold after the auction");
    });

    it("offers buy now and a lower offer to buyers with unique label ids", () => {
        const wrapper = mountSale(auction());

        expect(wrapper.findComponent(AuctionLeftoverBuy).exists()).toBe(true);
        expect(wrapper.findComponent(AuctionPriceOffer).exists()).toBe(true);
        expect(wrapper.findComponent(AuctionAdminOfferForm).exists()).toBe(false);
        expect(labelTargets(wrapper)).toHaveLength(1);
    });

    it("confirms a previous purchase and switches to buy more", () => {
        const wrapper = mountSale(
            auction({
                leftover_purchases: [{ id: 1, quantity: 2, price_per_item: "8.00", user: alice }],
            }),
        );

        const text = wrapper.text();
        expect(text).toContain("Purchase confirmed");
        expect(text).toContain("You purchased 2 items at €8.00 each.");
        expect(text).toContain("Total: €16.00");
        expect(wrapper.findComponent(AuctionLeftoverBuy).props("hasPurchase")).toBe(true);
        expect(text).toContain("Purchases");
        expect(text).toContain("(you)");
    });

    it("forwards purchases made through buy now", async () => {
        const wrapper = mountSale(auction());
        const updated = auction({ leftover_quantity: 2 });
        apiMock.mockResolvedValueOnce({ auction: updated });

        await wrapper.findComponent(AuctionLeftoverBuy).get("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.emitted("update")).toEqual([[updated]]);
    });

    it("shows receipts to buyers once the stock sold out", () => {
        const wrapper = mountSale(
            auction({
                leftover_quantity: 0,
                leftover_purchases: [{ id: 1, quantity: 1, price_per_item: "8.00", user: alice }],
                leftover_price_offers: [acceptedOffer],
            }),
        );

        const text = wrapper.text();
        expect(text).toContain("No leftovers left");
        expect(text).toContain("Purchase confirmed");
        expect(text).toContain("Offer accepted");
        expect(text).toContain("2 items at €6.00 each.");
        expect(text).toContain("Total: €12.00");
        expect(wrapper.findComponent(AuctionLeftoverBuy).exists()).toBe(false);
    });

    it("tells the seller they cannot buy and guests to log in", () => {
        expect(mountSale(auction(), seller).text()).not.toContain("cannot purchase leftovers");

        const own = mountSale(auction({ seller: bob }), bob);
        expect(own.text()).toContain("You cannot purchase leftovers from your own auction.");

        const guest = mountSale(auction(), null);
        expect(guest.get("a[href='/login']").text()).toBe("Log in");
    });

    it("gives admins the offer, purchase and review tools", async () => {
        const pending: LeftoverPriceOffer = { ...acceptedOffer, id: 5, status: "pending" };
        const wrapper = mountSale(
            auction({ leftover_price_offers: [acceptedOffer, pending] }),
            admin,
        );

        expect(wrapper.findComponent(AuctionLeftoverBuy).exists()).toBe(false);
        expect(wrapper.findComponent(AuctionAdminOfferForm).exists()).toBe(true);
        expect(wrapper.findComponent(AuctionAdminPurchaseForm).exists()).toBe(true);
        expect(wrapper.findComponent(AuctionAdminOffers).props("pendingCount")).toBe(1);
        expect(wrapper.text()).toContain("Price offers (1 pending)");

        await wrapper.findComponent(AuctionAdminOfferForm).get("button").trigger("click");
        await wrapper.findComponent(AuctionAdminPurchaseForm).get("button").trigger("click");
        expect(wrapper.emitted("loadUsers")).toHaveLength(2);
        expect(labelTargets(wrapper)).toHaveLength(5);

        const accept = wrapper.findAll("button").find((b) => b.text() === "Accept");
        await accept?.trigger("click");
        expect(wrapper.emitted("confirm")?.[0][0]).toMatchObject({
            message: "Accept offer of €6.00 × 2 from alice?",
        });
    });
});
