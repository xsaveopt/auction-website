import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

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
    return { api: apiMock, ApiError };
});

import Login from "./Login.vue";
import { ApiError } from "../api";

function mountLogin(onLogin = vi.fn()) {
    return mount(Login, {
        global: { provide: { onLogin }, stubs: { "router-link": true } },
    });
}

describe("Login", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("shows the Microsoft SSO link when SSO is enabled", async () => {
        apiMock.mockResolvedValueOnce({ enabled: true });
        const wrapper = mountLogin();
        await flushPromises();

        expect(wrapper.html()).toContain("/auth/microsoft/redirect");
        expect(wrapper.text()).toContain("Microsoft");
    });

    it("logs in and calls onLogin with the returned user", async () => {
        apiMock.mockResolvedValueOnce({ enabled: false });
        const onLogin = vi.fn();
        const wrapper = mountLogin(onLogin);
        await flushPromises();

        apiMock.mockResolvedValueOnce({ user: { id: 1, username: "alice" } });
        await wrapper.find("input[type='text']").setValue("alice");
        await wrapper.find("input[type='password']").setValue("secret");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith("/login", expect.objectContaining({ method: "POST" }));
        expect(onLogin).toHaveBeenCalledWith({ id: 1, username: "alice" });
    });

    it("renders an error message on failed login", async () => {
        apiMock.mockResolvedValueOnce({ enabled: false });
        const wrapper = mountLogin();
        await flushPromises();

        apiMock.mockRejectedValueOnce(new ApiError(422, { message: "Invalid credentials" }));
        await wrapper.find("input[type='text']").setValue("alice");
        await wrapper.find("input[type='password']").setValue("bad");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("Invalid credentials");
    });
});
