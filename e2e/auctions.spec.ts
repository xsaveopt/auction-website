import { test, expect, type Browser } from "@playwright/test";
import { apiHeaders, loginAsAdmin, registerUser } from "./session";

const title = `E2E Lamp ${Date.now()}`;
let auctionId: number;

async function seedAuction(browser: Browser) {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsAdmin(page);

    const settings = await page.request.put("/api/admin/settings", {
        headers: await apiHeaders(context),
        data: { bidding_schedule_enabled: false, anti_sniping_enabled: false },
    });
    expect(settings.status()).toBe(200);

    const created = await page.request.post("/api/auctions", {
        headers: await apiHeaders(context),
        data: {
            title,
            description: "A desk lamp seeded by the e2e suite.",
            starting_price: 5,
            quantity: 1,
            max_per_bidder: 1,
            ends_at: new Date(Date.now() + 7 * 86400000).toISOString(),
        },
    });
    expect(created.status()).toBe(201);
    const body = (await created.json()) as { auction: { id: number } };
    await context.close();
    return body.auction.id;
}

test.describe.configure({ mode: "serial" });

test.beforeAll(async ({ browser }) => {
    auctionId = await seedAuction(browser);
});

test("a visitor can find an auction in the list and open it", async ({ page }) => {
    await page.goto("/");
    await page.getByRole("link", { name: new RegExp(title) }).click();

    await expect(page).toHaveURL(new RegExp(`/auctions/${auctionId}$`));
    await expect(page.getByRole("heading", { level: 1, name: title })).toBeVisible();
    await expect(page.getByText("A desk lamp seeded by the e2e suite.")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Place a Bid" })).toHaveCount(0);
});

test("a registered user can place a bid and then raise it", async ({ page }) => {
    await registerUser(page, "bidder");
    await page.goto(`/auctions/${auctionId}`);

    await expect(page.getByRole("heading", { name: "Place a Bid" })).toBeVisible();
    const amount = page.locator('input[type="number"]').first();
    await amount.fill("7.50");
    await page.getByRole("button", { name: "Bid", exact: true }).click();

    await expect(page.getByRole("heading", { name: "Update Your Bid" })).toBeVisible();
    await expect(page.getByText(/Your current bid:\s*\S*7\.50/)).toBeVisible();

    await amount.fill("9.00");
    await page.getByRole("button", { name: "Update Bid" }).click();

    await expect(page.getByText(/Your current bid:\s*\S*9\.00/)).toBeVisible();
});

test("a bid below the starting price is rejected with a message", async ({ page }) => {
    await registerUser(page, "bidder");
    await page.goto(`/auctions/${auctionId}`);

    await page.locator('input[type="number"]').first().fill("1.00");
    await page.getByRole("button", { name: "Bid", exact: true }).click();

    await expect(page.getByText(/must be at least 5/)).toBeVisible();
    await expect(page.getByRole("heading", { name: "Place a Bid" })).toBeVisible();
});

test("a lowered bid is refused", async ({ page }) => {
    await registerUser(page, "bidder");
    await page.goto(`/auctions/${auctionId}`);

    const amount = page.locator('input[type="number"]').first();
    await amount.fill("12.00");
    await page.getByRole("button", { name: "Bid", exact: true }).click();
    await expect(page.getByRole("heading", { name: "Update Your Bid" })).toBeVisible();

    await amount.fill("11.00");
    await page.getByRole("button", { name: "Update Bid" }).click();

    await expect(page.getByText("You cannot lower your bid amount.")).toBeVisible();
    await expect(page.getByText(/Your current bid:\s*\S*12\.00/)).toBeVisible();
});
