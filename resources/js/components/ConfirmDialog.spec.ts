import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";
import ConfirmDialog from "./ConfirmDialog.vue";

describe("ConfirmDialog", () => {
    it("renders the message, title and confirm label", () => {
        const wrapper = mount(ConfirmDialog, {
            props: { title: "Delete item", message: "Are you sure?", confirmLabel: "Delete" },
        });

        expect(wrapper.text()).toContain("Delete item");
        expect(wrapper.text()).toContain("Are you sure?");
        expect(wrapper.text()).toContain("Delete");
        expect(wrapper.text()).toContain("Cancel");
    });

    it("emits confirm and cancel on the respective buttons", async () => {
        const wrapper = mount(ConfirmDialog, { props: { message: "Proceed?" } });
        const buttons = wrapper.findAll("button");

        await buttons[0].trigger("click");
        await buttons[1].trigger("click");

        expect(wrapper.emitted("cancel")).toHaveLength(1);
        expect(wrapper.emitted("confirm")).toHaveLength(1);
    });

    it("applies the danger style to the confirm button when danger is set", () => {
        const wrapper = mount(ConfirmDialog, { props: { message: "x", danger: true } });
        const confirmButton = wrapper.findAll("button")[1];

        expect(confirmButton.classes().join(" ")).toContain("bg-red-600");
    });

    it("is announced as a labelled modal dialog", () => {
        const wrapper = mount(ConfirmDialog, {
            props: { title: "Delete item", message: "Are you sure?" },
        });
        const dialog = wrapper.find("[role='alertdialog']");

        expect(dialog.attributes("aria-modal")).toBe("true");
        const titleId = dialog.attributes("aria-labelledby");
        const messageId = dialog.attributes("aria-describedby");
        expect(wrapper.find(`[id='${titleId}']`).text()).toBe("Delete item");
        expect(wrapper.find(`[id='${messageId}']`).text()).toBe("Are you sure?");
    });

    it("focuses cancel on open, traps tab and restores focus on close", async () => {
        const opener = document.createElement("button");
        document.body.appendChild(opener);
        opener.focus();

        const wrapper = mount(ConfirmDialog, {
            props: { message: "Proceed?" },
            attachTo: document.body,
        });
        await nextTick();
        const [cancel, confirm] = wrapper.findAll("button").map((b) => b.element);

        expect(document.activeElement).toBe(cancel);

        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Tab", shiftKey: true }));
        expect(document.activeElement).toBe(confirm);

        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Tab" }));
        expect(document.activeElement).toBe(cancel);

        wrapper.unmount();
        expect(document.activeElement).toBe(opener);
        opener.remove();
    });

    it("emits cancel when Escape is pressed", () => {
        const wrapper = mount(ConfirmDialog, { props: { message: "Proceed?" } });

        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));

        expect(wrapper.emitted("cancel")).toHaveLength(1);
        wrapper.unmount();
    });
});
