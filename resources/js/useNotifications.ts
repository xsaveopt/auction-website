import { ref } from "vue";
import type { Notification } from "./types";

const notifications = ref<Notification[]>([]);
let nextId = 0;

export function useNotifications() {
    function notify(message: string, type: Notification["type"] = "info", duration = 6000): number {
        const id = ++nextId;
        notifications.value.push({ id, message, type });
        if (duration > 0) {
            setTimeout(() => dismiss(id), duration);
        }
        return id;
    }

    function dismiss(id: number): void {
        notifications.value = notifications.value.filter((n) => n.id !== id);
    }

    return { notifications, notify, dismiss };
}
