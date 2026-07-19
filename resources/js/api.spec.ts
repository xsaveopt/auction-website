import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { api, ApiError } from "./api";

function jsonResponse(status: number, data: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => data,
    } as Response;
}

describe("api", () => {
    beforeEach(() => {
        document.cookie = "XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("sends Accept and X-XSRF-TOKEN headers read from the cookie", async () => {
        document.cookie = "XSRF-TOKEN=abc123";
        const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(jsonResponse(200, {}));

        await api("/foo");

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        const headers = init.headers as Record<string, string>;
        expect(headers.Accept).toBe("application/json");
        expect(headers["X-XSRF-TOKEN"]).toBe("abc123");
    });

    it("sets Content-Type: application/json for non-FormData bodies", async () => {
        const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(jsonResponse(200, {}));

        await api("/foo", { method: "POST", body: JSON.stringify({ a: 1 }) });

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        const headers = init.headers as Record<string, string>;
        expect(headers["Content-Type"]).toBe("application/json");
    });

    it("does not set Content-Type for FormData bodies", async () => {
        const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(jsonResponse(200, {}));

        await api("/foo", { method: "POST", body: new FormData() });

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        const headers = init.headers as Record<string, string>;
        expect(headers["Content-Type"]).toBeUndefined();
    });

    it("returns parsed JSON when the response is ok", async () => {
        vi.spyOn(globalThis, "fetch").mockResolvedValue(jsonResponse(200, { hello: "world" }));

        const result = await api<{ hello: string }>("/foo");

        expect(result).toEqual({ hello: "world" });
    });

    it("throws an ApiError with status and data when the response is not ok", async () => {
        vi.spyOn(globalThis, "fetch").mockResolvedValue(
            jsonResponse(422, { message: "Invalid", errors: { username: ["required"] } }),
        );

        await expect(api("/foo")).rejects.toMatchObject({
            status: 422,
            data: { message: "Invalid", errors: { username: ["required"] } },
        });

        try {
            await api("/foo");
        } catch (e) {
            expect(e).toBeInstanceOf(ApiError);
        }
    });

    it("reloads the page and returns undefined on a 419 response", async () => {
        vi.spyOn(globalThis, "fetch").mockResolvedValue(jsonResponse(419, {}));
        const reloadMock = vi.fn();
        vi.stubGlobal("location", { ...window.location, reload: reloadMock });

        const result = await api("/foo");

        expect(reloadMock).toHaveBeenCalledTimes(1);
        expect(result).toBeUndefined();

        vi.unstubAllGlobals();
    });
});
