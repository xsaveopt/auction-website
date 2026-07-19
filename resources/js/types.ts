export type Id = number;

export type Money = string | number;

export type AuctionStatus = "active" | "ended" | "cancelled";

export interface User {
    id: Id;
    username: string;
    is_admin?: boolean;
    microsoft_id?: string | null;
    payment_reference?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface Category {
    id: Id;
    name: string;
    slug: string;
    sort_order?: number;
}

export interface AuctionImage {
    id: Id;
    path: string;
    url?: string;
    sort_order?: number;
}

export interface AuctionQuestion {
    id: Id;
    auction_id?: Id;
    user_id?: Id;
    question: string;
    answer?: string | null;
    answered_at?: string | null;
    created_at?: string;
    user?: User;
    auction?: Auction;
}

export interface AuctionRound {
    id: Id;
    name: string;
    status: string;
    ends_at?: string | null;
    auction_count?: number;
    created_at?: string;
}

export interface Bid {
    id: Id;
    auction_id?: Id;
    user_id?: Id;
    amount: Money;
    quantity: number;
    won_quantity?: number;
    price?: Money;
    created_at?: string;
    user?: User;
}

export interface LeftoverPriceOffer {
    id: Id;
    auction_id: Id;
    user_id: Id;
    quantity: number;
    offered_price_per_item: Money;
    status: string;
    rebid_requested_at?: string | null;
    created_at?: string;
    user?: User;
    auction?: Auction;
}

export interface LeftoverPurchase {
    id: Id;
    auction_id?: Id;
    user_id?: Id;
    quantity: number;
    price_per_item: Money;
    from_price_offer?: boolean;
    created_at?: string;
    user?: User;
}

export interface AuditLog {
    id: Id;
    action: string;
    comment?: string | null;
    data?: Record<string, unknown> | null;
    created_at?: string;
    admin?: User;
    target_type?: string | null;
    target_id?: Id | null;
}

export interface Announcement {
    id: Id;
    message: string;
    is_active: boolean;
    author_id?: Id;
    author?: User;
    created_at?: string;
}

export interface Auction {
    id: Id;
    seller_id?: Id;
    title: string;
    description?: string | null;
    location?: string | null;
    starting_price: Money;
    quantity: number;
    max_per_bidder?: number | null;
    ends_at: string;
    status: AuctionStatus;
    is_active?: boolean;
    category_id?: Id | null;
    auction_round_id?: Id | null;
    current_price?: Money;
    total_bids?: number;
    bid_count?: number;
    items_allocated?: number;
    watchers?: number;
    watcher_count?: number;
    leftover_enabled?: boolean;
    leftover_quantity?: number;
    leftover_price?: Money;
    images: AuctionImage[];
    bids?: Bid[];
    questions?: AuctionQuestion[];
    leftover_purchases?: LeftoverPurchase[];
    leftover_price_offers?: LeftoverPriceOffer[];
    category?: Category | null;
    round?: AuctionRound | null;
    seller?: User;
    created_at?: string;
    updated_at?: string;
}

export interface Notification {
    id: number;
    message: string;
    type: "info" | "success" | "error" | "warning";
}

export interface Schedule {
    enabled?: boolean;
    weekends_open?: boolean;
    closed_start: string;
    closed_end: string;
    is_open?: boolean;
    [key: string]: unknown;
}

export interface HeartbeatData {
    online?: number;
    watchers?: number;
    auction_updates?: Array<Partial<Auction> & { id: Id }>;
    auction_ids?: Id[];
    auction?: Auction;
    [key: string]: unknown;
}

export interface CurrentRound {
    active: AuctionRound | null;
    ended: AuctionRound[];
}

export interface ConfirmDialogState {
    title?: string;
    message: string;
    confirmLabel?: string;
    danger?: boolean;
    onConfirm: () => void | Promise<void>;
}

export type NotifyFn = (message: string, type?: Notification["type"], duration?: number) => number;

export type OnLoginFn = (user: User) => void;
