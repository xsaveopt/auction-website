import { test, expect } from "@playwright/test";

test("the login page renders the login form", async ({ page }) => {
    await page.goto("/login");
    await expect(page.getByRole("heading", { name: "Login" })).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
});

test("a visitor can register and becomes logged in", async ({ page }) => {
    const username = `e2e${Date.now()}`;

    await page.goto("/register");
    await page.locator('input[type="text"]').first().fill(username);
    await page.locator('input[type="password"]').first().fill("password123");
    await page.getByRole("button", { name: "Register" }).click();

    await expect(page).toHaveURL(/127\.0\.0\.1:8123\/$/);
});

test("a registered admin can log in", async ({ page }) => {
    await page.goto("/login");
    await page.locator('input[type="text"]').first().fill("e2eadmin");
    await page.locator('input[type="password"]').first().fill("password123");
    await page.getByRole("button", { name: "Login" }).click();

    await expect(page).toHaveURL(/127\.0\.0\.1:8123\/$/);
});
