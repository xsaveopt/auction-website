import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../../lib/api";
import { alice, labelTargets, makeAuction } from "../../testing";
import type { LeftoverPriceOffer } from "../../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../../lib/api")>()),
    api: apiMock,
}));

import AuctionPriceOffer from "./AuctionPriceOffer.vue";

const auction = makeAuction({
    is_active: false,
    leftover_enabled: true,
    leftover_quantity: 3,
    leftover_price: "8.00",
});

const offer: LeftoverPriceOffer = {
    id: 3,
    auction_id: 7,
    user_id: 10,
    quantity: 1,
    offered_price_per_item: "6.00",
    status: "pending",
    rebid_requested_at: "2026-01-01T00:00:00Z",
    user: alice,
};

function mountOffer(current: LeftoverPriceOffer | null) {
    const notify = vi.fn();
    const wrapper = mount(AuctionPriceOffer, {
        props: { auction, offer: current },
        global: { provide: { currencySymbol: ref("€"), notify } },
    });
    return { wrapper, notify };
}

enableAutoUnmount(afterEach);

describe("AuctionPriceOffer", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("opens the offer form and submits a lower offer", async () => {
        const { wrapper, notify } = mountOffer(null);
        expect(wrapper.find("form").exists()).toBe(false);

        await wrapper.get("button").trigger("click");
        expect(wrapper.get("button").text()).toBe("Cancel");
        expect(wrapper.text()).toContain("Price per item (max €7.99)");
        const [quantity, price] = labelTargets(wrapper);
        expect(quantity.getAttribute("max")).toBe("3");
        expect(price.getAttribute("max")).toBe("7.99");

        const inputs = wrapper.findAll("input");
        await inputs[0].setValue("2");
        await inputs[1].setValue("5");
        expect(wrapper.text()).toContain("€10.00");

        apiMock.mockResolvedValueOnce({ auction });
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/auctions/7/leftover-price-offers", {
            method: "POST",
            body: JSON.stringify({ quantity: 2, offered_price_per_item: 5 }),
        });
        expect(wrapper.emitted("update")).toEqual([[auction]]);
        expect(notify).toHaveBeenCalledWith("Offer submitted!", "success");
        expect(wrapper.find("form").exists()).toBe(false);
    });

    it("shows the error when the offer is rejected by the server", async () => {
        const { wrapper } = mountOffer(null);
        await wrapper.get("button").trigger("click");

        apiMock.mockRejectedValueOnce(new ApiError(422, { message: "Closed" }));
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("Closed");
    });

    it("asks for a higher offer when a rebid is requested", () => {
        const { wrapper } = mountOffer(offer);

        const text = wrapper.text();
        expect(text).toContain("Your offer");
        expect(text).toContain("1 item at €6.00 each");
        expect(text).toContain("There is a tie at your price.");
        expect(text).toContain("Price per item (€6.01 – €7.99)");
        const [, price] = labelTargets(wrapper);
        expect(price.getAttribute("min")).toBe("6.01");
        expect(wrapper.get("button[type='submit']").text()).toBe("Submit higher offer");
    });

    it("shows the offer status otherwise", () => {
        const { wrapper } = mountOffer({ ...offer, status: "rejected", rebid_requested_at: null });

        expect(wrapper.find("form").exists()).toBe(false);
        const status = wrapper.get("span.capitalize");
        expect(status.text()).toBe("rejected");
        expect(status.classes()).toContain("bg-red-50");
    });
});
