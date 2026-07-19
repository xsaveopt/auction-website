import type { Auction } from "./types";

export function hasAvailableLeftovers(auction: Auction | null | undefined): boolean {
    return Boolean(auction?.leftover_enabled && Number(auction.leftover_quantity) > 0);
}

export function getLeftoverDiscountPercent(auction: Auction | null | undefined): number {
    const startingPrice = Number(auction?.starting_price);
    const leftoverPrice = Number(auction?.leftover_price);

    if (!(startingPrice > 0) || !(leftoverPrice >= 0) || leftoverPrice >= startingPrice) {
        return 0;
    }

    return Math.round(((startingPrice - leftoverPrice) / startingPrice) * 100);
}

export function getItemLabel(count: number | string | null | undefined, noun = "item"): string {
    const normalizedCount = Number(count ?? 0);

    return `${normalizedCount} ${noun}${normalizedCount === 1 ? "" : "s"}`;
}
