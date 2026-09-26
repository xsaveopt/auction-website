import { expect, type BrowserContext, type Page } from "@playwright/test";

export async function apiHeaders(context: BrowserContext) {
    const cookie = (await context.cookies()).find((c) => c.name === "XSRF-TOKEN");
    return { "X-XSRF-TOKEN": decodeURIComponent(cookie?.value ?? ""), Accept: "application/json" };
}

async function authenticate(
    page: Page,
    endpoint: "/api/login" | "/api/register",
    username: string,
) {
    await page.request.get("/api/user");
    const response = await page.request.post(endpoint, {
        headers: await apiHeaders(page.context()),
        data: { username, password: "password123" },
    });
    expect(response.ok()).toBeTruthy();
}

export async function loginAsAdmin(page: Page) {
    await authenticate(page, "/api/login", "e2eadmin");
}

export async function registerUser(page: Page, prefix: string) {
    const username = `${prefix}${Date.now()}${Math.floor(Math.random() * 1000)}`;
    await authenticate(page, "/api/register", username);
    return username;
}
