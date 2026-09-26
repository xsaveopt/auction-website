import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { defineComponent, h, ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import type { Auction, Schedule, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    router: { push: vi.fn(), replace: vi.fn() },
}));

vi.mock("../api", () => {
    class ApiError extends Error {
        status: number;
        data: { message?: string; errors?: Record<string, string[]> };
        constructor(status: number, data: { message?: string; errors?: Record<string, string[]> }) {
            super(data.message ?? "Request failed");
            this.name = "ApiError";
            this.status = status;
            this.data = data;
        }
    }
    return { api: state.apiMock, ApiError };
});
vi.mock("vue-router", () => ({ useRouter: () => state.router }));

import AuctionDetail from "./AuctionDetail.vue";
import ConfirmDialog from "../ConfirmDialog.vue";
import { ApiError } from "../api";

const RouterLinkStub = defineComponent({
    props: { to: { type: String, required: true } },
    setup(props, { slots }) {
        return () => h("a", { href: props.to }, slots.default?.());
    },
});

const seller: User = { id: 1, username: "seller", is_admin: true };
const admin: User = { id: 2, username: "admin", is_admin: true };
const alice: User = { id: 10, username: "alice" };
const bob: User = { id: 11, username: "bob" };

function auction(overrides: Partial<Auction> = {}): Auction {
    return {
        id: 7,
        title: "Laptop",
        description: "Fast machine",
        starting_price: "10.00",
        current_price: "14.00",
        quantity: 3,
        max_per_bidder: 2,
        items_allocated: 1,
        bid_count: 1,
        location: "Ghent",
        ends_at: "2026-01-01T12:00:00Z",
        status: "active",
        is_active: true,
        images: [],
        bids: [
            {
                id: 1,
                amount: "14.00",
                quantity: 1,
                won_quantity: 1,
                user: bob,
                created_at: "2026-01-01T09:00:00Z",
            },
        ],
        questions: [],
        seller,
        category: { id: 3, name: "Laptops", slug: "laptops" },
        ...overrides,
    };
}

function mountDetail(
    initial: Auction,
    options: { user?: User | null; schedule?: Schedule | null } = {},
) {
    state.apiMock.mockResolvedValue({ auction: initial });
    const notify = vi.fn();
    const wrapper = mount(AuctionDetail, {
        props: { id: "7" },
        global: {
            provide: {
                user: ref(options.user === undefined ? alice : options.user),
                schedule: ref(options.schedule ?? null),
                currencySymbol: ref("€"),
                heartbeatData: ref(null),
                now: ref(new Date("2026-01-01T10:00:00Z")),
                notify,
            },
            stubs: {
                "router-link": RouterLinkStub,
                AuctionImageGallery: true,
                AuctionQuestions: true,
            },
        },
    });
    return { wrapper, notify };
}

function button(wrapper: ReturnType<typeof mount>, label: string) {
    const match = wrapper.findAll("button").find((b) => b.text() === label);
    if (!match) throw new Error(`No button labelled ${label}`);
    return match;
}

enableAutoUnmount(afterEach);

