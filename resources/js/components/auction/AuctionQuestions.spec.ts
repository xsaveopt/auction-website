import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import type { AuctionQuestion } from "../../types";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../../api", () => {
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
    return { api: apiMock, ApiError };
});

import AuctionQuestions from "./AuctionQuestions.vue";
import { ApiError } from "../../api";

const answered: AuctionQuestion = {
    id: 1,
    question: "Is the battery new?",
    answer: "Yes, replaced last month.",
    answered_at: "2026-03-02T10:15:00Z",
    created_at: "2026-03-01T09:00:00Z",
    user: { id: 3, username: "carol" },
};

const open: AuctionQuestion = {
    id: 2,
    question: "Does it come with a charger?",
    answer: null,
    created_at: "2026-03-03T12:30:00Z",
    user: { id: 4, username: "dave" },
};

function mountQuestions(
    overrides: Partial<{
        questions: AuctionQuestion[];
        canModerate: boolean;
        canAsk: boolean;
        isSeller: boolean;
    }> = {},
) {
    return mount(AuctionQuestions, {
        props: {
            auctionId: "5",
            questions: [answered, open],
            canModerate: false,
            canAsk: true,
            isSeller: false,
            ...overrides,
        },
        global: { stubs: { "router-link": { template: "<a><slot /></a>" } } },
    });
}

function buttonByText(wrapper: ReturnType<typeof mountQuestions>, text: string) {
    const button = wrapper.findAll("button").find((b) => b.text() === text);
    if (!button) throw new Error(`No button with text ${text}`);
    return button;
}

describe("AuctionQuestions", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("splits answered questions into the FAQ and lists open ones separately", () => {
        const wrapper = mountQuestions();
        const text = wrapper.text();

        expect(text).toContain("1 answered");
        expect(text).toContain("1 awaiting an answer");
        expect(text).toContain("Yes, replaced last month.");
        expect(text).toContain("Answered 2026-03-02 10:15");
        expect(text).toContain("Does it come with a charger?");
        expect(text).toContain("Awaiting answer");
    });

    it("shows empty states when there are no questions", () => {
        const wrapper = mountQuestions({ questions: [] });

        expect(wrapper.text()).toContain("No answered questions yet.");
        expect(wrapper.text()).toContain("No open questions right now.");
    });

    it("hides moderation controls from regular users", () => {
        const wrapper = mountQuestions();

        expect(wrapper.text()).not.toContain("Answer question");
        expect(wrapper.text()).not.toContain("Delete question");
        expect(wrapper.text()).not.toContain("Update answer");
    });

    it("prompts guests to log in and tells sellers they can moderate", () => {
        const guest = mountQuestions({ canAsk: false });
        expect(guest.find("form").exists()).toBe(false);
        expect(guest.text()).toContain("Log in");

        const seller = mountQuestions({ canAsk: false, isSeller: true });
        expect(seller.text()).toContain("You can answer or remove questions");
    });

    it("rejects an empty question without calling the API", async () => {
        const wrapper = mountQuestions();

        await wrapper.find("textarea").setValue("   ");
        await wrapper.find("form").trigger("submit.prevent");

        expect(apiMock).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain("Question is required.");
    });

    it("posts a trimmed question and asks the parent to refresh", async () => {
        apiMock.mockResolvedValueOnce({});
        const wrapper = mountQuestions();

        await wrapper.find("textarea").setValue("  Any scratches?  ");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/auctions/5/questions", {
            method: "POST",
            body: JSON.stringify({ question: "Any scratches?" }),
        });
        expect(wrapper.emitted("refresh")).toHaveLength(1);
        expect((wrapper.find("textarea").element as HTMLTextAreaElement).value).toBe("");
    });

    it("shows the field error when asking fails validation", async () => {
        apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { question: ["The question is too short."] } }),
        );
        const wrapper = mountQuestions();

        await wrapper.find("textarea").setValue("Hm");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("The question is too short.");
        expect(wrapper.emitted("refresh")).toBeUndefined();
    });

    it("lets a moderator publish an answer to an open question", async () => {
        apiMock.mockResolvedValueOnce({});
        const wrapper = mountQuestions({ canModerate: true, canAsk: false, isSeller: true });

        await buttonByText(wrapper, "Answer question").trigger("click");
        await wrapper.find("textarea").setValue(" Yes, the original one. ");
        await buttonByText(wrapper, "Publish answer").trigger("click");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/questions/2", {
            method: "PUT",
            body: JSON.stringify({ answer: "Yes, the original one." }),
        });
        expect(wrapper.emitted("refresh")).toHaveLength(1);
        expect(wrapper.text()).not.toContain("Publish answer");
    });

    it("requires an answer before saving", async () => {
        const wrapper = mountQuestions({ canModerate: true, canAsk: false });

        await buttonByText(wrapper, "Answer question").trigger("click");
        await buttonByText(wrapper, "Publish answer").trigger("click");

        expect(apiMock).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain("Answer is required.");
    });

    it("prefills the existing answer when updating and can cancel the edit", async () => {
        const wrapper = mountQuestions({ canModerate: true, canAsk: false });

        await buttonByText(wrapper, "Update answer").trigger("click");
        expect((wrapper.find("textarea").element as HTMLTextAreaElement).value).toBe(
            "Yes, replaced last month.",
        );

        await buttonByText(wrapper, "Cancel").trigger("click");
        expect(wrapper.find("textarea").exists()).toBe(false);
    });

    it("deletes a question only after confirmation", async () => {
        apiMock.mockResolvedValueOnce({});
        const wrapper = mountQuestions({ canModerate: true, canAsk: false });

        await wrapper
            .findAll("button")
            .filter((b) => b.text() === "Delete question")[1]
            .trigger("click");

        expect(apiMock).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain("Delete this question?");

        await buttonByText(wrapper, "Delete").trigger("click");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/questions/2", { method: "DELETE" });
        expect(wrapper.emitted("refresh")).toHaveLength(1);
        expect(wrapper.text()).not.toContain("Delete this question?");
    });

    it("keeps the question when the delete dialog is cancelled", async () => {
        const wrapper = mountQuestions({ canModerate: true, canAsk: false });

        await buttonByText(wrapper, "Delete question").trigger("click");
        const dialogCancel = wrapper.findAll("button").filter((b) => b.text() === "Cancel")[0];
        await dialogCancel.trigger("click");

        expect(apiMock).not.toHaveBeenCalled();
        expect(wrapper.text()).not.toContain("Delete this question?");
    });

    it("shows the API message when deleting fails", async () => {
        apiMock.mockRejectedValueOnce(new ApiError(403, { message: "Not allowed." }));
        const wrapper = mountQuestions({ canModerate: true, canAsk: false });

        await buttonByText(wrapper, "Delete question").trigger("click");
        await buttonByText(wrapper, "Delete").trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain("Not allowed.");
        expect(wrapper.emitted("refresh")).toBeUndefined();
    });
});
