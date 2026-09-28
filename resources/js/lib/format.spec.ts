import { describe, it, expect } from "vitest";
import { formatDate, formatMoney } from "./format";

describe("formatDate", () => {
    it("formats to the minute by default", () => {
        expect(formatDate("2026-01-01T10:20:30Z")).toBe("2026-01-01 10:20");
    });

    it("supports day and second precision", () => {
        expect(formatDate("2026-01-01T10:20:30Z", "day")).toBe("2026-01-01");
        expect(formatDate("2026-01-01T10:20:30Z", "second")).toBe("2026-01-01 10:20:30");
    });

    it("returns the fallback for missing values", () => {
        expect(formatDate(undefined)).toBe("");
        expect(formatDate(null)).toBe("");
        expect(formatDate("")).toBe("");
        expect(formatDate(null, "day", "—")).toBe("—");
    });
});

describe("formatMoney", () => {
    it("formats numbers and numeric strings with two decimals", () => {
        expect(formatMoney("3")).toBe("3.00");
        expect(formatMoney(12.345)).toBe("12.35");
        expect(formatMoney(0)).toBe("0.00");
    });

    it("prefixes the currency symbol when given", () => {
        expect(formatMoney("3", "€")).toBe("€3.00");
    });

    it("treats missing values as zero", () => {
        expect(formatMoney(null, "€")).toBe("€0.00");
        expect(formatMoney(undefined)).toBe("0.00");
    });
});
