<script setup>
import { ref, computed, inject, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const user = inject("user");
const currencySymbol = inject("currencySymbol");
const offers = ref([]);
const loading = ref(true);
const processingId = ref(null);
const error = ref("");

if (!user.value?.is_admin) {
    router.push("/");
}

onMounted(async () => {
    await load();
});

async function load() {
    loading.value = true;
    try {
        const data = await api("/admin/leftover-price-offers");
        offers.value = data.offers;
    } finally {
        loading.value = false;
    }
}

function formatDate(d) {
    if (!d) return "";
    return d.slice(0, 16).replace("T", " ");
}

async function accept(offer) {
    processingId.value = offer.id;
    error.value = "";
    try {
        await api(`/admin/leftover-price-offers/${offer.id}/accept`, { method: "POST" });
        offers.value = offers.value.filter((o) => o.id !== offer.id);
    } catch (e) {
        error.value = e.data?.message || "Failed to accept offer.";
    } finally {
        processingId.value = null;
    }
}

const grouped = computed(() => {
    const map = new Map();
    for (const offer of offers.value) {
        const id = offer.auction.id;
        if (!map.has(id)) {
            map.set(id, { auction: offer.auction, offers: [] });
        }
        map.get(id).offers.push(offer);
    }
    return [...map.values()].map((group) => ({
        ...group,
        offers: [...group.offers].sort(
            (a, b) => Number(b.offered_price_per_item) - Number(a.offered_price_per_item),
        ),
    }));
});

async function reject(offer) {
    processingId.value = offer.id;
    error.value = "";
    try {
        await api(`/admin/leftover-price-offers/${offer.id}/reject`, { method: "POST" });
        offers.value = offers.value.filter((o) => o.id !== offer.id);
    } catch (e) {
        error.value = e.data?.message || "Failed to reject offer.";
    } finally {
        processingId.value = null;
    }
}

async function deleteOffer(offer) {
    processingId.value = offer.id;
    error.value = "";
    try {
        await api(`/admin/leftover-price-offers/${offer.id}`, { method: "DELETE" });
        offers.value = offers.value.filter((o) => o.id !== offer.id);
    } catch (e) {
        error.value = e.data?.message || "Failed to delete offer.";
    } finally {
        processingId.value = null;
    }
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold mb-4">Price Offers</h1>

        <p v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</p>

        <p v-else-if="offers.length === 0" class="text-gray-500 dark:text-gray-400">
            No pending price offers.
        </p>

        <p v-if="error" class="text-red-600 dark:text-red-400 mb-3 text-sm">{{ error }}</p>

        <div v-if="!loading && offers.length > 0" class="space-y-6">
            <div v-for="group in grouped" :key="group.auction.id">
                <div class="flex items-center gap-3 mb-2">
                    <img
                        v-if="group.auction.images?.length"
                        :src="group.auction.images[0].url"
                        class="w-10 h-10 rounded object-cover shrink-0"
                    />
                    <router-link
                        :to="`/auctions/${group.auction.id}`"
                        class="font-semibold text-lg hover:underline text-blue-600 dark:text-blue-400 truncate"
                    >
                        {{ group.auction.title }}
                    </router-link>
                    <span class="text-sm text-gray-400 dark:text-gray-500 shrink-0">
                        list {{ currencySymbol }}{{ group.auction.leftover_price }}
                    </span>
                </div>
                <div class="space-y-2 pl-1">
                    <div
                        v-for="offer in group.offers"
                        :key="offer.id"
                        class="bg-white dark:bg-gray-800 rounded shadow px-5 py-3 flex items-center gap-4"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                <span class="font-medium">{{ offer.user.username }}</span>
                                offers
                                <span class="font-bold">
                                    {{ offer.quantity }} × {{ currencySymbol
                                    }}{{ Number(offer.offered_price_per_item).toFixed(2) }}
                                </span>
                                <span
                                    v-if="offer.quantity > 1"
                                    class="text-gray-400 dark:text-gray-500"
                                >
                                    = {{ currencySymbol
                                    }}{{
                                        (
                                            offer.quantity * Number(offer.offered_price_per_item)
                                        ).toFixed(2)
                                    }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ formatDate(offer.created_at) }}
                            </p>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button
                                @click="accept(offer)"
                                :disabled="processingId === offer.id"
                                class="text-sm bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white px-3 py-1.5 rounded"
                            >
                                Accept
                            </button>
                            <button
                                @click="reject(offer)"
                                :disabled="processingId === offer.id"
                                class="text-sm bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white px-3 py-1.5 rounded"
                            >
                                Reject
                            </button>
                            <button
                                @click="deleteOffer(offer)"
                                :disabled="processingId === offer.id"
                                class="text-sm bg-gray-400 hover:bg-gray-500 disabled:opacity-50 text-white px-3 py-1.5 rounded"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
