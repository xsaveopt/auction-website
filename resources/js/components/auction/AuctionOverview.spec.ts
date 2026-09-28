import { describe, it, expect, afterEach } from "vitest";
import { ref } from "vue";
import { mount, enableAutoUnmount } from "@vue/test-utils";
import { alice, makeAuction, seller } from "../../testing";
import type { Auction, User } from "../../lib/types";
import AuctionOverview from "./AuctionOverview.vue";
import AuctionImageGallery from "./AuctionImageGallery.vue";

function mountOverview(auction: Auction, user: User | null = alice) {
    return mount(AuctionOverview, {
        props: {
            auction,
            status: { label: "Live auction", tone: "bg-blue-50", summary: "Bidding closes soon." },
            priceLabel: "Current clearing price",
            priceValue: "14.00",
            leftoverSold: 0,
            activeImage: 0,
        },
        global: {
            provide: { user: ref(user), currencySymbol: ref("€") },
            stubs: { "router-link": { props: ["to"], template: "<a :href='to'><slot /></a>" } },
        },
    });
}

enableAutoUnmount(afterEach);

describe("AuctionOverview", () => {
    it("renders the status, prices and details", () => {
        const wrapper = mountOverview(
            makeAuction({
                watcher_count: 3,
                bid_count: 1,
                location: "Ghent",
                description: "Fast machine",
                items_allocated: 1,
                category: { id: 3, name: "Laptops", slug: "laptops" },
            }),
        );

        const text = wrapper.text();
        expect(wrapper.get("h1").text()).toBe("Laptop");
        expect(text).toContain("Live auction");
        expect(text).toContain("Bidding closes soon.");
        expect(text).toContain("3 currently watching");
        expect(text).toContain("Laptops");
        expect(text).toContain("Current clearing price");
        expect(text).toContain("€14.00");
        expect(text).toContain("Starting price");
        expect(text).toContain("€10.00");
        expect(text).toContain("Max 2 items per bidder");
        expect(text).toContain("1 allocated");
        expect(text).toContain("Ends 2026-01-01 12:00");
        expect(text).toContain("Pickup: Ghent");
        expect(text).toContain("1 bid");
        expect(text).not.toContain("1 bids");
        expect(wrapper.find("a[href='/auctions/7/edit']").exists()).toBe(false);
    });

    it("gives admins edit and delete actions", async () => {
        const wrapper = mountOverview(makeAuction({ is_active: false }), seller);

        expect(wrapper.text()).toContain("Ended 2026-01-01 12:00");
        expect(wrapper.get("a[href='/auctions/7/edit']").text()).toBe("Edit");
        await wrapper.get("button").trigger("click");
        expect(wrapper.emitted("delete")).toHaveLength(1);
    });

    it("binds the active image to the gallery", async () => {
        const wrapper = mountOverview(
            makeAuction({
                images: [
                    { id: 1, path: "a.jpg", url: "/a.jpg" },
                    { id: 2, path: "b.jpg", url: "/b.jpg" },
                ],
            }),
        );

        wrapper.findComponent(AuctionImageGallery).vm.$emit("update:activeImage", 1);
        expect(wrapper.emitted("update:activeImage")).toEqual([[1]]);
    });
});
