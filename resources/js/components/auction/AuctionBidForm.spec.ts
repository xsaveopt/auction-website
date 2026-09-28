import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import { ApiError } from "../../lib/api";
import { alice, labelTargets, makeAuction as auction } from "../../testing";
import type { Auction, Schedule } from "../../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../../lib/api")>()),
    api: apiMock,
}));

import AuctionBidForm from "./AuctionBidForm.vue";

function mountForm(initial: Auction, options: { schedule?: Schedule | null } = {}) {
    const notify = vi.fn();
    const reload = vi.fn<() => Promise<Auction | null>>().mockResolvedValue(null);
    const wrapper = mount(AuctionBidForm, {
        props: {
            auction: initial,
            reload,
            error: "",
            "onUpdate:error": (value: string) => wrapper.setProps({ error: value }),
        },
        global: {
            provide: {
                user: ref(alice),
                schedule: ref(options.schedule ?? null),
                currencySymbol: ref("€"),
                notify,
            },
        },
    });
    return { wrapper, notify, reload };
}

enableAutoUnmount(afterEach);

describe("AuctionBidForm", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("pairs every label with its own input", () => {
        const { wrapper } = mountForm(auction());

        const targets = labelTargets(wrapper);
        expect(targets.map((el) => el.tagName)).toEqual(["INPUT", "INPUT"]);
    });

    it("shows the per-item total and places the bid", async () => {
        const { wrapper, notify, reload } = mountForm(auction());
        expect(wrapper.text()).toContain("Place a Bid");

        const inputs = wrapper.findAll("input[type='number']");
        await inputs[0].setValue("15.5");
        await inputs[1].setValue("2");
        expect(wrapper.text()).toContain("maximum total of €31.00");

        apiMock.mockResolvedValueOnce({});
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/auctions/7/bids", {
            method: "POST",
            body: JSON.stringify({ amount: 15.5, quantity: 2 }),
        });
        expect(reload).toHaveBeenCalled();
        expect(notify).toHaveBeenCalledWith("Bid placed successfully!", "success");
    });

    it("hides the quantity for single-item bidding", () => {
        const { wrapper } = mountForm(auction({ max_per_bidder: 1 }));

        expect(wrapper.findAll("input[type='number']")).toHaveLength(1);
        expect(wrapper.text()).not.toContain("maximum total");
    });

    it("summarises the user's current bid", () => {
        const { wrapper } = mountForm(
            auction({
                bids: [{ id: 1, amount: "12.00", quantity: 2, won_quantity: 1, user: alice }],
            }),
        );

        const text = wrapper.text();
        expect(text).toContain("Update Your Bid");
        expect(text).toContain("€12.00");
        expect(text).toContain("for 2 items");
        expect(text).toContain("(up to €24.00 total)");
        expect(text).toContain("(winning 1)");
        expect(wrapper.get("button[type='submit']").text()).toBe("Update Bid");
    });

    it("writes API errors to the shared error model", async () => {
        const { wrapper } = mountForm(auction());

        apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { amount: ["Bid must be at least 10.00."] } }),
        );
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.emitted("update:error")?.at(-1)).toEqual(["Bid must be at least 10.00."]);
        expect(wrapper.text()).toContain("Bid must be at least 10.00.");
    });

    it("shows the office hours notice while bidding is closed", () => {
        const { wrapper } = mountForm(auction(), {
            schedule: { enabled: true, is_open: false, closed_start: "09:00", closed_end: "17:00" },
        });

        expect(wrapper.text()).toContain("Bidding is closed during office hours (09:00 – 17:00)");
        expect(wrapper.find("form").exists()).toBe(false);
    });
});
