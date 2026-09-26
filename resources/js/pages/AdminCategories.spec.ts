import { describe, it, expect, vi, beforeEach } from "vitest";
import { ref } from "vue";
import { mount, flushPromises } from "@vue/test-utils";
import type { Category, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    router: { push: vi.fn(), replace: vi.fn() },
}));

vi.mock("../api", () => ({ api: state.apiMock, ApiError: class extends Error {} }));
vi.mock("vue-router", () => ({ useRouter: () => state.router }));

import AdminCategories from "./AdminCategories.vue";

const admin: User = { id: 1, username: "admin", is_admin: true };

function mountCategories(categories: Category[], user: User | null = admin) {
    let current = categories;
    state.apiMock.mockImplementation(async (url: string, options?: RequestInit) => {
        if (url === "/categories" && !options) return { categories: current };
        return {};
    });
    const wrapper = mount(AdminCategories, { global: { provide: { user: ref(user) } } });
    return {
        wrapper,
        setCategories(next: Category[]) {
            current = next;
        },
    };
}

function bodyOf(call: unknown[]) {
    return JSON.parse((call[1] as RequestInit).body as string);
}

describe("AdminCategories", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
    });

    it("redirects non-admins without loading categories", async () => {
        mountCategories([], { id: 2, username: "bob", is_admin: false });
        await flushPromises();

        expect(state.router.push).toHaveBeenCalledWith("/");
        expect(state.apiMock).not.toHaveBeenCalled();
    });

    it("lists categories with their sort order", async () => {
        const { wrapper } = mountCategories([
            { id: 1, name: "Laptops", slug: "laptops", sort_order: 0 },
            { id: 2, name: "Phones", slug: "phones", sort_order: 1 },
        ]);
        await flushPromises();

        const items = wrapper.findAll("li");
        expect(items).toHaveLength(2);
        expect(items[0].text()).toContain("Laptops");
        expect(items[1].text()).toContain("order: 1");
    });

    it("shows an empty state when there are no categories", async () => {
        const { wrapper } = mountCategories([]);
        await flushPromises();

        expect(wrapper.text()).toContain("No categories yet.");
    });

    it("adds a category at the end of the sort order and reloads", async () => {
        const { wrapper, setCategories } = mountCategories([
            { id: 1, name: "Laptops", slug: "laptops", sort_order: 0 },
        ]);
        await flushPromises();

        setCategories([
            { id: 1, name: "Laptops", slug: "laptops", sort_order: 0 },
            { id: 2, name: "Monitors", slug: "monitors", sort_order: 1 },
        ]);
        await wrapper.find("input[placeholder='New category name']").setValue("  Monitors  ");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        const post = state.apiMock.mock.calls.find((c) => c[1]?.method === "POST");
        expect(post?.[0]).toBe("/categories");
        expect(bodyOf(post as unknown[])).toEqual({ name: "Monitors", sort_order: 1 });
        expect(wrapper.text()).toContain("Monitors");
        expect(
            (wrapper.find("input[placeholder='New category name']").element as HTMLInputElement)
                .value,
        ).toBe("");
    });

    it("edits a category name and sort order", async () => {
        const { wrapper } = mountCategories([
            { id: 5, name: "Laptops", slug: "laptops", sort_order: 0 },
        ]);
        await flushPromises();

        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Edit")!
            .trigger("click");
        const [nameInput, orderInput] = wrapper.findAll("li input");
        await nameInput.setValue("Notebooks");
        await orderInput.setValue("3");
        await wrapper.find("li form").trigger("submit.prevent");
        await flushPromises();

        const put = state.apiMock.mock.calls.find((c) => c[1]?.method === "PUT");
        expect(put?.[0]).toBe("/categories/5");
        expect(bodyOf(put as unknown[])).toEqual({ name: "Notebooks", sort_order: 3 });
        expect(wrapper.find("li form").exists()).toBe(false);
    });

    it("cancelling an edit leaves the category untouched", async () => {
        const { wrapper } = mountCategories([
            { id: 5, name: "Laptops", slug: "laptops", sort_order: 0 },
        ]);
        await flushPromises();

        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Edit")!
            .trigger("click");
        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Cancel")!
            .trigger("click");

        expect(wrapper.find("li form").exists()).toBe(false);
        expect(state.apiMock.mock.calls.some((c) => c[1]?.method === "PUT")).toBe(false);
    });

    it("asks for confirmation before deleting and only deletes on confirm", async () => {
        const { wrapper, setCategories } = mountCategories([
            { id: 9, name: "Old", slug: "old", sort_order: 0 },
        ]);
        await flushPromises();

        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Delete")!
            .trigger("click");
        expect(wrapper.text()).toContain('Delete "Old"?');
        expect(state.apiMock.mock.calls.some((c) => c[1]?.method === "DELETE")).toBe(false);

        setCategories([]);
        const dialogButtons = wrapper.findAll(".fixed button");
        await dialogButtons.find((b) => b.text() === "Delete")!.trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/categories/9", { method: "DELETE" });
        expect(wrapper.text()).toContain("No categories yet.");
        expect(wrapper.find(".fixed").exists()).toBe(false);
    });

    it("does not delete when the confirmation is cancelled", async () => {
        const { wrapper } = mountCategories([{ id: 9, name: "Old", slug: "old", sort_order: 0 }]);
        await flushPromises();

        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Delete")!
            .trigger("click");
        await wrapper
            .findAll(".fixed button")
            .find((b) => b.text() === "Cancel")!
            .trigger("click");

        expect(wrapper.find(".fixed").exists()).toBe(false);
        expect(state.apiMock.mock.calls.some((c) => c[1]?.method === "DELETE")).toBe(false);
    });
});
