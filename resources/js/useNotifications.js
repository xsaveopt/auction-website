import { ref } from "vue";

// Module-level singleton so all components share the same notification list
const notifications = ref([]);
let nextId = 0;

const browserPermission = ref(
    typeof Notification !== "undefined" ? Notification.permission : "denied",
);

export async function requestBrowserPermission() {
    if (typeof Notification === "undefined") return "denied";
    const result = await Notification.requestPermission();
    browserPermission.value = result;
    return result;
}

function sendBrowserNotification(message) {
    if (typeof Notification === "undefined" || Notification.permission !== "granted") return;
    if (!document.hidden) return;
    new Notification("Auction House", { body: message, icon: "/favicon.ico" });
}

export function useNotifications() {
    function notify(message, type = "info", duration = 6000, { browser = false } = {}) {
        const id = ++nextId;
        notifications.value.push({ id, message, type });
        if (duration > 0) {
            setTimeout(() => dismiss(id), duration);
        }
        if (browser) {
            sendBrowserNotification(message);
        }
        return id;
    }

    function dismiss(id) {
        notifications.value = notifications.value.filter((n) => n.id !== id);
    }

    return { notifications, notify, dismiss, browserPermission, requestBrowserPermission };
}
