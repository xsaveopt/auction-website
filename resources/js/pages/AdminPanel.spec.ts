import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { reactive, ref } from "vue";
import { mount, enableAutoUnmount } from "@vue/test-utils";
import type { User } from "../types";

const state = vi.hoisted(() => ({
    route: { name: "admin-results" as string, path: "/admin/results", query: {} },
    router: { push: vi.fn(), replace: vi.fn() },
}));

vi.mock("vue-router", () => ({
    useRoute: () => state.route,
    useRouter: () => state.router,
}));

vi.mock("../api", () => ({ api: vi.fn(), ApiError: class extends Error {} }));

import AdminPanel from "./AdminPanel.vue";

const tabStubs = {
    AdminResults: true,
    AdminQuestions: true,
    AdminPriceOffers: true,
    AdminLeftovers: true,
    AdminAuctions: true,
    AdminRounds: true,
    AdminCategories: true,
    AdminAuditLog: true,
    AdminSettings: true,
    CreateAuction: true,
    "router-link": { props: ["to"], template: "<a :href='to'><slot /></a>" },
};

function mountPanel(user: User | null = { id: 1, username: "admin", is_admin: true }) {
    return mount(AdminPanel, {
        attachTo: document.body,
        global: { provide: { user: ref(user) }, stubs: tabStubs },
    });
}

describe("AdminPanel", () => {
    enableAutoUnmount(afterEach);

    beforeEach(() => {
        state.route = reactive({ name: "admin-results", path: "/admin/results", query: {} });
        state.router.push.mockReset();
    });

    it("redirects non-admins to the home page", () => {
        mountPanel({ id: 2, username: "bob", is_admin: false });

        expect(state.router.push).toHaveBeenCalledWith("/");
    });

    it("renders a sidebar link for every admin tab", () => {
        const wrapper = mountPanel();

        const hrefs = wrapper.findAll("nav a").map((a) => a.attributes("href"));
        expect(hrefs).toEqual([
            "/admin/results",
            "/admin/questions",
            "/admin/price-offers",
            "/admin/leftovers",
            "/admin/categories",
            "/admin/auctions",
            "/admin/rounds",
            "/admin/audit-log",
            "/admin/sell",
            "/admin/settings",
        ]);
        expect(state.router.push).not.toHaveBeenCalled();
    });

    it("mounts only the tab matching the current route", () => {
        state.route.name = "admin-categories";
        const wrapper = mountPanel();

        expect(wrapper.findComponent({ name: "AdminCategories" }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: "AdminResults" }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: "AdminAuctions" }).exists()).toBe(false);
    });

    it("falls back to the results tab for an unknown route name", () => {
        state.route.name = "something-else";
        const wrapper = mountPanel();

        expect(wrapper.findComponent({ name: "AdminResults" }).exists()).toBe(true);
    });

    it("keeps visited tabs mounted but hidden and remounts the sell form", async () => {
        state.route.name = "admin-questions";
        const wrapper = mountPanel();

        state.route.name = "admin-sell";
        await wrapper.vm.$nextTick();

        const questions = wrapper.findComponent({ name: "AdminQuestions" });
        expect(questions.exists()).toBe(true);
        expect(questions.isVisible()).toBe(false);
        expect(wrapper.findComponent({ name: "CreateAuction" }).exists()).toBe(true);

        state.route.name = "admin-questions";
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: "CreateAuction" }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: "AdminQuestions" }).isVisible()).toBe(true);
    });
});
