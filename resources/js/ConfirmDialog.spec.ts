import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
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
});
