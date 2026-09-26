import { describe, it, expect, beforeEach } from "vitest";
import type { RouteLocationNormalizedLoaded } from "vue-router";
import { presencePayload } from "./presence";

function route(path: string, params: Record<string, string> = {}): RouteLocationNormalizedLoaded {
    return { path, params } as unknown as RouteLocationNormalizedLoaded;
}

describe("presencePayload", () => {
    beforeEach(() => {
        window.localStorage.clear();
        window.sessionStorage.clear();
    });

    it("marks the root path as the home page", () => {
        const payload = presencePayload(route("/"));

        expect(payload.page_type).toBe("home");
        expect(payload.path).toBe("/");
        expect(payload.auction_id).toBeUndefined();
    });

    it("marks an auction detail path as an auction page with its id", () => {
        const payload = presencePayload(route("/auctions/42", { id: "42" }));

        expect(payload.page_type).toBe("auction");
        expect(payload.auction_id).toBe(42);
    });

    it("treats nested auction paths and non numeric ids as generic pages", () => {
        expect(presencePayload(route("/auctions/42/edit", { id: "42" })).page_type).toBe("page");

        const invalid = presencePayload(route("/auctions/abc", { id: "abc" }));
        expect(invalid.page_type).toBe("page");
        expect(invalid.auction_id).toBeUndefined();
    });

    it("keeps a stable client id in localStorage and page id in sessionStorage", () => {
        const first = presencePayload(route("/dashboard"));
        const second = presencePayload(route("/"));

        expect(first.client_id).toBeTruthy();
        expect(first.page_id).toBeTruthy();
        expect(second.client_id).toBe(first.client_id);
        expect(second.page_id).toBe(first.page_id);
        expect(window.localStorage.getItem("auction-presence-client-id")).toBe(first.client_id);
        expect(window.sessionStorage.getItem("auction-presence-page-id")).toBe(first.page_id);
    });

    it("reuses identifiers that are already stored", () => {
        window.localStorage.setItem("auction-presence-client-id", "client-1");
        window.sessionStorage.setItem("auction-presence-page-id", "page-1");

        const payload = presencePayload(route("/"));

        expect(payload.client_id).toBe("client-1");
        expect(payload.page_id).toBe("page-1");
    });
});
