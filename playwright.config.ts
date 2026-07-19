import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
    testDir: "./e2e",
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    timeout: 30000,
    expect: { timeout: 10000 },
    use: {
        baseURL: "http://127.0.0.1:8123",
        trace: "on-first-retry",
    },
    projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
    webServer: {
        command: "bash e2e/serve.sh",
        url: "http://127.0.0.1:8123",
        reuseExistingServer: !process.env.CI,
        timeout: 180000,
    },
});
