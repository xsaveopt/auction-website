import { describe, it, expect, vi, beforeEach } from "vitest";
import { reactive, ref } from "vue";
import { mount, flushPromises } from "@vue/test-utils";
import type { AuditLog, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    route: { path: "/admin/audit-log", query: {} as Record<string, string> },
    router: { push: vi.fn(), replace: vi.fn() },
}));

vi.mock("../api", () => ({ api: state.apiMock, ApiError: class extends Error {} }));
vi.mock("vue-router", () => ({
    useRoute: () => state.route,
    useRouter: () => state.router,
}));

import AdminAuditLog from "./AdminAuditLog.vue";

const admin: User = { id: 1, username: "admin", is_admin: true };
const otherAdmin: User = { id: 2, username: "carol", is_admin: true };

function log(overrides: Partial<AuditLog>): AuditLog {
    return {
        id: 1,
        action: "auction.create",
        comment: null,
        data: null,
        created_at: "2026-03-04T12:34:56.000000Z",
        admin,
        target_type: "auction",
        target_id: 10,
        ...overrides,
    };
}

function page(logs: AuditLog[], current = 1, last = 1) {
    return { logs, current_page: current, last_page: last, total: logs.length };
}

type Handler = (url: string, options?: RequestInit) => unknown;

function mountLog(handler: Handler, active = true) {
    state.apiMock.mockImplementation(async (url: string, options?: RequestInit) =>
        handler(url, options),
    );
    return mount(AdminAuditLog, {
        props: { active },
        global: { provide: { user: ref(admin) } },
    });
}

function button(wrapper: ReturnType<typeof mountLog>, text: string) {
    return wrapper.findAll("button").find((b) => b.text() === text)!;
}

describe("AdminAuditLog", () => {
    beforeEach(() => {
        state.apiMock.mockReset();
        state.router.push.mockReset();
        state.router.replace.mockReset();
        state.route = reactive({ path: "/admin/audit-log", query: {} });
    });

    it("renders entries with readable labels, target and details", async () => {
        const wrapper = mountLog(() =>
            page([
                log({ id: 1, action: "auction.cancel", data: { reason: "dupe", qty: 2 } }),
                log({ id: 2, action: "custom.thing", target_type: null, admin: otherAdmin }),
            ]),
        );
        await flushPromises();

        const [first, second] = wrapper.findAll("tbody tr");
        expect(first.text()).toContain("2026-03-04 12:34:56");
        expect(first.text()).toContain("Cancelled auction");
        expect(first.text()).toContain("auction #10");
        expect(first.text()).toContain("reason: dupe · qty: 2");
        expect(second.text()).toContain("custom.thing");
        expect(second.text()).toContain("carol");
        expect(wrapper.text()).toContain("2 total entries");
    });

    it("shows an empty state", async () => {
        const wrapper = mountLog(() => page([]));
        await flushPromises();

        expect(wrapper.text()).toContain("No audit log entries yet.");
    });

    it("shows an error when the log cannot be loaded", async () => {
        const wrapper = mountLog(() => {
            throw new Error("boom");
        });
        await flushPromises();

        expect(wrapper.text()).toContain("Failed to load audit log.");
    });

    it("starts on the page from the query and paginates", async () => {
        state.route.query = { page: "2" };
        const wrapper = mountLog((url) => {
            const p = Number(new URL(url, "http://x").searchParams.get("page"));
            return page([log({ id: p, action: "bid.delete" })], p, 3);
        });
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/admin/audit-log?page=2");
        expect(wrapper.text()).toContain("Page 2 of 3");

        await button(wrapper, "Next").trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenLastCalledWith("/admin/audit-log?page=3");
        expect(wrapper.text()).toContain("Page 3 of 3");
        expect(button(wrapper, "Next").attributes("disabled")).toBeDefined();
        expect(state.router.replace).toHaveBeenLastCalledWith({
            path: "/admin/audit-log",
            query: { page: "3" },
        });

        await button(wrapper, "Previous").trigger("click");
        await flushPromises();
        await button(wrapper, "Previous").trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain("Page 1 of 3");
        expect(state.router.replace).toHaveBeenLastCalledWith({
            path: "/admin/audit-log",
            query: {},
        });
    });

    it("does not touch the URL while inactive", async () => {
        mountLog(() => page([log({})]), false);
        await flushPromises();

        expect(state.router.replace).not.toHaveBeenCalled();
    });

    it("only lets the admin comment on their own entries", async () => {
        const wrapper = mountLog(() => page([log({ id: 1 }), log({ id: 2, admin: otherAdmin })]));
        await flushPromises();

        const [mine, theirs] = wrapper.findAll("tbody tr");
        expect(mine.find("input[type='checkbox']").exists()).toBe(true);
        expect(mine.find("button[title='Add comment']").exists()).toBe(true);
        expect(theirs.find("input[type='checkbox']").exists()).toBe(false);
        expect(theirs.find("button").exists()).toBe(false);
    });

    it("saves a single comment", async () => {
        const wrapper = mountLog((url, options) => {
            if (options?.method === "PATCH") return { comment: "Checked" };
            return page([log({ id: 4 })]);
        });
        await flushPromises();

        await wrapper.find("button[title='Add comment']").trigger("click");
        await wrapper.find("tbody textarea").setValue("Checked");
        await button(wrapper, "Save").trigger("click");
        await flushPromises();

        expect(state.apiMock).toHaveBeenCalledWith("/admin/audit-log/4/comment", {
            method: "PATCH",
            body: JSON.stringify({ comment: "Checked" }),
        });
        expect(wrapper.find("tbody textarea").exists()).toBe(false);
        expect(wrapper.find("tbody").text()).toContain("Checked");
    });

    it("applies a bulk comment to the selected entries", async () => {
        const wrapper = mountLog((url, options) => {
            if (options?.method === "PATCH") return {};
            return page([log({ id: 1 }), log({ id: 2 }), log({ id: 3 })]);
        });
        await flushPromises();

        const checkboxes = wrapper.findAll("tbody input[type='checkbox']");
        await checkboxes[0].trigger("change");
        await checkboxes[2].trigger("change");
        expect(wrapper.text()).toContain("Add comment to 2 selected entries");

        await wrapper.find("textarea").setValue("Batch reviewed");
        await button(wrapper, "Apply to all").trigger("click");
        await flushPromises();

        const call = state.apiMock.mock.calls.find((c) => c[0] === "/admin/audit-log/bulk-comment");
        expect(call?.[1]?.method).toBe("PATCH");
        expect(JSON.parse(call?.[1]?.body as string)).toEqual({
            ids: [1, 3],
            comment: "Batch reviewed",
        });
        const cells = wrapper.findAll("tbody tr").map((r) => r.text().includes("Batch reviewed"));
        expect(cells).toEqual([true, false, true]);
        expect(wrapper.text()).not.toContain("selected entries");
    });

    it("keeps the selection and shows an error when the bulk comment fails", async () => {
        const wrapper = mountLog((url, options) => {
            if (options?.method === "PATCH") throw new Error("nope");
            return page([log({ id: 1 })]);
        });
        await flushPromises();

        await wrapper.find("tbody input[type='checkbox']").trigger("change");
        await button(wrapper, "Apply to all").trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain("Failed to save comments.");
        expect(wrapper.text()).toContain("Add comment to 1 selected entry");
    });
});
