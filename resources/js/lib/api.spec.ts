import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { api, ApiError } from "./api";

function jsonResponse(status: number, data: unknown): Response {
    return new Response(JSON.stringify(data), { status });
}

function textResponse(status: number, body: string): Response {
    return new Response(body, { status });
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

    it("reloads the page and rejects with an ApiError on a 419 response", async () => {
        vi.spyOn(globalThis, "fetch").mockResolvedValue(jsonResponse(419, {}));
        const reloadMock = vi.fn();
        vi.stubGlobal("location", { ...window.location, reload: reloadMock });

        await expect(api("/foo")).rejects.toMatchObject({ name: "ApiError", status: 419 });
        expect(reloadMock).toHaveBeenCalledTimes(1);

        vi.unstubAllGlobals();
    });

    it("turns a non-JSON error page into an ApiError", async () => {
        vi.spyOn(globalThis, "fetch").mockResolvedValue(
            textResponse(502, "<html>Bad Gateway</html>"),
        );

        const error = await api("/foo").catch((e: unknown) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect((error as ApiError).status).toBe(502);
        expect((error as ApiError).message).toBe("Request failed (502).");
    });

    it("rejects a successful response whose body is not JSON", async () => {
        vi.spyOn(globalThis, "fetch").mockResolvedValue(textResponse(200, "<html>proxy</html>"));

        await expect(api("/foo")).rejects.toBeInstanceOf(ApiError);
    });

    it("resolves an empty successful body to an empty object", async () => {
        vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(null, { status: 204 }));

        await expect(api("/foo")).resolves.toEqual({});
    });
});
