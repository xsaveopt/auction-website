import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("./api", () => ({ api: apiMock }));

interface FakeSubscription {
    endpoint: string;
    toJSON: ReturnType<typeof vi.fn>;
    getKey: ReturnType<typeof vi.fn>;
    unsubscribe: ReturnType<typeof vi.fn>;
}

interface BrowserOptions {
    permission?: NotificationPermission;
    requested?: NotificationPermission;
    existing?: FakeSubscription | null;
    created?: FakeSubscription;
    encodings?: string[];
}

function fakeSubscription(overrides: Partial<FakeSubscription> = {}): FakeSubscription {
    return {
        endpoint: "https://push.example/abc",
        toJSON: vi.fn(() => ({
            endpoint: "https://push.example/abc",
            keys: { p256dh: "p256-key", auth: "auth-key" },
        })),
        getKey: vi.fn(() => null),
        unsubscribe: vi.fn(async () => true),
        ...overrides,
    };
}

function installBrowser(options: BrowserOptions = {}) {
    const notification = {
        permission: options.permission ?? "default",
        requestPermission: vi.fn(async () => {
            notification.permission = options.requested ?? "granted";
            return notification.permission;
        }),
    };
    const pushManager = {
        getSubscription: vi.fn(async () => options.existing ?? null),
        subscribe: vi.fn(async () => options.created ?? fakeSubscription()),
    };
    const registration = { pushManager };
    const register = vi.fn(async () => registration);

    vi.stubGlobal("Notification", notification);
    vi.stubGlobal("PushManager", {
        supportedContentEncodings: options.encodings ?? ["aes128gcm", "aesgcm"],
    });
    Object.defineProperty(navigator, "serviceWorker", {
        value: { register },
        configurable: true,
    });

    return { notification, pushManager, register };
}

async function loadModule() {
    vi.resetModules();
    return import("./pushNotifications");
}

