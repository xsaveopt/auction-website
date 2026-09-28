import type { Money } from "./types";

export type DatePrecision = "day" | "minute" | "second";

const DATE_LENGTH: Record<DatePrecision, number> = {
    day: 10,
    minute: 16,
    second: 19,
};

export function formatDate(
    value: string | null | undefined,
    precision: DatePrecision = "minute",
    fallback = "",
): string {
    if (!value) return fallback;
    return value.slice(0, DATE_LENGTH[precision]).replace("T", " ");
}

export function formatMoney(value: Money | null | undefined, currencySymbol = ""): string {
    return `${currencySymbol}${Number(value ?? 0).toFixed(2)}`;
}
