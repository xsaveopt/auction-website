import { describe, it, expect } from "vitest";
import {
    clampToStock,
    getItemLabel,
    getLeftoverDiscountPercent,
    getPriceOfferLimit,
    hasAvailableLeftovers,
    watchingText,
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

describe("getPriceOfferLimit", () => {
    it("is one cent below the leftover price", () => {
        expect(getPriceOfferLimit(auction({ leftover_price: "15.00" }))).toBe("14.99");
    });

    it("never goes below one cent", () => {
        expect(getPriceOfferLimit(auction({ leftover_price: "0.01" }))).toBe("0.01");
        expect(getPriceOfferLimit(null)).toBe("0.01");
    });
});

describe("clampToStock", () => {
    it("keeps the value between one and the remaining stock", () => {
        expect(clampToStock(5, 2)).toBe(2);
        expect(clampToStock(0, 3)).toBe(1);
        expect(clampToStock("2", 3)).toBe(2);
        expect(clampToStock(4, 0)).toBe(1);
        expect(clampToStock(null, undefined)).toBe(1);
    });
});

describe("watchingText", () => {
    it("describes the watcher count", () => {
        expect(watchingText(2)).toBe("2 currently watching");
    });
});
