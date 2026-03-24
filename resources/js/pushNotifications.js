import { computed, ref } from "vue";
import { api } from "./api.js";

const pushSupported = ref(
    typeof window !== "undefined" &&
        "serviceWorker" in navigator &&
        "PushManager" in window &&
        typeof Notification !== "undefined",
);

const browserPermission = ref(pushSupported.value ? Notification.permission : "denied");
const subscriptionState = ref(pushSupported.value ? "idle" : "unsupported");

let registrationPromise;
let pushConfigPromise;

function supportedContentEncoding() {
    if (
        typeof PushManager !== "undefined" &&
        Array.isArray(PushManager.supportedContentEncodings) &&
        PushManager.supportedContentEncodings.includes("aes128gcm")
    ) {
        return "aes128gcm";
    }

    return "aesgcm";
}

function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replaceAll("-", "+").replaceAll("_", "/");
    const raw = window.atob(base64);

    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

function arrayBufferToBase64Url(buffer) {
    if (!buffer) return null;

    const bytes = new Uint8Array(buffer);
    let binary = "";

    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return window.btoa(binary).replaceAll("+", "-").replaceAll("/", "_").replaceAll("=", "");
}

function serializeSubscription(subscription) {
    const serialized = subscription.toJSON();

    return {
        endpoint: serialized.endpoint ?? subscription.endpoint,
        keys: {
            p256dh:
                serialized.keys?.p256dh ?? arrayBufferToBase64Url(subscription.getKey("p256dh")),
            auth: serialized.keys?.auth ?? arrayBufferToBase64Url(subscription.getKey("auth")),
        },
        contentEncoding: supportedContentEncoding(),
    };
}

async function fetchPushConfig() {
    if (!pushSupported.value) {
        return { configured: false, public_key: null };
    }

    pushConfigPromise ??= api("/push/config");

    return pushConfigPromise;
}

export async function registerPushServiceWorker() {
    if (!pushSupported.value) return null;

    registrationPromise ??= navigator.serviceWorker.register("/service-worker.js");

    return registrationPromise;
}

export async function refreshPushSubscriptionState() {
    if (!pushSupported.value) return "unsupported";

    const registration = await registerPushServiceWorker();
    const subscription = await registration?.pushManager.getSubscription();

    subscriptionState.value = subscription ? "subscribed" : "idle";

    return subscriptionState.value;
}

export async function syncPushSubscription() {
    if (!pushSupported.value) return false;

    browserPermission.value = Notification.permission;

    if (browserPermission.value !== "granted") {
        subscriptionState.value = browserPermission.value === "denied" ? "blocked" : "idle";
        return false;
    }

    const config = await fetchPushConfig();

    if (!config.configured || !config.public_key) {
        subscriptionState.value = "error";
        throw new Error("Push notifications are not configured on this server yet.");
    }

    const registration = await registerPushServiceWorker();
    if (!registration) return false;

    subscriptionState.value = "subscribing";

    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(config.public_key),
        });
    }

    const serialized = serializeSubscription(subscription);

    if (!serialized.keys.p256dh || !serialized.keys.auth) {
        subscriptionState.value = "error";
        throw new Error("The browser returned an incomplete push subscription.");
    }

    await api("/push/subscription", {
        method: "PUT",
        body: JSON.stringify({ subscription: serialized }),
    });

    subscriptionState.value = "subscribed";

    return true;
}

export async function enablePushNotifications() {
    if (!pushSupported.value) {
        throw new Error("This browser does not support push notifications.");
    }

    if (browserPermission.value === "default") {
        browserPermission.value = await Notification.requestPermission();
    } else {
        browserPermission.value = Notification.permission;
    }

    if (browserPermission.value !== "granted") {
        subscriptionState.value = browserPermission.value === "denied" ? "blocked" : "idle";
        return false;
    }

    return syncPushSubscription();
}

export async function clearPushSubscription({ removeServer = true } = {}) {
    if (!pushSupported.value) return false;

    const registration = await registerPushServiceWorker();
    const subscription = await registration?.pushManager.getSubscription();

    if (!subscription) {
        subscriptionState.value = "idle";
        return false;
    }

    if (removeServer) {
        await api("/push/subscription", {
            method: "DELETE",
            body: JSON.stringify({ endpoint: subscription.endpoint }),
        });
    }

    await subscription.unsubscribe();
    subscriptionState.value = "idle";

    return true;
}

export function usePushNotifications() {
    const pushEnabled = computed(
        () => browserPermission.value === "granted" && subscriptionState.value === "subscribed",
    );

    return {
        pushSupported,
        pushEnabled,
        browserPermission,
        subscriptionState,
        registerPushServiceWorker,
        refreshPushSubscriptionState,
        syncPushSubscription,
        enablePushNotifications,
        clearPushSubscription,
    };
}
