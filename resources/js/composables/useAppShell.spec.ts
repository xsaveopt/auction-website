import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { defineComponent, h, inject, reactive, type Ref } from "vue";
import { mount, flushPromises, enableAutoUnmount } from "@vue/test-utils";
import type { OnLoginFn, User } from "../types";

const state = vi.hoisted(() => ({
    apiMock: vi.fn(),
    notify: vi.fn(),
    route: {} as { path: string; fullPath: string; params: Record<string, string>; query: object },
    router: { push: vi.fn(), replace: vi.fn() },
    push: {
        pushSupported: { value: false },
        pushEnabled: { value: false },
        pushStateKnown: { value: true },
        browserPermission: { value: "default" as string },
        registerPushServiceWorker: vi.fn(),
        refreshPushSubscriptionState: vi.fn(),
        syncPushSubscription: vi.fn(),
        enablePushNotifications: vi.fn(),
        clearPushSubscription: vi.fn(),
    },
}));

vi.mock("../api", () => ({ api: state.apiMock, ApiError: class extends Error {} }));
vi.mock("vue-router", () => ({
    useRoute: () => state.route,
    useRouter: () => state.router,
}));
vi.mock("../useNotifications", () => ({ useNotifications: () => ({ notify: state.notify }) }));
vi.mock("../useTheme", () => ({
    useTheme: () => ({ isDark: { value: false }, toggleTheme: vi.fn() }),
}));
vi.mock("../pushNotifications", () => ({ usePushNotifications: () => state.push }));

import { useAppShell } from "./useAppShell";

type Shell = ReturnType<typeof useAppShell>;

interface Injected {
    currencySymbol: Ref<string>;
    onLogin: OnLoginFn;
}

function mountShell(responses: Record<string, unknown> = {}) {
    const all: Record<string, unknown> = {
        "/user": { user: null },
        "/schedule": { schedule: null },
        "/auth/sso/enabled": { enabled: false },
        "/rounds/current": { active: null, ended: [] },
        "/presence/heartbeat": { online: 1 },
        "/my-auctions": { active: [], won: [], lost: [] },
        "/logout": {},
        ...responses,
    };
    state.apiMock.mockImplementation(async (url: string) => {
        if (!(url in all)) throw new Error(`Unexpected ${url}`);
        const value = all[url];
        if (typeof value === "function") return value();
        return value;
    });

    let result!: Shell;
    const injected = {} as Injected;
    const Child = defineComponent({
        setup() {
            injected.currencySymbol = inject("currencySymbol") as Ref<string>;
            injected.onLogin = inject("onLogin") as OnLoginFn;
            return () => h("span");
        },
    });
    const Harness = defineComponent({
        setup() {
            result = useAppShell();
            return () => h(Child);
        },
    });
    const wrapper = mount(Harness);
    return {
        wrapper,
        injected,
        get result() {
            return result;
        },
    };
}

function schedule(overrides: Record<string, unknown> = {}) {
    return {
        schedule: {
            enabled: true,
            weekends_open: false,
            closed_start: "09:00",
            closed_end: "17:00",
            ...overrides,
        },
    };
}

enableAutoUnmount(afterEach);

