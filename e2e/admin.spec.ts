import { test, expect } from "@playwright/test";
import { loginAsAdmin, registerUser } from "./session";

test("an admin can add a category and list an item in it", async ({ page }) => {
    const suffix = `${Date.now()}`;
    const category = `E2E Tools ${suffix}`;
    const title = `E2E Drill ${suffix}`;

    await loginAsAdmin(page);

    await page.goto("/admin/categories");
    await expect(page.getByRole("heading", { name: "Manage Categories" })).toBeVisible();
    await page.getByPlaceholder("New category name").fill(category);
    await page.getByRole("button", { name: "Add", exact: true }).click();
    await expect(page.getByText(category)).toBeVisible();

    await page.goto("/admin/sell");
    await expect(page.getByRole("heading", { name: "Sell an Item" })).toBeVisible();
    const form = page
        .locator("form")
        .filter({ has: page.getByRole("button", { name: "Create Auction" }) });
    await form.locator('input[type="text"]').first().fill(title);
    await form.locator("textarea").fill("Cordless drill listed by the e2e admin flow.");
    await form.getByPlaceholder("e.g. Warehouse A, 123 Main St").fill("Warehouse E2E");
    await form.locator("select").selectOption({ label: category });
    await form.locator('input[type="number"]').first().fill("25.00");
    await page.getByRole("button", { name: "Create Auction" }).click();

    await expect(page).toHaveURL(/\/auctions\/\d+$/);
    await expect(page.getByRole("heading", { level: 1, name: title })).toBeVisible();
    await expect(page.getByText("Warehouse E2E")).toBeVisible();

    await page.goto("/admin/auctions");
    await expect(page.getByRole("heading", { name: "Auction Listing" })).toBeVisible();
    await expect(page.getByRole("link", { name: title })).toBeVisible();
});

test("a regular user is sent away from the admin panel", async ({ page }) => {
    await registerUser(page, "plain");

    await page.goto("/admin/categories");

    await expect(page).toHaveURL(/127\.0\.0\.1:8123\/$/);
    await expect(page.getByRole("button", { name: "Logout" })).toBeVisible();
    await expect(page.getByRole("heading", { name: "Manage Categories" })).toHaveCount(0);
});
