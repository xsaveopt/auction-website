import type { RouteLocationNormalizedLoaded } from "vue-router";

export const HEARTBEAT_INTERVAL_MS = 1000;

const CLIENT_STORAGE_KEY = "auction-presence-client-id";
const PAGE_STORAGE_KEY = "auction-presence-page-id";

export interface PresencePayload {
    client_id: string;
    page_id: string;
    page_type: "page" | "home" | "auction";
    path: string;
    auction_id?: number;
}

function createIdentifier(): string {
    if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function getStorageValue(storage: Storage, key: string): string {
    const existing = storage.getItem(key);

    if (existing) {
        return existing;
    }

    const created = createIdentifier();
    storage.setItem(key, created);

    return created;
}

export function presencePayload(route: RouteLocationNormalizedLoaded): PresencePayload {
    const payload: PresencePayload = {
        client_id: getStorageValue(window.localStorage, CLIENT_STORAGE_KEY),
        page_id: getStorageValue(window.sessionStorage, PAGE_STORAGE_KEY),
        page_type: "page",
        path: route.path,
    };

    if (route.path === "/") {
        payload.page_type = "home";
        return payload;
    }

    if (/^\/auctions\/[^/]+$/.test(route.path)) {
        const auctionId = Number.parseInt(String(route.params.id ?? ""), 10);

        if (Number.isInteger(auctionId)) {
            payload.page_type = "auction";
            payload.auction_id = auctionId;
        }
    }

    return payload;
}
