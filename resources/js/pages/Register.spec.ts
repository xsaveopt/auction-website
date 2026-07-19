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

import Register from "./Register.vue";
import { ApiError } from "../api";

function mountRegister(onLogin = vi.fn()) {
    return mount(Register, {
        global: { provide: { onLogin }, stubs: { "router-link": true } },
    });
}

describe("Register", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("registers and calls onLogin with the new user", async () => {
        apiMock.mockResolvedValueOnce({ enabled: false });
        const onLogin = vi.fn();
        const wrapper = mountRegister(onLogin);
        await flushPromises();

        apiMock.mockResolvedValueOnce({ user: { id: 7, username: "bob" } });
        await wrapper.find("input[type='text']").setValue("bob");
        await wrapper.find("input[type='password']").setValue("secret");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(apiMock).toHaveBeenCalledWith(
            "/register",
            expect.objectContaining({ method: "POST" }),
        );
        expect(onLogin).toHaveBeenCalledWith({ id: 7, username: "bob" });
    });

    it("renders field validation errors from the API", async () => {
        apiMock.mockResolvedValueOnce({ enabled: false });
        const wrapper = mountRegister();
        await flushPromises();

        apiMock.mockRejectedValueOnce(
            new ApiError(422, { errors: { username: ["The username has already been taken."] } }),
        );
        await wrapper.find("input[type='text']").setValue("taken");
        await wrapper.find("input[type='password']").setValue("secret");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("The username has already been taken.");
    });
});
