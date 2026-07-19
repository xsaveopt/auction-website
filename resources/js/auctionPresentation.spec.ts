import { describe, it, expect } from "vitest";
import {
    getItemLabel,
    getLeftoverDiscountPercent,
    hasAvailableLeftovers,
} from "./auctionPresentation";
import type { Auction } from "./types";

function auction(overrides: Partial<Auction> = {}): Auction {
    return {
        id: 1,
        title: "Test",
        starting_price: 100,
        quantity: 5,
        status: "active",
        ends_at: "2026-01-01T00:00:00",
        images: [],
        ...overrides,
    };
}

describe("getItemLabel", () => {
    it("pluralizes based on count", () => {
        expect(getItemLabel(1)).toBe("1 item");
        expect(getItemLabel(3)).toBe("3 items");
        expect(getItemLabel(0)).toBe("0 items");
    });

    it("respects a custom noun", () => {
        expect(getItemLabel(2, "bid")).toBe("2 bids");
    });
});

describe("getLeftoverDiscountPercent", () => {
    it("computes the discount from starting vs leftover price", () => {
        expect(
            getLeftoverDiscountPercent(auction({ starting_price: 100, leftover_price: 75 })),
        ).toBe(25);
    });

    it("returns 0 when there is no valid discount", () => {
        expect(
            getLeftoverDiscountPercent(auction({ starting_price: 100, leftover_price: 100 })),
        ).toBe(0);
        expect(getLeftoverDiscountPercent(null)).toBe(0);
    });
});

describe("hasAvailableLeftovers", () => {
    it("is true only when leftovers are enabled and in stock", () => {
        expect(
            hasAvailableLeftovers(auction({ leftover_enabled: true, leftover_quantity: 2 })),
        ).toBe(true);
        expect(
            hasAvailableLeftovers(auction({ leftover_enabled: true, leftover_quantity: 0 })),
        ).toBe(false);
        expect(hasAvailableLeftovers(auction({ leftover_enabled: false }))).toBe(false);
    });
});
