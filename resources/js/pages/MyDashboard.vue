<script setup>
import { ref, inject, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const user = inject("user");

if (!user.value) {
    router.push("/login");
}
const currencySymbol = inject("currencySymbol");
const now = inject("now");
const active = ref([]);
const won = ref([]);
const lost = ref([]);
const purchased = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const data = await api("/my-auctions");
        active.value = data.active;
        won.value = data.won;
        lost.value = data.lost;
        purchased.value = data.purchased ?? [];
    } finally {
        loading.value = false;
    }
});

function myLeftoverPurchase(auction) {
    return auction.leftover_purchases?.find((p) => p.user.id === user.value?.id);
}

function myAcceptedOffer(auction) {
    return auction.leftover_price_offers?.find(
        (o) => o.user.id === user.value?.id && o.status === "accepted",
    );
}

function myLeftoverSale(auction) {
    return myLeftoverPurchase(auction) ?? myAcceptedOffer(auction);
}

function myLeftoverPrice(sale) {
    return sale?.price_per_item ?? sale?.offered_price_per_item;
}

function timeLeft(endsAt) {
    const diff = new Date(endsAt) - now.value;
    if (diff <= 0) return "Ended";
    const hours = Math.floor(diff / 3600000);
    const minutes = Math.floor((diff % 3600000) / 60000);
    if (hours > 24) return `${Math.floor(hours / 24)}d ${hours % 24}h left`;
    return `${hours}h ${minutes}m left`;
}