describe("AuctionDetail", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
    });

    it("renders the auction summary for a bidder without admin controls", async () => {
        const { wrapper } = mountDetail(auction({ watcher_count: 2 }));
        expect(wrapper.text()).toContain("Loading...");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7");
        expect(wrapper.get("h1").text()).toBe("Laptop");
        const text = wrapper.text();
        expect(text).toContain("Live auction");
        expect(text).toContain("2 currently watching");
        expect(text).toContain("Laptops");
        expect(text).toContain("Current clearing price");
        expect(text).toContain("€14.00");
        expect(text).toContain("Pickup: Ghent");
        expect(text).toContain("Place a Bid");
        expect(text).toContain("bob");
        expect(text).not.toContain("Admin — Auction Controls");
        expect(wrapper.find("a[href='/auctions/7/edit']").exists()).toBe(false);
        expect(text).not.toContain("+ Add bid");
    });

    it("places a bid through the form and reloads the auction", async () => {
        const { wrapper, notify } = mountDetail(auction());
        await flushPromises();

        await wrapper.get("form input[type='number']").setValue("15.5");
        await wrapper.findAll("form input[type='number']")[1].setValue("2");
        expect(wrapper.text()).toContain("maximum total of €31.00");

        state.apiMock.mockResolvedValueOnce({}).mockResolvedValueOnce({
            auction: auction({
                bids: [
                    { id: 1, amount: "14.00", quantity: 1, won_quantity: 1, user: bob },
                    { id: 2, amount: "15.50", quantity: 2, won_quantity: 2, user: alice },
                ],
            }),
        });
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7/bids", {
            method: "POST",
            body: JSON.stringify({ amount: 15.5, quantity: 2 }),
        });
        expect(notify).toHaveBeenCalledWith("Bid placed successfully!", "success");
        expect(wrapper.text()).toContain("Update Your Bid");
        expect(wrapper.text()).toContain("(winning 2)");
        expect(wrapper.text()).toContain("(you)");
    });

    it("shows the API error when a bid is rejected", async () => {
        const { wrapper } = mountDetail(auction());
        await flushPromises();

        state.apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { amount: ["Bid must be at least 10.00."] } }),
        );
        await wrapper.get("form input[type='number']").setValue("1");
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("Bid must be at least 10.00.");
    });

    it("replaces the bid form with the office hours notice while bidding is closed", async () => {
        const { wrapper } = mountDetail(auction(), {
            schedule: { enabled: true, is_open: false, closed_start: "09:00", closed_end: "17:00" },
        });
        await flushPromises();

        expect(wrapper.text()).toContain("Bidding is closed during office hours (09:00 – 17:00)");
        expect(wrapper.find("form").exists()).toBe(false);
    });

    it("does not offer bidding to guests or the seller", async () => {
        const guest = mountDetail(auction(), { user: null });
        await flushPromises();
        expect(guest.wrapper.get("h1").text()).toBe("Laptop");
        expect(guest.wrapper.text()).not.toContain("Place a Bid");

        const own = mountDetail(auction(), { user: seller });
        await flushPromises();
        expect(own.wrapper.get("h1").text()).toBe("Laptop");
        expect(own.wrapper.text()).not.toContain("Place a Bid");
    });

    it("shows the ended notice once the auction is over", async () => {
        const { wrapper } = mountDetail(
            auction({ is_active: false, status: "ended", bid_count: 1 }),
        );
        await flushPromises();

        expect(wrapper.text()).toContain("This auction has ended.");
        expect(wrapper.text()).toContain("Final clearing price");
        expect(wrapper.find("form").exists()).toBe(false);
    });

    it("lets a bidder buy leftovers after the auction", async () => {
        const ended = auction({
            is_active: false,
            status: "ended",
            quantity: 5,
            items_allocated: 2,
            leftover_enabled: true,
            leftover_quantity: 3,
            leftover_price: "8.00",
        });
        const { wrapper, notify } = mountDetail(ended);
        await flushPromises();

        expect(wrapper.text()).toContain("Leftover sale");
        expect(wrapper.text()).toContain("€8.00 per item");

        await wrapper.get("form input[type='number']").setValue("2");
        expect(wrapper.text()).toContain("€16.00");

        state.apiMock.mockResolvedValueOnce({
            auction: {
                ...ended,
                leftover_quantity: 1,
                leftover_purchases: [{ id: 1, quantity: 2, price_per_item: "8.00", user: alice }],
            },
        });
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7/leftover-purchases", {
            method: "POST",
            body: JSON.stringify({ quantity: 2 }),
        });
        expect(notify).toHaveBeenCalledWith("Purchase successful!", "success");
        expect(wrapper.text()).toContain("Purchase confirmed");
    });

    it("deletes the auction as an admin only after confirming", async () => {
        const { wrapper } = mountDetail(auction(), { user: admin });
        await flushPromises();

        expect(wrapper.get("a[href='/auctions/7/edit']").text()).toBe("Edit");
        expect(wrapper.findComponent(ConfirmDialog).exists()).toBe(false);

        await button(wrapper, "Delete").trigger("click");
        const dialog = wrapper.findComponent(ConfirmDialog);
        expect(dialog.props("message")).toContain("delete this auction");

        dialog.vm.$emit("cancel");
        await flushPromises();
        expect(wrapper.findComponent(ConfirmDialog).exists()).toBe(false);
        expect(state.apiMock).not.toHaveBeenCalledWith("/auctions/7", { method: "DELETE" });

        await button(wrapper, "Delete").trigger("click");
        wrapper.findComponent(ConfirmDialog).vm.$emit("confirm");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7", { method: "DELETE" });
        expect(state.router.push).toHaveBeenCalledWith("/");
        expect(wrapper.findComponent(ConfirmDialog).exists()).toBe(false);
    });

    it("ends the auction from the admin controls", async () => {
        const { wrapper } = mountDetail(auction(), { user: admin });
        await flushPromises();

        expect(wrapper.text()).toContain("Admin — Auction Controls");

        state.apiMock.mockResolvedValueOnce({
            auction: auction({ is_active: false, status: "ended" }),
        });
        await button(wrapper, "End Now").trigger("click");
        wrapper.findComponent(ConfirmDialog).vm.$emit("confirm");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/admin/auctions/7/end", { method: "POST" });
        expect(wrapper.text()).toContain("Status: ended");
        expect(button(wrapper, "Reactivate").exists()).toBe(true);
    });

    it("lets an admin add a bid on behalf of a user", async () => {
        const { wrapper } = mountDetail(auction(), { user: admin });
        await flushPromises();

        state.apiMock.mockResolvedValueOnce({ users: [alice, bob] });
        await button(wrapper, "+ Add bid").trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/admin/users");
        const form = wrapper.findAll("form").at(-1)!;
        await form.get("select").setValue("alice");
        const inputs = form.findAll("input[type='number']");
        await inputs[0].setValue("20");
        await inputs[1].setValue("1");

        state.apiMock.mockResolvedValueOnce({ auction: auction() });
        await form.trigger("submit.prevent");
        await flushPromises();

        const [url, init] = state.apiMock.mock.calls.at(-1) as [string, RequestInit];
        expect(url).toBe("/admin/auctions/7/bids");
        expect(init.method).toBe("POST");
        expect(JSON.parse(init.body as string)).toMatchObject({ username: "alice" });
    });

    it("wires the questions panel and reloads when it asks for a refresh", async () => {
        const { wrapper } = mountDetail(auction({ questions: [{ id: 1, question: "Charger?" }] }));
        await flushPromises();

        const questions = wrapper.findComponent({ name: "AuctionQuestions" });
        expect(questions.props()).toMatchObject({
            auctionId: "7",
            questions: [{ id: 1, question: "Charger?" }],
            canModerate: false,
            canAsk: true,
            isSeller: false,
        });

        state.apiMock.mockClear();
        questions.vm.$emit("refresh");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/7");
    });
});
