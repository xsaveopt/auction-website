import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import type { User } from "../types";

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

import CreateAuction from "./CreateAuction.vue";
import { ApiError } from "../api";

const admin: User = { id: 1, username: "admin", is_admin: true };

function mountCreate(user: User | null = admin) {
    return mount(CreateAuction, {
        global: {
            provide: {
                user: ref(user),
                currencySymbol: ref("€"),
                now: ref(new Date(2026, 0, 1, 9, 5)),
            },
        },
    });
}

function selectFiles(input: HTMLInputElement, files: File[]) {
    Object.defineProperty(input, "files", { value: files, configurable: true });
    input.dispatchEvent(new Event("change"));
}

enableAutoUnmount(afterEach);

describe("CreateAuction", () => {
    const originalCreate = URL.createObjectURL;
    const originalRevoke = URL.revokeObjectURL;

    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
        let counter = 0;
        URL.createObjectURL = vi.fn(() => `blob:preview-${++counter}`);
        URL.revokeObjectURL = vi.fn();
    });

    afterEach(() => {
        URL.createObjectURL = originalCreate;
        URL.revokeObjectURL = originalRevoke;
    });

    it("redirects non-admins to the home page", async () => {
        state.apiMock.mockResolvedValue({ categories: [] });
        mountCreate({ id: 2, username: "bob" });
        await flushPromises();

        expect(state.router.push).toHaveBeenCalledWith("/");
    });

    it("prefills the end date a week ahead and labels the price with the currency", async () => {
        state.apiMock.mockResolvedValue({ categories: [] });
        const wrapper = mountCreate();
        await flushPromises();

        expect(state.router.push).not.toHaveBeenCalled();
        expect(
            (wrapper.find("input[type='datetime-local']").element as HTMLInputElement).value,
        ).toBe("2026-01-08T09:05");
        expect(wrapper.text()).toContain("Starting Price (€)");
        expect(wrapper.find("select").exists()).toBe(false);
    });

    it("creates the auction with normalised fields and opens it", async () => {
        state.apiMock.mockResolvedValueOnce({
            categories: [{ id: 3, name: "Laptops", slug: "laptops" }],
        });
        const wrapper = mountCreate();
        await flushPromises();

        state.apiMock.mockResolvedValueOnce({ auction: { id: 42 } });
        await wrapper.find("input[type='text']").setValue("ThinkPad");
        await wrapper.find("textarea").setValue("Lightly used");
        await wrapper.find("select").setValue("3");
        const numbers = wrapper.findAll("input[type='number']");
        await numbers[0].setValue("12.5");
        await numbers[1].setValue("4");
        await numbers[2].setValue("2");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/auctions", {
            method: "POST",
            body: JSON.stringify({
                title: "ThinkPad",
                description: "Lightly used",
                starting_price: 12.5,
                quantity: 4,
                max_per_bidder: 2,
                category_id: 3,
                location: null,
                ends_at: "2026-01-08T09:05",
            }),
        });
        expect(state.router.push).toHaveBeenCalledWith("/auctions/42");
    });

    it("uploads the selected images after creating the auction", async () => {
        state.apiMock.mockResolvedValueOnce({ categories: [] });
        const wrapper = mountCreate();
        await flushPromises();

        const first = new File(["a"], "a.png", { type: "image/png" });
        const second = new File(["b"], "b.png", { type: "image/png" });
        selectFiles(wrapper.find("input[type='file']").element as HTMLInputElement, [
            first,
            second,
        ]);
        await flushPromises();

        expect(wrapper.findAll("img").map((img) => img.attributes("src"))).toEqual([
            "blob:preview-1",
            "blob:preview-2",
        ]);

        await wrapper.find("button[type='button']").trigger("click");
        expect(URL.revokeObjectURL).toHaveBeenCalledWith("blob:preview-1");
        expect(wrapper.findAll("img")).toHaveLength(1);

        state.apiMock.mockResolvedValueOnce({ auction: { id: 9 } }).mockResolvedValueOnce({});
        await wrapper.find("input[type='text']").setValue("Monitor");
        await wrapper.find("textarea").setValue("27 inch");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        const [url, init] = state.apiMock.mock.calls.at(-1) as [string, RequestInit];
        expect(url).toBe("/auctions/9/images");
        expect(init.method).toBe("POST");
        expect((init.body as FormData).getAll("images[]")).toEqual([second]);
        expect(state.router.push).toHaveBeenCalledWith("/auctions/9");
    });

    it("shows field validation errors from the API", async () => {
        state.apiMock.mockResolvedValueOnce({ categories: [] });
        const wrapper = mountCreate();
        await flushPromises();

        state.apiMock.mockRejectedValueOnce(
            new ApiError(422, {
                errors: {
                    title: ["The title field is required."],
                    ends_at: ["The ends at must be a date after now."],
                },
            }),
        );
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("The title field is required.");
        expect(wrapper.text()).toContain("The ends at must be a date after now.");
        expect(state.router.push).not.toHaveBeenCalled();
        expect(wrapper.find("button[type='submit']").text()).toBe("Create Auction");
    });

    it("falls back to a general error when the request fails unexpectedly", async () => {
        state.apiMock.mockResolvedValueOnce({ categories: [] });
        const wrapper = mountCreate();
        await flushPromises();

        state.apiMock.mockRejectedValueOnce(new Error("network"));
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("Failed to create auction.");
    });
});