function myBid(auction) {
    return auction.bids.find((b) => b.user.id === user.value?.id);
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold mb-6">My Bids & Wins</h1>

        <div v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</div>

        <template v-else>
            <!-- Leftover Purchases -->
            <section v-if="purchased.length > 0" class="mb-10">
                <h2 class="text-xl font-semibold mb-4 text-blue-700 dark:text-blue-400">
                    Purchased (Leftover)
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <router-link
                        v-for="auction in purchased"
                        :key="auction.id"
                        :to="`/auctions/${auction.id}`"
                        class="block bg-white dark:bg-gray-800 rounded shadow border-l-4 border-blue-500 overflow-hidden hover:shadow-md transition-shadow"
                    >
                        <div class="flex">
                            <img
                                v-if="auction.images.length"
                                :src="auction.images[0].url"
                                class="w-24 h-24 object-cover shrink-0"
                            />
                            <div class="p-4 min-w-0">
                                <h3 class="font-bold truncate">{{ auction.title }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Bought {{ myLeftoverSale(auction)?.quantity }} item{{
                                        myLeftoverSale(auction)?.quantity !== 1 ? "s" : ""
                                    }}
                                    @ {{ currencySymbol
                                    }}{{
                                        Number(myLeftoverPrice(myLeftoverSale(auction))).toFixed(2)
                                    }}
                                    <span
                                        v-if="myAcceptedOffer(auction)"
                                        class="ml-1 text-xs text-blue-600 dark:text-blue-400"
                                        >(price offer)</span
                                    >
                                </p>
                                <p class="mt-1 font-bold text-blue-700 dark:text-blue-400">
                                    Total: {{ currencySymbol
                                    }}{{
                                        (
                                            (myLeftoverSale(auction)?.quantity ?? 0) *
                                            Number(myLeftoverPrice(myLeftoverSale(auction)))
                                        ).toFixed(2)
                                    }}
                                </p>
                            </div>
                        </div>
                    </router-link>
                </div>
            </section>

            <!-- Won Auctions -->
            <section v-if="won.length > 0" class="mb-10">
                <h2 class="text-xl font-semibold mb-4 text-green-700 dark:text-green-400">
                    Items Won
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <router-link
                        v-for="auction in won"
                        :key="auction.id"
                        :to="`/auctions/${auction.id}`"
                        class="block bg-white dark:bg-gray-800 rounded shadow border-l-4 border-green-500 overflow-hidden hover:shadow-md transition-shadow"
                    >
                        <div class="flex">
                            <img
                                v-if="auction.images.length"
                                :src="auction.images[0].url"
                                class="w-24 h-24 object-cover shrink-0"
                            />
                            <div class="p-4 min-w-0">
                                <h3 class="font-bold truncate">
                                    {{ auction.title }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Won {{ myBid(auction).won_quantity }} item{{
                                        myBid(auction).won_quantity !== 1 ? "s" : ""
                                    }}
                                    @ {{ currencySymbol
                                    }}{{
                                        Number(
                                            myBid(auction).price ?? myBid(auction).amount,
                                        ).toFixed(2)
                                    }}
                                </p>
                                <p class="mt-1 font-bold text-green-700 dark:text-green-400">
                                    Total: {{ currencySymbol
                                    }}{{
                                        (
                                            myBid(auction).won_quantity *
                                            Number(myBid(auction).price ?? myBid(auction).amount)
                                        ).toFixed(2)
                                    }}
                                </p>
                            </div>
                        </div>
                    </router-link>
                </div>
            </section>

            <!-- Active Bids -->
            <section class="mb-10">
                <h2 class="text-xl font-semibold mb-4">Active Bids</h2>
                <p v-if="active.length === 0" class="text-gray-500 dark:text-gray-400">
                    You aren't bidding on anything active right now.
                </p>
                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <router-link
                        v-for="auction in active"
                        :key="auction.id"
                        :to="`/auctions/${auction.id}`"
                        class="block bg-white dark:bg-gray-800 rounded shadow overflow-hidden hover:shadow-md transition-shadow"
                    >
                        <div class="flex">
                            <img
                                v-if="auction.images.length"
                                :src="auction.images[0].url"
                                class="w-24 h-24 object-cover shrink-0"
                            />
                            <div class="p-4 min-w-0 flex-1">
                                <div class="flex justify-between items-start">
                                    <h3 class="font-bold truncate">
                                        {{ auction.title }}
                                    </h3>
                                    <span
                                        class="text-xs whitespace-nowrap px-2 py-0.5 rounded"
                                        :class="
                                            myBid(auction).won_quantity > 0
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                                        "
                                    >
                                        {{ myBid(auction).won_quantity > 0 ? "Winning" : "Outbid" }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Your bid: {{ currencySymbol
                                    }}{{ Number(myBid(auction).amount).toFixed(2) }}
                                </p>
                                <div class="mt-2 flex justify-between items-end">
                                    <span
                                        class="text-xs font-medium text-blue-600 dark:text-blue-400"
                                        >{{ timeLeft(auction.ends_at) }}</span
                                    >
                                    <span
                                        class="text-sm font-bold text-green-700 dark:text-green-400"
                                    >
                                        {{ currencySymbol
                                        }}{{ Number(auction.current_price).toFixed(2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </router-link>
                </div>
            </section>

            <!-- Lost / Ended -->
            <section v-if="lost.length > 0">
                <h2 class="text-xl font-semibold mb-4 text-gray-500 dark:text-gray-400">
                    Ended Auctions
                </h2>
                <div class="space-y-2">
                    <router-link
                        v-for="auction in lost"
                        :key="auction.id"
                        :to="`/auctions/${auction.id}`"
                        class="flex items-center gap-3 bg-white dark:bg-gray-800 rounded shadow px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <img
                            v-if="auction.images.length"
                            :src="auction.images[0].url"
                            class="w-10 h-10 object-cover rounded"
                        />
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-medium truncate">
                                {{ auction.title }}
                            </h3>
                            <p class="text-xs text-gray-400">
                                Ended
                                {{ auction.ends_at.slice(0, 10) }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Your bid: {{ currencySymbol
                                }}{{ Number(myBid(auction).amount).toFixed(2) }}
                            </p>
                            <p class="text-xs font-bold text-gray-600 dark:text-gray-300">
                                Final: {{ currencySymbol
                                }}{{ Number(auction.current_price).toFixed(2) }}
                            </p>
                        </div>
                    </router-link>
                </div>
            </section>
        </template>
    </div>
</template>
