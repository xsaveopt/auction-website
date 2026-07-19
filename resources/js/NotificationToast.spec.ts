import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";
import NotificationToast from "./NotificationToast.vue";
import { useNotifications } from "./useNotifications";

describe("NotificationToast", () => {
    beforeEach(() => {
        const { notifications, dismiss } = useNotifications();
        for (const n of notifications.value.slice()) {
            dismiss(n.id);
        }
    });

    it("renders active notifications and dismisses them", async () => {
        const { notify } = useNotifications();
        const wrapper = mount(NotificationToast, {
            global: { stubs: { teleport: true } },
        });

        notify("Saved successfully", "success", 0);
        await nextTick();
        expect(wrapper.text()).toContain("Saved successfully");

        await wrapper.find("button[aria-label='Dismiss']").trigger("click");
        await nextTick();
        expect(wrapper.text()).not.toContain("Saved successfully");
    });
});
