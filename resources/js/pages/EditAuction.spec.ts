import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import type { Auction, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    router: { push: vi.fn() },
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

import EditAuction from "./EditAuction.vue";
import { ApiError } from "../api";

const admin: User = { id: 1, username: "admin", is_admin: true };

const existing: Auction = {
    id: 3,
    title: "Desk",
    description: "Standing desk",
    starting_price: "25",
    quantity: 5,
    max_per_bidder: 2,
    category_id: 4,
    location: "Ghent",
    ends_at: "2026-02-01T18:30:00.000000Z",
    status: "active",
    images: [],
};

function respond(responses: Record<string, unknown>) {
    state.apiMock.mockImplementation(async (url: string, init?: RequestInit) => {
        const key = init?.method ? `${init.method} ${url}` : url;
        if (!(key in responses)) throw new Error(`Unexpected ${key}`);
        const value = responses[key];
        if (value instanceof Error) throw value;
        return value;
    });
}

function mountEdit(user: User | null = admin) {
    return mount(EditAuction, {
        props: { id: "3" },
        global: {
            provide: { user: ref(user), currencySymbol: ref("$") },
            stubs: { "router-link": true },
        },
    });
}

enableAutoUnmount(afterEach);

describe("EditAuction", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
    });

    it("redirects non-admins without loading the auction", async () => {
        mountEdit({ id: 2, username: "bob" });
        await flushPromises();

        expect(state.router.push).toHaveBeenCalledWith("/");
        expect(state.apiMock).not.toHaveBeenCalled();
    });

    it("prefills the form from the existing auction", async () => {
        respond({
            "/auctions/3": { auction: existing },
            "/categories": { categories: [{ id: 4, name: "Furniture", slug: "furniture" }] },
        });
        const wrapper = mountEdit();
        expect(wrapper.text()).toContain("Loading...");
        await flushPromises();

        expect((wrapper.find("input[type='text']").element as HTMLInputElement).value).toBe("Desk");
        expect((wrapper.find("textarea").element as HTMLTextAreaElement).value).toBe(
            "Standing desk",
        );
        expect((wrapper.find("select").element as HTMLSelectElement).value).toBe("4");
        const numbers = wrapper.findAll("input[type='number']");
        expect((numbers[0].element as HTMLInputElement).value).toBe("25.00");
        expect((numbers[1].element as HTMLInputElement).value).toBe("5");
        expect((numbers[2].element as HTMLInputElement).value).toBe("2");
        expect(
            (wrapper.find("input[type='datetime-local']").element as HTMLInputElement).value,
        ).toBe("2026-02-01T18:30");
        expect(wrapper.text()).toContain("Starting Price ($)");
    });

    it("still renders the form when categories fail to load", async () => {
        respond({ "/auctions/3": { auction: existing }, "/categories": new Error("down") });
        const wrapper = mountEdit();
        await flushPromises();

        expect(wrapper.find("form").exists()).toBe(true);
        expect(wrapper.find("select").exists()).toBe(false);
    });

    it("shows an error when the auction cannot be loaded", async () => {
        respond({ "/auctions/3": new Error("missing"), "/categories": { categories: [] } });
        const wrapper = mountEdit();
        await flushPromises();

        expect(wrapper.text()).toContain("Failed to load auction.");
    });

    it("saves the changes with PUT and returns to the auction", async () => {
        respond({
            "/auctions/3": { auction: existing },
            "/categories": { categories: [{ id: 4, name: "Furniture", slug: "furniture" }] },
            "PUT /auctions/3": {},
        });
        const wrapper = mountEdit();
        await flushPromises();

        await wrapper.find("input[type='text']").setValue("Big desk");
        await wrapper.find("select").setValue("");
        await wrapper.findAll("input[type='text']")[1].setValue("");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/auctions/3", {
            method: "PUT",
            body: JSON.stringify({
                title: "Big desk",
                description: "Standing desk",
                starting_price: 25,
                quantity: 5,
                max_per_bidder: 2,
                category_id: null,
                location: null,
                ends_at: "2026-02-01T18:30",
            }),
        });
        expect(state.router.push).toHaveBeenCalledWith("/auctions/3");
    });

    it("shows validation and general errors when saving fails", async () => {
        respond({
            "/auctions/3": { auction: existing },
            "/categories": { categories: [] },
            "PUT /auctions/3": new ApiError(422, {
                errors: { quantity: ["Quantity cannot be lower than items already allocated."] },
            }),
        });
        const wrapper = mountEdit();
        await flushPromises();

        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("Quantity cannot be lower than items already allocated.");
        expect(state.router.push).not.toHaveBeenCalled();

        respond({
            "PUT /auctions/3": new ApiError(403, { message: "This action is unauthorized." }),
        });
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("This action is unauthorized.");
        expect(wrapper.text()).not.toContain("Quantity cannot be lower");
    });
});
