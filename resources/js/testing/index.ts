import { defineComponent, h } from "vue";
import { mount } from "@vue/test-utils";
import type { Auction, User } from "../lib/types";

export const seller: User = { id: 1, username: "seller", is_admin: true };
export const alice: User = { id: 10, username: "alice" };
export const bob: User = { id: 11, username: "bob" };

export function makeAuction(overrides: Partial<Auction> = {}): Auction {
    return {
        id: 7,
        title: "Laptop",
        starting_price: "10.00",
        quantity: 3,
        max_per_bidder: 2,
        ends_at: "2026-01-01T12:00:00Z",
        status: "active",
        is_active: true,
        current_price: "10.00",
        images: [],
        bids: [],
        questions: [],
        seller,
        ...overrides,
    };
}

export function mountComposable<T>(composable: () => T, provide: Record<string, unknown>) {
    const holder: { value?: T } = {};
    const Harness = defineComponent({
        setup() {
            holder.value = composable();
            return () => h("div");
        },
    });
    const wrapper = mount(Harness, { global: { provide } });
    if (!("value" in holder)) throw new Error("Composable did not run");
    return { result: holder.value as T, wrapper };
}

export function labelTargets(wrapper: { element: Element }): Element[] {
    const labels = Array.from(wrapper.element.querySelectorAll("label"));
    const ids = labels.map((label) => label.htmlFor);
    if (ids.some((id) => !id)) throw new Error("Label without a for attribute");
    if (new Set(ids).size !== ids.length) throw new Error(`Duplicate label ids: ${ids.join(", ")}`);

    return ids.map((id) => {
        const inside = Array.from(wrapper.element.querySelectorAll(`[id="${id}"]`));
        if (inside.length !== 1) throw new Error(`Label ${id} matches ${inside.length} controls`);
        return inside[0];
    });
}
