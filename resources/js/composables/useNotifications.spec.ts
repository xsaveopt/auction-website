import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { useNotifications } from "./useNotifications";

describe("useNotifications", () => {
    beforeEach(() => {
        const { notifications, dismiss } = useNotifications();
        for (const n of notifications.value.slice()) {
            dismiss(n.id);
        }
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("notify pushes a notification and returns its id", () => {
        const { notifications, notify } = useNotifications();

        const id = notify("Saved", "success");

        expect(typeof id).toBe("number");
        expect(notifications.value).toHaveLength(1);
        expect(notifications.value[0]).toMatchObject({ id, message: "Saved", type: "success" });
    });

    it("dismiss removes the notification by id", () => {
        const { notifications, notify, dismiss } = useNotifications();

        const id = notify("Saved");
        expect(notifications.value).toHaveLength(1);

        dismiss(id);

        expect(notifications.value).toHaveLength(0);
    });

    it("auto-dismisses after the given duration", () => {
        vi.useFakeTimers();
        const { notifications, notify } = useNotifications();

        notify("Saved", "info", 5000);
        expect(notifications.value).toHaveLength(1);

        vi.advanceTimersByTime(4999);
        expect(notifications.value).toHaveLength(1);

        vi.advanceTimersByTime(1);
        expect(notifications.value).toHaveLength(0);
    });

    it("does not schedule an auto-dismiss when duration is 0", () => {
        vi.useFakeTimers();
        const { notifications, notify } = useNotifications();

        notify("Persistent", "info", 0);

        vi.advanceTimersByTime(60000);
        expect(notifications.value).toHaveLength(1);
    });
});
