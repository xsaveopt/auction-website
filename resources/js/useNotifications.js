import { ref } from "vue";

// Module-level singleton so all components share the same notification list
const notifications = ref([]);
let nextId = 0;

export function useNotifications() {
    function notify(message, type = "info", duration = 6000) {
        const id = ++nextId;
        notifications.value.push({ id, message, type });
        if (duration > 0) {
            setTimeout(() => dismiss(id), duration);
        }
        return id;
    }

    function dismiss(id) {
        notifications.value = notifications.value.filter((n) => n.id !== id);
    }

    return { notifications, notify, dismiss };
}
