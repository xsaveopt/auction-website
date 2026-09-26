import { describe, it, expect, vi, beforeEach } from "vitest";

vi.mock("./pages/AuctionList.vue", () => ({
    default: { name: "AuctionList", render: () => null },
}));
vi.mock("./pages/AuctionDetail.vue", () => ({
    default: { name: "AuctionDetail", render: () => null },
}));
vi.mock("./pages/EditAuction.vue", () => ({
    default: { name: "EditAuction", render: () => null },
}));
vi.mock("./pages/AdminPanel.vue", () => ({ default: { name: "AdminPanel", render: () => null } }));
vi.mock("./pages/Login.vue", () => ({ default: { name: "Login", render: () => null } }));
vi.mock("./pages/Register.vue", () => ({ default: { name: "Register", render: () => null } }));
vi.mock("./pages/MyDashboard.vue", () => ({
    default: { name: "MyDashboard", render: () => null },
}));

import router from "./router";

async function visit(path: string) {
    await router.push(path);
    return router.currentRoute.value;
}

describe("router", () => {
    beforeEach(async () => {
        await router.push("/");
    });

    it("redirects /admin to the results tab by default", async () => {
        const route = await visit("/admin");

        expect(route.path).toBe("/admin/results");
        expect(route.name).toBe("admin-results");
        expect(route.query).toEqual({});
    });

    it("redirects the legacy tab query to the matching admin path", async () => {
        const route = await visit("/admin?tab=priceOffers");

        expect(route.path).toBe("/admin/price-offers");
        expect(route.name).toBe("admin-price-offers");
    });

    it("keeps only the query keys the target tab understands", async () => {
        const results = await visit("/admin?tab=results&view=auctions&round_id=4&page=2");
        expect(results.path).toBe("/admin/results");
        expect(results.query).toEqual({ view: "auctions", round_id: "4" });

        const auditLog = await visit("/admin?tab=auditLog&page=3&view=users");
        expect(auditLog.path).toBe("/admin/audit-log");
        expect(auditLog.query).toEqual({ page: "3" });

        const settings = await visit("/admin?tab=settings&page=3");
        expect(settings.path).toBe("/admin/settings");
        expect(settings.query).toEqual({});
    });

    it("falls back to the results tab for unknown or repeated tab values", async () => {
        expect((await visit("/admin?tab=nope")).path).toBe("/admin/results");
        expect((await visit("/admin?tab=rounds&tab=sell")).path).toBe("/admin/results");
    });

    it("redirects the old create auction path to the sell tab", async () => {
        const route = await visit("/auctions/new");

        expect(route.path).toBe("/admin/sell");
        expect(route.name).toBe("admin-sell");
    });

    it("passes the auction id as a prop to detail and edit routes", async () => {
        const detail = await visit("/auctions/12");
        expect(detail.params).toEqual({ id: "12" });
        expect(detail.matched[0].props).toEqual({ default: true });

        const edit = await visit("/auctions/12/edit");
        expect(edit.matched[0].path).toBe("/auctions/:id/edit");
    });

    it("resolves the public pages", () => {
        for (const path of ["/", "/dashboard", "/login", "/register"]) {
            expect(router.resolve(path).matched).toHaveLength(1);
        }
    });
});
