import { describe, it, expect, vi, beforeEach } from "vitest";
import { defineComponent, h, ref } from "vue";
import { mount, flushPromises, type VueWrapper } from "@vue/test-utils";
import type { AuctionRound, User } from "../types";

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

import AdminRounds from "./AdminRounds.vue";
import { ApiError } from "../api";

const RouterLinkStub = defineComponent({
    props: { to: { type: String, required: true } },
    setup(props, { slots }) {
        return () => h("a", { href: props.to }, slots.default?.());
    },
});

const admin: User = { id: 1, username: "admin", is_admin: true };

function round(overrides: Partial<AuctionRound> = {}): AuctionRound {
    return { id: 1, name: "Spring", status: "active", auction_count: 4, ...overrides };
}

async function mountRounds(rounds: AuctionRound[], user: User | null = admin) {
    state.apiMock.mockResolvedValueOnce({ rounds });
    const wrapper = mount(AdminRounds, {
        global: {
            provide: { user: ref(user) },
            stubs: { "router-link": RouterLinkStub },
        },
    });
    await flushPromises();
    return wrapper;
}

function button(wrapper: VueWrapper, label: string) {
    const found = wrapper
        .find("tbody")
        .findAll("button")
        .find((b) => b.text() === label);
    expect(found).toBeDefined();
    return found!;
}

function dialogButton(wrapper: VueWrapper, label: string) {
    const found = wrapper
        .find("div.fixed")
        .findAll("button")
        .find((b) => b.text() === label);
    expect(found).toBeDefined();
    return found!;
}

describe("AdminRounds", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
    });

    it("redirects non-admin users to the home page", async () => {
        await mountRounds([], { id: 2, username: "bob", is_admin: false });

        expect(state.router.push).toHaveBeenCalledWith("/");
    });

    it("shows an empty state when there are no rounds", async () => {
        const wrapper = await mountRounds([]);

        expect(state.apiMock).toHaveBeenCalledWith("/rounds");
        expect(wrapper.text()).toContain("No rounds yet");
    });

    it("lists rounds and blocks creating a new round while one is active", async () => {
        const wrapper = await mountRounds([
            round(),
            round({ id: 2, name: "Winter", status: "ended", ends_at: "2026-01-31T17:00:00Z" }),
        ]);

        const rows = wrapper.findAll("tbody tr");
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain("Spring");
        expect(rows[0].text()).toContain("Active");
        expect(rows[1].text()).toContain("Ended");
        expect(rows[1].text()).toContain("2026-01-31 17:00");
        expect(rows[1].find("a").attributes("href")).toBe("/admin/results?round_id=2");
        expect(wrapper.text()).toContain("A round is currently active");
        expect(wrapper.find("input[type='text']").attributes("disabled")).toBeDefined();
    });

    it("creates a round with a trimmed name and prepends it to the list", async () => {
        const wrapper = await mountRounds([round({ status: "ended" })]);

        state.apiMock.mockResolvedValueOnce({ round: round({ id: 5, name: "Summer" }) });
        await wrapper.find("input[type='text']").setValue("  Summer  ");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/rounds", {
            method: "POST",
            body: JSON.stringify({ name: "Summer" }),
        });
        expect(wrapper.findAll("tbody tr")[0].text()).toContain("Summer");
        expect((wrapper.find("input[type='text']").element as HTMLInputElement).value).toBe("");
    });

    it("shows the API error when creating a round fails", async () => {
        const wrapper = await mountRounds([]);

        state.apiMock.mockRejectedValueOnce(new ApiError(422, { message: "Name taken" }));
        await wrapper.find("input[type='text']").setValue("Spring");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("Name taken");
    });

    it("closes a round only after confirmation and updates its row", async () => {
        const wrapper = await mountRounds([round({ id: 3 })]);

        await button(wrapper, "Close Round").trigger("click");
        expect(wrapper.text()).toContain('Close "Spring"?');

        state.apiMock.mockResolvedValueOnce({ round: round({ id: 3, status: "ended" }) });
        await dialogButton(wrapper, "Close Round").trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/rounds/3/close", { method: "POST" });
        expect(wrapper.text()).not.toContain('Close "Spring"?');
        expect(wrapper.find("tbody tr").text()).toContain("Ended");
        expect(wrapper.find("tbody tr a").attributes("href")).toBe("/admin/results?round_id=3");
    });

    it("does not close the round when the confirmation is cancelled", async () => {
        const wrapper = await mountRounds([round()]);

        await button(wrapper, "Close Round").trigger("click");
        await dialogButton(wrapper, "Cancel").trigger("click");

        expect(state.apiMock).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).not.toContain('Close "Spring"?');
    });

    it("shows the API error when closing a round fails", async () => {
        const wrapper = await mountRounds([round()]);

        await button(wrapper, "Close Round").trigger("click");
        state.apiMock.mockRejectedValueOnce(new ApiError(500, {}));
        await dialogButton(wrapper, "Close Round").trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain("Failed to close round.");
        expect(wrapper.find("tbody tr").text()).toContain("Active");
    });
});
