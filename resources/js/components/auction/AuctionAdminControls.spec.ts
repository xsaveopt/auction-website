import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import { makeAuction } from "../../testing";
import type { Auction } from "../../lib/types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../../lib/api", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../../lib/api")>()),
    api: apiMock,
}));

import AuctionAdminControls from "./AuctionAdminControls.vue";

function mountControls(auction: Auction) {
    return mount(AuctionAdminControls, {
        props: { auction },
        global: { provide: { notify: vi.fn(), currencySymbol: ref("€") } },
    });
}

function button(wrapper: ReturnType<typeof mountControls>, label: string) {
    const match = wrapper.findAll("button").find((b) => b.text() === label);
    if (!match) throw new Error(`No button labelled ${label}`);
    return match;
}

enableAutoUnmount(afterEach);

describe("AuctionAdminControls", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("confirms before ending or cancelling a live auction", async () => {
        const wrapper = mountControls(makeAuction());

        await button(wrapper, "End Now").trigger("click");
        await button(wrapper, "Cancel").trigger("click");

        expect(wrapper.emitted("confirm")).toMatchObject([
            [{ confirmLabel: "End Auction" }],
            [{ confirmLabel: "Cancel Auction" }],
        ]);
        expect(wrapper.text()).not.toContain("Reactivate");
    });

    it("requires a new end time before extending", async () => {
        const wrapper = mountControls(makeAuction());

        await button(wrapper, "Extend to").trigger("click");
        expect(wrapper.text()).toContain("Set a new end time first.");

        const updated = makeAuction({ ends_at: "2026-02-01T10:00:00Z" });
        apiMock.mockResolvedValueOnce({ auction: updated });
        await wrapper.get("input[type='datetime-local']").setValue("2026-02-01T10:00");
        await button(wrapper, "Extend to").trigger("click");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/extend", {
            method: "POST",
            body: JSON.stringify({ ends_at: "2026-02-01T10:00" }),
        });
        expect(wrapper.emitted("update")).toEqual([[updated]]);
        expect(wrapper.text()).not.toContain("Set a new end time first.");
    });

    it("offers reactivation once the auction has ended", async () => {
        const wrapper = mountControls(makeAuction({ is_active: false, status: "cancelled" }));

        expect(wrapper.text()).toContain("Status: cancelled");
        expect(wrapper.text()).not.toContain("End Now");

        apiMock.mockResolvedValueOnce({ auction: makeAuction() });
        await wrapper.get("input[type='datetime-local']").setValue("2026-02-01T10:00");
        await button(wrapper, "Reactivate").trigger("click");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/admin/auctions/7/reactivate", {
            method: "POST",
            body: JSON.stringify({ ends_at: "2026-02-01T10:00" }),
        });
    });
});