describe("useAppShell", () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ["setInterval", "clearInterval", "Date"] });
        vi.setSystemTime(new Date(2026, 0, 7, 12, 0, 0));
        state.apiMock.mockReset();
        state.notify.mockReset();
        state.router.push.mockReset();
        state.route = reactive({ path: "/", fullPath: "/", params: {}, query: {} });
        state.push.pushSupported.value = false;
        state.push.browserPermission.value = "default";
        for (const fn of [
            state.push.registerPushServiceWorker,
            state.push.refreshPushSubscriptionState,
            state.push.syncPushSubscription,
            state.push.enablePushNotifications,
            state.push.clearPushSubscription,
        ]) {
            fn.mockReset();
            fn.mockResolvedValue(true);
        }
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("loads the user, schedule, sso flag and round on mount", async () => {
        const { result, injected } = mountShell({
            "/user": { user: { id: 1, username: "alice" } },
            "/schedule": schedule({
                enabled: false,
                currency_symbol: "€",
                site_locked: true,
                lock_message: "Maintenance",
                server_time_local: "13:05:09",
            }),
            "/auth/sso/enabled": { enabled: true },
        });
        await flushPromises();

        expect(result.user.value?.username).toBe("alice");
        expect(result.loading.value).toBe(false);
        expect(result.ssoEnabled.value).toBe(true);
        expect(result.siteLocked.value).toBe(true);
        expect(result.lockMessage.value).toBe("Maintenance");
        expect(injected.currencySymbol.value).toBe("€");
        expect(result.serverClock.value).toBe("13:05:09");
        expect(result.scheduleBar.value).toBeNull();
        expect(state.apiMock).toHaveBeenCalledWith("/rounds/current");
        expect(state.apiMock).toHaveBeenCalledWith(
            "/presence/heartbeat",
            expect.objectContaining({ method: "POST" }),
        );

        vi.advanceTimersByTime(1000);
        expect(result.serverClock.value).toBe("13:05:10");
    });

    it("treats a failing user request as logged out", async () => {
        const { result } = mountShell({
            "/user": () => Promise.reject(new Error("401")),
            "/auth/sso/enabled": () => Promise.reject(new Error("500")),
        });
        await flushPromises();

        expect(result.user.value).toBeNull();
        expect(result.loading.value).toBe(false);
        expect(result.ssoEnabled.value).toBe(false);
    });

    it("shows time until bidding opens during closed hours", async () => {
        const { result } = mountShell({ "/schedule": schedule() });
        await flushPromises();

        expect(result.scheduleBar.value).toEqual({
            open: false,
            percent: 37.5,
            label: "Bidding opens in 5h 0m",
        });
    });

    it("shows time until bidding closes outside closed hours", async () => {
        vi.setSystemTime(new Date(2026, 0, 7, 18, 0, 0));
        const { result } = mountShell({ "/schedule": schedule() });
        await flushPromises();

        expect(result.scheduleBar.value).toMatchObject({
            open: true,
            label: "Bidding closes in 15h 0m",
        });
    });

    it("shows the weekend window when weekends are open", async () => {
        vi.setSystemTime(new Date(2026, 0, 10, 12, 0, 0));
        const { result } = mountShell({ "/schedule": schedule({ weekends_open: true }) });
        await flushPromises();

        expect(result.scheduleBar.value).toMatchObject({
            open: true,
            label: "Bidding open for the weekend · closes in 1d 21h",
        });
    });

    it("refreshes the schedule every minute", async () => {
        mountShell();
        await flushPromises();
        const before = state.apiMock.mock.calls.filter(([url]) => url === "/schedule").length;

        vi.advanceTimersByTime(60000);
        await flushPromises();

        const after = state.apiMock.mock.calls.filter(([url]) => url === "/schedule").length;
        expect(after).toBe(before + 1);
    });

    it("notifies bidders about won, lost and overbid auctions while polling", async () => {
        let poll = 0;
        const me: User = { id: 5, username: "me" };
        const snapshots = [
            {
                active: [
                    { id: 1, title: "Won", bids: [] },
                    { id: 2, title: "Lost", bids: [] },
                    {
                        id: 3,
                        title: "Overbid",
                        bids: [{ id: 9, amount: 1, quantity: 1, won_quantity: 1, user: me }],
                    },
                ],
            },
            {
                active: [
                    {
                        id: 3,
                        title: "Overbid",
                        bids: [{ id: 9, amount: 1, quantity: 1, won_quantity: 0, user: me }],
                    },
                ],
                won: [{ id: 1, title: "Won" }],
                lost: [{ id: 2, title: "Lost" }],
            },
        ];
        mountShell({
            "/user": { user: me },
            "/my-auctions": () => snapshots[Math.min(poll++, 1)],
        });
        await flushPromises();
        expect(state.notify).not.toHaveBeenCalled();

        vi.advanceTimersByTime(30000);
        await flushPromises();

        expect(state.notify).toHaveBeenCalledWith('You won "Won"!', "success", 10000);
        expect(state.notify).toHaveBeenCalledWith(
            'Auction "Lost" has ended — you didn\'t win.',
            "info",
            8000,
        );
        expect(state.notify).toHaveBeenCalledWith(
            'You\'ve been overbid on "Overbid"!',
            "warning",
            8000,
        );
    });

    it("does not poll bidder auctions for admins and clears their push subscription", async () => {
        state.push.browserPermission.value = "granted";
        mountShell({ "/user": { user: { id: 1, username: "admin", is_admin: true } } });
        await flushPromises();

        expect(state.apiMock).not.toHaveBeenCalledWith("/my-auctions");
        expect(state.push.clearPushSubscription).toHaveBeenCalled();
        expect(state.push.syncPushSubscription).not.toHaveBeenCalled();
    });

    it("syncs the push subscription for bidders with granted permission", async () => {
        state.push.browserPermission.value = "granted";
        state.push.syncPushSubscription.mockRejectedValueOnce(new Error("sync failed"));
        mountShell({ "/user": { user: { id: 2, username: "bob" } } });
        await flushPromises();

        expect(state.push.syncPushSubscription).toHaveBeenCalled();
        expect(state.notify).toHaveBeenCalledWith("sync failed", "error", 8000);
    });

    it("registers the service worker when push is supported", async () => {
        state.push.pushSupported.value = true;
        mountShell();
        await flushPromises();

        expect(state.push.registerPushServiceWorker).toHaveBeenCalled();
        expect(state.push.refreshPushSubscriptionState).toHaveBeenCalled();
    });

    it("reports the notification bell outcome", async () => {
        const { result } = mountShell();
        await flushPromises();

        await result.handleNotificationBell();
        expect(state.notify).toHaveBeenLastCalledWith("Browser notifications enabled.", "success");

        state.push.enablePushNotifications.mockResolvedValueOnce(false);
        state.push.browserPermission.value = "denied";
        await result.handleNotificationBell();
        expect(state.notify).toHaveBeenLastCalledWith(
            "Notifications are blocked in your browser settings.",
            "warning",
            8000,
        );

        state.push.enablePushNotifications.mockRejectedValueOnce("nope");
        await result.handleNotificationBell();
        expect(state.notify).toHaveBeenLastCalledWith(
            "Couldn't enable browser notifications.",
            "error",
            8000,
        );
    });

    it("logs in through the provided onLogin and logs out again", async () => {
        const { result, injected } = mountShell();
        await flushPromises();

        injected.onLogin({ id: 3, username: "carol" });
        expect(result.user.value?.username).toBe("carol");
        expect(state.router.push).toHaveBeenCalledWith("/");

        state.router.push.mockClear();
        state.push.browserPermission.value = "granted";
        await result.logout();

        expect(state.push.clearPushSubscription).toHaveBeenCalled();
        expect(state.apiMock).toHaveBeenCalledWith("/logout", { method: "POST" });
        expect(result.user.value).toBeNull();
        expect(state.router.push).toHaveBeenCalledWith("/");
    });

    it("sends a heartbeat on navigation and pauses it while hidden", async () => {
        const { wrapper } = mountShell();
        await flushPromises();
        const heartbeats = () =>
            state.apiMock.mock.calls.filter(([url]) => url === "/presence/heartbeat").length;

        const start = heartbeats();
        state.route.fullPath = "/auctions/1";
        await flushPromises();
        expect(heartbeats()).toBe(start + 1);

        Object.defineProperty(document, "hidden", { configurable: true, value: true });
        document.dispatchEvent(new Event("visibilitychange"));
        const paused = heartbeats();
        vi.advanceTimersByTime(5000);
        expect(heartbeats()).toBe(paused);

        Object.defineProperty(document, "hidden", { configurable: true, value: false });
        document.dispatchEvent(new Event("visibilitychange"));
        expect(heartbeats()).toBe(paused + 1);

        wrapper.unmount();
        const stopped = heartbeats();
        vi.advanceTimersByTime(5000);
        expect(heartbeats()).toBe(stopped);
    });
});
