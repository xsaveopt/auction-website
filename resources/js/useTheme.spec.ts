import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { nextTick } from "vue";

function stubMatchMedia(matches: boolean): void {
    vi.stubGlobal(
        "matchMedia",
        vi.fn().mockImplementation((query: string) => ({
            matches,
            media: query,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        })),
    );
}

describe("useTheme", () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.classList.remove("dark");
        vi.resetModules();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it("toggling flips isDark and writes localStorage.theme", async () => {
        stubMatchMedia(false);
        const { useTheme } = await import("./useTheme");
        const { isDark, toggleTheme } = useTheme();

        expect(isDark.value).toBe(false);
        expect(localStorage.getItem("theme")).toBe("light");

        toggleTheme();
        await nextTick();

        expect(isDark.value).toBe(true);
        expect(localStorage.getItem("theme")).toBe("dark");
        expect(document.documentElement.classList.contains("dark")).toBe(true);
    });

    it("respects a stored theme value over the system preference", async () => {
        localStorage.setItem("theme", "dark");
        stubMatchMedia(false);
        const { useTheme } = await import("./useTheme");
        const { isDark } = useTheme();

        expect(isDark.value).toBe(true);
    });

    it("falls back to the system preference when nothing is stored", async () => {
        stubMatchMedia(true);
        const { useTheme } = await import("./useTheme");
        const { isDark } = useTheme();

        expect(isDark.value).toBe(true);
        expect(localStorage.getItem("theme")).toBe("dark");
    });
});
