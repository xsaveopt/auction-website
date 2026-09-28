import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../../lib/api";
import { labelTargets, makeAuction } from "../../testing";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../../lib/api")>()),
    api: apiMock,
}));

import AuctionLeftoverBuy from "./AuctionLeftoverBuy.vue";

const auction = makeAuction({
    is_active: false,
    leftover_enabled: true,
    leftover_quantity: 3,
    leftover_price: "8.00",
});

function mountBuy(hasPurchase = false) {
    const notify = vi.fn();
    const wrapper = mount(AuctionLeftoverBuy, {
        props: { auction, hasPurchase },
        global: { provide: { currencySymbol: ref("€"), notify } },
    });
    return { wrapper, notify };
}

enableAutoUnmount(afterEach);

describe("AuctionLeftoverBuy", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("buys the selected quantity and emits the updated auction", async () => {
        const { wrapper, notify } = mountBuy();
        const [quantity] = labelTargets(wrapper);
        expect(quantity.getAttribute("max")).toBe("3");
        expect(wrapper.get("h3").text()).toBe("Buy now");

        await wrapper.get("input").setValue("2");
        expect(wrapper.text()).toContain("€16.00");

        const updated = { ...auction, leftover_quantity: 1 };
        apiMock.mockResolvedValueOnce({ auction: updated });
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/auctions/7/leftover-purchases", {
            method: "POST",
            body: JSON.stringify({ quantity: 2 }),
        });
        expect(wrapper.emitted("update")).toEqual([[updated]]);
        expect(notify).toHaveBeenCalledWith("Purchase successful!", "success");
    });

    it("labels repeat purchases and shows errors", async () => {
        const { wrapper } = mountBuy(true);
        expect(wrapper.get("h3").text()).toBe("Buy more");
        expect(wrapper.get("button").text()).toBe("Buy more");

        apiMock.mockRejectedValueOnce(new ApiError(422, { errors: { quantity: ["Too many."] } }));
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("Too many.");
        expect(wrapper.emitted("update")).toBeUndefined();
    });
});
