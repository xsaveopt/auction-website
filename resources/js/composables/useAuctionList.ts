import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "../api";
import { getLeftoverDiscountPercent, hasAvailableLeftovers } from "../auctionPresentation";
import {
    injectHeartbeatData,
    injectCurrencySymbol,
    injectUser,
    injectNow,
    injectCurrentRound,
} from "../injection";
import type { Announcement, Auction, AuctionRound, Category, Id } from "../types";

interface AuctionGroup {
    id: Id | null;
    name: string;
    slug: string;
    auctions: Auction[];
}

export function useAuctionList() {
    const route = useRoute();
    const router = useRouter();

    const auctions = ref<Auction[]>([]);
    const allRounds = ref<AuctionRound[]>([]);
    const categories = ref<Category[]>([]);
    const loading = ref(true);
    const heartbeatData = injectHeartbeatData();
    const currencySymbol = injectCurrencySymbol();
    const user = injectUser();
    const now = injectNow();
    const currentRound = injectCurrentRound();

    const selectedRoundId = ref<number | null>(
        route.query.round_id ? Number(route.query.round_id) : null,
    );

    const announcement = ref<Announcement | null>(null);
    const editingAnnouncement = ref(false);
    const announcementDraft = ref("");
    const announcementSaving = ref(false);

    function syncRoundQuery(roundId: number | null) {
        const query = { ...route.query };
        if (roundId !== null) {
            query.round_id = String(roundId);
        } else {
            delete query.round_id;
        }
        router.replace({ path: route.path, query });
    }

    async function loadAnnouncement() {
        const data = await api<{ announcement: Announcement | null }>("/announcement").catch(
            () => null,
        );
        if (data) announcement.value = data.announcement;
    }

    async function saveAnnouncement() {
        if (!announcementDraft.value.trim()) return;
        announcementSaving.value = true;
        const data = await api<{ announcement: Announcement }>("/announcement", {
            method: "POST",
            body: JSON.stringify({ message: announcementDraft.value.trim() }),
        }).catch(() => null);
        announcementSaving.value = false;
        if (data) {
            announcement.value = data.announcement;
            editingAnnouncement.value = false;
            announcementDraft.value = "";
        }
    }

    async function removeAnnouncement() {
        if (!announcement.value) return;
        await api(`/announcements/${announcement.value.id}`, { method: "DELETE" }).catch(
            () => null,
        );
        announcement.value = null;
    }

    function startEditAnnouncement() {
        announcementDraft.value = announcement.value?.message || "";
        editingAnnouncement.value = true;
    }

    async function loadAuctions(roundId: number | null = null) {
        const url = roundId !== null ? `/auctions?round_id=${roundId}` : "/auctions";
        const data = await api<{ auctions: Auction[] }>(url);
        auctions.value = data.auctions;
    }

    let initialized = false;

    onMounted(async () => {
        try {
            const [currentData, categoryData] = await Promise.all([
                api<{ active: AuctionRound | null; ended?: AuctionRound[] }>("/rounds/current"),
                api<{ categories: Category[] }>("/categories"),
                loadAnnouncement(),
            ]);

            const rounds: AuctionRound[] = [];
            if (currentData?.active) rounds.push(currentData.active);
            allRounds.value = [...rounds, ...(currentData?.ended ?? [])];

            if (categoryData) categories.value = categoryData.categories;

            let roundId = selectedRoundId.value;
            if (roundId === null) {
                roundId = currentData?.active?.id ?? null;
                selectedRoundId.value = roundId;
            }
            syncRoundQuery(roundId);
            await loadAuctions(roundId);
        } finally {
            loading.value = false;
            initialized = true;
        }
    });

    watch(selectedRoundId, async (roundId) => {
        if (!initialized) return;
        syncRoundQuery(roundId);
        loading.value = true;
        await loadAuctions(roundId);
        loading.value = false;
    });

    watch(heartbeatData, (data) => {
        if (loading.value || !data?.auction_updates) return;

        const currentIds = new Set(auctions.value.map((a) => a.id));
        const serverIds = new Set(data.auction_ids ?? []);
        if (
            currentIds.size !== serverIds.size ||
            [...serverIds].some((id) => !currentIds.has(id))
        ) {
            loadAuctions(selectedRoundId.value);
            return;
        }

        const updateMap = new Map(data.auction_updates.map((u) => [u.id, u]));
        for (const auction of auctions.value) {
            const update = updateMap.get(auction.id);
            if (update) {
                Object.assign(auction, update);
            }
        }
    });

    function watchingText(count: number) {
        return `${count} currently watching`;
    }

    function roundClosed(auction: Auction) {
        return auction.round?.status === "ended";
    }

    function isLeftoverSale(auction: Auction) {
        return !auction.is_active && hasAvailableLeftovers(auction) && !roundClosed(auction);
    }

    function priceLabel(auction: Auction) {
        if (isLeftoverSale(auction)) return "Buy now";
        if (auction.is_active) return "Current price";
        if ((auction.bid_count ?? 0) > 0) return "Final price";

        return "Starting price";
    }

    function priceValue(auction: Auction) {
        return isLeftoverSale(auction) ? auction.leftover_price : auction.current_price;
    }

    function leftoverDiscountText(auction: Auction) {
        const discountPercent = getLeftoverDiscountPercent(auction);

        return discountPercent > 0 ? `${discountPercent}% off` : null;
    }

    function statusText(auction: Auction) {
        if (auction.is_active) return "Live";
        if (isLeftoverSale(auction)) return "Leftover sale";
        if (auction.leftover_enabled && auction.leftover_quantity === 0 && !roundClosed(auction))
            return "Sold out";

        return "Ended";
    }

    const showSoldOut = ref(false);
    const selectedLocation = ref<string | null>(null);

    function shouldHideEnded(auction: Auction) {
        if (auction.is_active) return false;
        if (roundClosed(auction)) return false;
        return !(auction.leftover_enabled && (auction.leftover_quantity ?? 0) > 0);
    }

    const hiddenEndedCount = computed(
        () => auctions.value.filter((a) => shouldHideEnded(a)).length,
    );

    const availableLocations = computed(() => {
        const locs = new Set<string>();
        for (const auction of auctions.value) {
            if (auction.location) locs.add(auction.location);
        }
        return [...locs].sort();
    });

    const groupedAuctions = computed<AuctionGroup[]>(() => {
        const groups: AuctionGroup[] = [];
        const categoryMap = new Map<Id, AuctionGroup>();

        for (const cat of categories.value) {
            const group: AuctionGroup = {
                id: cat.id,
                name: cat.name,
                slug: cat.slug,
                auctions: [],
            };
            categoryMap.set(cat.id, group);
            groups.push(group);
        }

        const uncategorized: Auction[] = [];

        for (const auction of auctions.value) {
            if (!showSoldOut.value && shouldHideEnded(auction)) continue;
            if (selectedLocation.value && auction.location !== selectedLocation.value) continue;
            if (auction.category_id && categoryMap.has(auction.category_id)) {
                categoryMap.get(auction.category_id)!.auctions.push(auction);
            } else {
                uncategorized.push(auction);
            }
        }

        const result = groups.filter((g) => g.auctions.length > 0);

        if (uncategorized.length > 0) {
            result.push({
                id: null,
                name: "Other",
                slug: "other",
                auctions: uncategorized,
            });
        }

        return result;
    });

    function timeLeft(endsAt: string) {
        const diff = new Date(endsAt).getTime() - now.value.getTime();
        if (diff <= 0) return "Ended";
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        if (hours > 24) return `${Math.floor(hours / 24)}d ${hours % 24}h left`;
        return `${hours}h ${minutes}m left`;
    }

    return {
        auctions,
        allRounds,
        loading,
        currencySymbol,
        user,
        now,
        currentRound,
        selectedRoundId,
        announcement,
        editingAnnouncement,
        announcementDraft,
        announcementSaving,
        saveAnnouncement,
        removeAnnouncement,
        startEditAnnouncement,
        watchingText,
        roundClosed,
        isLeftoverSale,
        priceLabel,
        priceValue,
        leftoverDiscountText,
        statusText,
        showSoldOut,
        selectedLocation,
        hiddenEndedCount,
        availableLocations,
        groupedAuctions,
        timeLeft,
    };
}
