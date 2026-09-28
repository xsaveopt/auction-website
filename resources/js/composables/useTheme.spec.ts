import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { nextTick } from "vue";

function stubMatchMedia(matches: boolean) {
    const listeners: Array<(e: MediaQueryListEvent) => void> = [];
    vi.stubGlobal(
        "matchMedia",
        vi.fn().mockImplementation((query: string) => ({
            matches,
            media: query,
            addEventListener: (_: string, listener: (e: MediaQueryListEvent) => void) =>
                listeners.push(listener),
            removeEventListener: vi.fn(),
        })),
    );
    return (dark: boolean) =>
        listeners.forEach((listener) => listener({ matches: dark } as MediaQueryListEvent));
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
        expect(localStorage.getItem("theme")).toBeNull();

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
        expect(localStorage.getItem("theme")).toBeNull();
    });

    it("follows system preference changes until the user picks a theme", async () => {
        const setSystemDark = stubMatchMedia(false);
        const { useTheme } = await import("./useTheme");
        const { isDark, toggleTheme } = useTheme();

        setSystemDark(true);
        await nextTick();
        expect(isDark.value).toBe(true);
        expect(document.documentElement.classList.contains("dark")).toBe(true);

        toggleTheme();
        setSystemDark(true);
        await nextTick();
        expect(isDark.value).toBe(false);
        expect(localStorage.getItem("theme")).toBe("light");
    });
});
