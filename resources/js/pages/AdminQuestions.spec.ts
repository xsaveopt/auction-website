import { describe, it, expect, vi, beforeEach } from "vitest";
import { defineComponent, h, ref } from "vue";
import { mount, flushPromises, type VueWrapper } from "@vue/test-utils";
import type { AuctionQuestion, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    route: { path: "/admin/questions", query: {} as Record<string, string | undefined> },
    router: { push: vi.fn(), replace: vi.fn() },
    questions: [] as unknown[],
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
vi.mock("vue-router", () => ({
    useRoute: () => state.route,
    useRouter: () => state.router,
}));

import AdminQuestions from "./AdminQuestions.vue";
import { ApiError } from "../api";

const RouterLinkStub = defineComponent({
    props: { to: { type: String, required: true } },
    setup(props, { slots }) {
        return () => h("a", { href: props.to }, slots.default?.());
    },
});

const admin: User = { id: 1, username: "admin", is_admin: true };

function question(overrides: Partial<AuctionQuestion> = {}): AuctionQuestion {
    return {
        id: 1,
        question: "Does it come with a charger?",
        answer: null,
        created_at: "2026-02-01T09:30:00Z",
        user: { id: 2, username: "alice" },
        auction: {
            id: 10,
            title: "Laptop",
            starting_price: "100.00",
            quantity: 1,
            ends_at: "2026-03-01T10:00:00Z",
            status: "active",
            images: [],
        },
        ...overrides,
    };
}

async function mountQuestions(questions: AuctionQuestion[], user: User = admin) {
    state.questions = questions;
    state.apiMock.mockImplementation(async (url: string, init?: RequestInit) => {
        if (url === "/rounds") return { rounds: [] };
        if (url === "/rounds/current") return { active: null };
        if (url.startsWith("/questions") && !init) return { questions: state.questions };
        return {};
    });
    const wrapper = mount(AdminQuestions, {
        global: {
            provide: { user: ref(user) },
            stubs: { "router-link": RouterLinkStub },
        },
    });
    await flushPromises();
    return wrapper;
}

function button(wrapper: VueWrapper, label: string) {
    const found = wrapper.findAll("button").find((b) => b.text() === label);
    expect(found).toBeDefined();
    return found!;
}

describe("AdminQuestions", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
        state.route.query = {};
    });

    it("redirects non-admin users to the home page", async () => {
        await mountQuestions([], { id: 2, username: "bob", is_admin: false });

        expect(state.router.push).toHaveBeenCalledWith("/");
    });

    it("shows an empty state when there are no questions", async () => {
        const wrapper = await mountQuestions([]);

        expect(wrapper.text()).toContain("No questions yet.");
    });

    it("splits questions into awaiting and answered sections", async () => {
        const wrapper = await mountQuestions([
            question(),
            question({
                id: 2,
                question: "Is it boxed?",
                answer: "Yes, original box.",
                answered_at: "2026-02-02T11:00:00Z",
            }),
        ]);

        expect(wrapper.text()).toContain("Awaiting Answer (1)");
        expect(wrapper.text()).toContain("Answered (1)");
        expect(wrapper.text()).toContain("Asked by alice · 2026-02-01 09:30");
        expect(wrapper.text()).toContain("Yes, original box.");
        expect(wrapper.text()).toContain("Answered 2026-02-02 11:00");
        expect(wrapper.find("a").attributes("href")).toBe("/auctions/10");
    });

    it("publishes a trimmed answer and shows the refreshed list", async () => {
        const wrapper = await mountQuestions([question()]);

        await button(wrapper, "Answer question").trigger("click");
        await wrapper.find("textarea").setValue("  Yes, included.  ");
        state.questions = [question({ answer: "Yes, included." })];
        await button(wrapper, "Publish answer").trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/questions/1", {
            method: "PUT",
            body: JSON.stringify({ answer: "Yes, included." }),
        });
        expect(wrapper.find("textarea").exists()).toBe(false);
        expect(wrapper.text()).toContain("Answered (1)");
        expect(wrapper.text()).toContain("Yes, included.");
    });

    it("prefills the editor with the existing answer when updating", async () => {
        const wrapper = await mountQuestions([question({ answer: "Old answer" })]);

        await button(wrapper, "Update answer").trigger("click");

        expect((wrapper.find("textarea").element as HTMLTextAreaElement).value).toBe("Old answer");
        expect(wrapper.findAll("button").some((b) => b.text() === "Save answer")).toBe(true);
    });

    it("requires a non-empty answer before calling the API", async () => {
        const wrapper = await mountQuestions([question()]);
        const callsBefore = state.apiMock.mock.calls.length;

        await button(wrapper, "Answer question").trigger("click");
        await wrapper.find("textarea").setValue("   ");
        await button(wrapper, "Publish answer").trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain("Answer is required.");
        expect(state.apiMock.mock.calls.length).toBe(callsBefore);
    });

    it("shows the validation error when saving an answer fails", async () => {
        const wrapper = await mountQuestions([question()]);

        await button(wrapper, "Answer question").trigger("click");
        await wrapper.find("textarea").setValue("Maybe");
        state.apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { answer: ["The answer is too short."] } }),
        );
        await button(wrapper, "Publish answer").trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain("The answer is too short.");
        expect(wrapper.find("textarea").exists()).toBe(true);
    });

    it("cancelling the editor hides it without saving", async () => {
        const wrapper = await mountQuestions([question()]);
        const callsBefore = state.apiMock.mock.calls.length;

        await button(wrapper, "Answer question").trigger("click");
        await button(wrapper, "Cancel").trigger("click");

        expect(wrapper.find("textarea").exists()).toBe(false);
        expect(state.apiMock.mock.calls.length).toBe(callsBefore);
    });

    it("deletes a question only after confirmation", async () => {
        const wrapper = await mountQuestions([question()]);

        await button(wrapper, "Delete question").trigger("click");
        expect(wrapper.text()).toContain("Delete this question?");

        state.questions = [];
        await button(wrapper, "Delete").trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/questions/1", { method: "DELETE" });
        expect(wrapper.text()).not.toContain("Delete this question?");
        expect(wrapper.text()).toContain("No questions yet.");
    });

    it("does not delete when the confirmation is cancelled", async () => {
        const wrapper = await mountQuestions([question()]);

        await button(wrapper, "Delete question").trigger("click");
        await button(wrapper, "Cancel").trigger("click");
        await flushPromises();

        expect(state.apiMock).not.toHaveBeenCalledWith("/questions/1", { method: "DELETE" });
        expect(wrapper.text()).not.toContain("Delete this question?");
    });

    it("shows the API error when deleting fails", async () => {
        const wrapper = await mountQuestions([question()]);

        await button(wrapper, "Delete question").trigger("click");
        state.apiMock.mockRejectedValueOnce(new ApiError(403, { message: "Forbidden" }));
        await button(wrapper, "Delete").trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain("Forbidden");
        expect(wrapper.text()).toContain("Does it come with a charger?");
    });
});
