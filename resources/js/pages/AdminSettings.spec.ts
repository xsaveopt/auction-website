import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../lib/api", () => {
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

import AdminSettings from "./AdminSettings.vue";
import { ApiError } from "../lib/api";

async function mountSettings(
    settings: Record<string, unknown> | Error,
    apiKey: { exists: boolean; created_at: string | null } = { exists: false, created_at: null },
) {
    if (settings instanceof Error) {
        apiMock.mockRejectedValueOnce(settings);
    } else {
        apiMock.mockResolvedValueOnce({ settings });
    }
    apiMock.mockResolvedValueOnce({ api_key: apiKey });
    const wrapper = mount(AdminSettings);
    await flushPromises();
    return wrapper;
}

function lastBody(): Record<string, unknown> {
    const [, init] = apiMock.mock.calls[apiMock.mock.calls.length - 1];
    return JSON.parse(init.body);
}

describe("AdminSettings", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("fills the form with the stored settings", async () => {
        const wrapper = await mountSettings({
            is_locked: true,
            lock_message: "Back soon",
            currency_symbol: "€",
            company_name: "Acme BV",
        });

        expect(apiMock).toHaveBeenCalledWith("/admin/settings");
        const checkbox = wrapper.find("input[type='checkbox']").element as HTMLInputElement;
        expect(checkbox.checked).toBe(true);
        const values = wrapper
            .findAll("input[type='text']")
            .map((i) => (i.element as HTMLInputElement).value);
        expect(values).toContain("Back soon");
        expect(values).toContain("€");
        expect(values).toContain("Acme BV");
    });

    it("falls back to the defaults when loading fails", async () => {
        const wrapper = await mountSettings(new Error("boom"));

        expect(wrapper.find("form").exists()).toBe(true);
        apiMock.mockResolvedValueOnce({});
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(lastBody()).toMatchObject({
            is_locked: false,
            currency_symbol: "$",
            bidding_closed_start: "09:00",
            anti_sniping_window: 60,
            invoice_payment_days: 30,
        });
    });

    it("saves the edited form with a PUT and briefly confirms", async () => {
        const wrapper = await mountSettings({ currency_symbol: "€", company_name: "Acme BV" });
        vi.useFakeTimers();

        const currency = wrapper
            .findAll("input[type='text']")
            .find((i) => (i.element as HTMLInputElement).value === "€")!;
        await currency.setValue("£");
        await wrapper.find("input[type='number']").setValue("90");
        apiMock.mockResolvedValueOnce({});
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(apiMock).toHaveBeenLastCalledWith(
            "/admin/settings",
            expect.objectContaining({ method: "PUT" }),
        );
        expect(lastBody()).toMatchObject({
            currency_symbol: "£",
            company_name: "Acme BV",
            anti_sniping_window: 90,
        });
        expect(wrapper.text()).toContain("Saved.");

        await vi.advanceTimersByTimeAsync(3000);
        expect(wrapper.text()).not.toContain("Saved.");
    });

    it("shows the error message when saving fails", async () => {
        const wrapper = await mountSettings({});

        apiMock.mockRejectedValueOnce(new ApiError(422, { message: "Invalid currency" }));
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("Invalid currency");
        expect(wrapper.text()).not.toContain("Saved.");
        expect(wrapper.find("button[type='submit']").text()).toBe("Save settings");
    });

    it("generates an api key and shows it once", async () => {
        const wrapper = await mountSettings({});

        expect(apiMock).toHaveBeenCalledWith("/admin/api-key");
        expect(wrapper.find("[data-testid='api-key']").text()).toContain("No api key yet.");

        apiMock.mockResolvedValueOnce({
            key: "auk_secret",
            api_key: { exists: true, created_at: "2026-09-28T10:00:00.000000Z" },
        });
        await wrapper.find("[data-testid='generate-api-key']").trigger("click");
        await flushPromises();

        expect(apiMock).toHaveBeenLastCalledWith(
            "/admin/api-key",
            expect.objectContaining({ method: "POST" }),
        );
        const input = wrapper.find("[data-testid='new-api-key']").element as HTMLInputElement;
        expect(input.value).toBe("auk_secret");
        expect(wrapper.find("[data-testid='generate-api-key']").text()).toBe("Regenerate key");
    });

    it("asks for confirmation before regenerating an existing key", async () => {
        const wrapper = await mountSettings(
            {},
            { exists: true, created_at: "2026-09-28T10:00:00.000000Z" },
        );
        const callsBefore = apiMock.mock.calls.length;

        await wrapper.find("[data-testid='generate-api-key']").trigger("click");
        expect(apiMock.mock.calls.length).toBe(callsBefore);
        expect(wrapper.text()).toContain("Your current api key stops working immediately.");

        apiMock.mockResolvedValueOnce({
            key: "auk_new",
            api_key: { exists: true, created_at: "2026-09-28T11:00:00.000000Z" },
        });
        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Regenerate")!
            .trigger("click");
        await flushPromises();

        expect(apiMock).toHaveBeenLastCalledWith(
            "/admin/api-key",
            expect.objectContaining({ method: "POST" }),
        );
        expect(
            (wrapper.find("[data-testid='new-api-key']").element as HTMLInputElement).value,
        ).toBe("auk_new");
    });

    it("revokes the api key after confirmation", async () => {
        const wrapper = await mountSettings(
            {},
            { exists: true, created_at: "2026-09-28T10:00:00.000000Z" },
        );

        await wrapper.find("[data-testid='revoke-api-key']").trigger("click");
        apiMock.mockResolvedValueOnce({ api_key: { exists: false, created_at: null } });
        await wrapper
            .findAll("button")
            .find((b) => b.text() === "Revoke" && !b.attributes("data-testid"))!
            .trigger("click");
        await flushPromises();

        expect(apiMock).toHaveBeenLastCalledWith(
            "/admin/api-key",
            expect.objectContaining({ method: "DELETE" }),
        );
        expect(wrapper.find("[data-testid='revoke-api-key']").exists()).toBe(false);
        expect(wrapper.find("[data-testid='api-key']").text()).toContain("No api key yet.");
    });
});