describe("pushNotifications", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        Reflect.deleteProperty(navigator, "serviceWorker");
    });

    it("reports unsupported browsers and refuses to enable", async () => {
        const push = await loadModule();
        const state = push.usePushNotifications();

        expect(state.pushSupported.value).toBe(false);
        expect(state.subscriptionState.value).toBe("unsupported");
        expect(state.browserPermission.value).toBe("denied");
        expect(await push.registerPushServiceWorker()).toBeNull();
        expect(await push.syncPushSubscription()).toBe(false);
        await expect(push.enablePushNotifications()).rejects.toThrow("does not support");
        expect(apiMock).not.toHaveBeenCalled();
    });

    it("registers the service worker once and reflects an existing subscription", async () => {
        const browser = installBrowser({ existing: fakeSubscription() });
        const push = await loadModule();

        expect(await push.refreshPushSubscriptionState()).toBe("subscribed");
        await push.registerPushServiceWorker();

        expect(browser.register).toHaveBeenCalledTimes(1);
        expect(browser.register).toHaveBeenCalledWith("/service-worker.js");
    });

    it("reports idle when no subscription exists", async () => {
        installBrowser();
        const push = await loadModule();

        expect(await push.refreshPushSubscriptionState()).toBe("idle");
        expect(push.usePushNotifications().pushStateKnown.value).toBe(true);
    });

    it("requests permission, subscribes with the server key and stores the subscription", async () => {
        const browser = installBrowser({ permission: "default", requested: "granted" });
        apiMock.mockResolvedValueOnce({ configured: true, public_key: "AQID" });
        apiMock.mockResolvedValueOnce({});
        const push = await loadModule();

        await expect(push.enablePushNotifications()).resolves.toBe(true);

        expect(browser.notification.requestPermission).toHaveBeenCalledTimes(1);
        const subscribeArgs = browser.pushManager.subscribe.mock.calls[0] as unknown as [
            { userVisibleOnly: boolean; applicationServerKey: Uint8Array },
        ];
        expect(subscribeArgs[0].userVisibleOnly).toBe(true);
        expect(Array.from(subscribeArgs[0].applicationServerKey)).toEqual([1, 2, 3]);
        expect(apiMock).toHaveBeenNthCalledWith(1, "/push/config");
        expect(apiMock).toHaveBeenNthCalledWith(2, "/push/subscription", {
            method: "PUT",
            body: JSON.stringify({
                subscription: {
                    endpoint: "https://push.example/abc",
                    keys: { p256dh: "p256-key", auth: "auth-key" },
                    contentEncoding: "aes128gcm",
                },
            }),
        });

        const state = push.usePushNotifications();
        expect(state.subscriptionState.value).toBe("subscribed");
        expect(state.pushEnabled.value).toBe(true);
    });

    it("marks notifications blocked when the user denies permission", async () => {
        installBrowser({ permission: "default", requested: "denied" });
        const push = await loadModule();

        await expect(push.enablePushNotifications()).resolves.toBe(false);

        const state = push.usePushNotifications();
        expect(state.subscriptionState.value).toBe("blocked");
        expect(state.pushEnabled.value).toBe(false);
        expect(apiMock).not.toHaveBeenCalled();
    });

    it("stays idle when permission has not been granted yet", async () => {
        installBrowser({ permission: "default" });
        const push = await loadModule();

        await expect(push.syncPushSubscription()).resolves.toBe(false);
        expect(push.usePushNotifications().subscriptionState.value).toBe("idle");
    });

    it("fails with an error state when the server has no push key", async () => {
        installBrowser({ permission: "granted" });
        apiMock.mockResolvedValueOnce({ configured: false, public_key: null });
        const push = await loadModule();

        await expect(push.syncPushSubscription()).rejects.toThrow("not configured");
        expect(push.usePushNotifications().subscriptionState.value).toBe("error");
    });

    it("reuses an existing subscription and falls back to raw keys and aesgcm", async () => {
        const existing = fakeSubscription({
            toJSON: vi.fn(() => ({})),
            getKey: vi.fn((name: string) =>
                name === "p256dh" ? new Uint8Array([251, 255]).buffer : new Uint8Array([1]).buffer,
            ),
        });
        const browser = installBrowser({ permission: "granted", existing, encodings: [] });
        apiMock.mockResolvedValueOnce({ configured: true, public_key: "AQID" });
        apiMock.mockResolvedValueOnce({});
        const push = await loadModule();

        await expect(push.syncPushSubscription()).resolves.toBe(true);

        expect(browser.pushManager.subscribe).not.toHaveBeenCalled();
        expect(apiMock).toHaveBeenLastCalledWith("/push/subscription", {
            method: "PUT",
            body: JSON.stringify({
                subscription: {
                    endpoint: "https://push.example/abc",
                    keys: { p256dh: "-_8", auth: "AQ" },
                    contentEncoding: "aesgcm",
                },
            }),
        });
    });

    it("rejects a subscription without encryption keys", async () => {
        installBrowser({
            permission: "granted",
            existing: fakeSubscription({ toJSON: vi.fn(() => ({})) }),
        });
        apiMock.mockResolvedValueOnce({ configured: true, public_key: "AQID" });
        const push = await loadModule();

        await expect(push.syncPushSubscription()).rejects.toThrow("incomplete push subscription");
        expect(push.usePushNotifications().subscriptionState.value).toBe("error");
        expect(apiMock).toHaveBeenCalledTimes(1);
    });

    it("removes the subscription from the server and the browser", async () => {
        const existing = fakeSubscription();
        installBrowser({ permission: "granted", existing });
        apiMock.mockResolvedValueOnce({});
        const push = await loadModule();

        await expect(push.clearPushSubscription()).resolves.toBe(true);

        expect(apiMock).toHaveBeenCalledWith("/push/subscription", {
            method: "DELETE",
            body: JSON.stringify({ endpoint: "https://push.example/abc" }),
        });
        expect(existing.unsubscribe).toHaveBeenCalledTimes(1);
        expect(push.usePushNotifications().subscriptionState.value).toBe("idle");
    });

    it("can unsubscribe locally without contacting the server", async () => {
        const existing = fakeSubscription();
        installBrowser({ permission: "granted", existing });
        const push = await loadModule();

        await expect(push.clearPushSubscription({ removeServer: false })).resolves.toBe(true);

        expect(apiMock).not.toHaveBeenCalled();
        expect(existing.unsubscribe).toHaveBeenCalledTimes(1);
    });

    it("returns false when there is nothing to clear", async () => {
        installBrowser({ permission: "granted" });
        const push = await loadModule();

        await expect(push.clearPushSubscription()).resolves.toBe(false);
        expect(apiMock).not.toHaveBeenCalled();
    });
});
