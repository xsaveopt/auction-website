<script setup>
import { ref, inject, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const user = inject("user");
const auctions = ref([]);
const loading = ref(true);
const expanded = ref({});

if (!user.value?.is_admin) {
    router.push("/");
}

onMounted(async () => {
    try {
        const data = await api("/auctions/ended");
        auctions.value = data.auctions;
    } finally {
        loading.value = false;
    }
});

function toggle(id) {
    expanded.value[id] = !expanded.value[id];
}

function winners(auction) {
    return auction.bids.filter((b) => b.won_quantity > 0);
}

function formatDate(d) {
    return new Date(d).toLocaleString();
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold mb-4">Ended Auctions — Results</h1>
        <p v-if="loading" class="text-gray-500">Loading...</p>
        <p v-else-if="auctions.length === 0" class="text-gray-500">
            No ended auctions yet.
        </p>
        <div v-else class="space-y-3">
            <div
                v-for="auction in auctions"
                :key="auction.id"
                class="bg-white rounded shadow overflow-hidden"
            >
                <button
                    @click="toggle(auction.id)"
                    class="w-full text-left px-5 py-4 flex items-center justify-between hover:bg-gray-50"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <img
                            v-if="auction.images.length"
                            :src="auction.images[0].url"
                            class="w-10 h-10 rounded object-cover shrink-0"
                        />
                        <div class="min-w-0">
                            <h2 class="font-semibold truncate">
                                {{ auction.title }}
                            </h2>
                            <p class="text-xs text-gray-400">
                                Ended {{ formatDate(auction.ends_at) }} ·
                                {{ auction.quantity }} item{{
                                    auction.quantity !== 1 ? "s" : ""
                                }}
                                · {{ auction.bid_count }} bid{{
                                    auction.bid_count !== 1 ? "s" : ""
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 ml-4">
                        <span
                            v-if="winners(auction).length > 0"
                            class="text-sm font-medium text-green-700"
                        >
                            {{
                                winners(auction).reduce(
                                    (s, b) => s + b.won_quantity,
                                    0,
                                )
                            }}
                            sold @ ${{
                                Number(auction.current_price).toFixed(2)
                            }}
                        </span>
                        <span v-else class="text-sm text-gray-400"
                            >No bids</span
                        >
                        <svg
                            class="w-4 h-4 text-gray-400 transition-transform"
                            :class="expanded[auction.id] ? 'rotate-180' : ''"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </div>
                </button>

                <div v-if="expanded[auction.id]" class="border-t px-5 py-4">
                    <div
                        v-if="winners(auction).length === 0"
                        class="text-gray-400 text-sm"
                    >
                        No winners — auction ended without bids.
                    </div>
                    <div v-else>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">
                            Winners — clearing price: ${{
                                Number(auction.current_price).toFixed(2)
                            }}
                            / item
                        </h3>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="pb-1 font-medium">User</th>
                                    <th class="pb-1 font-medium">Bid</th>
                                    <th class="pb-1 font-medium">Won</th>
                                    <th class="pb-1 font-medium text-right">
                                        Total Owed
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="bid in winners(auction)"
                                    :key="bid.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-2 font-medium">
                                        {{ bid.user.username }}
                                    </td>
                                    <td class="py-2">
                                        ${{ Number(bid.amount).toFixed(2) }}
                                    </td>
                                    <td class="py-2">
                                        {{ bid.won_quantity }} item{{
                                            bid.won_quantity !== 1 ? "s" : ""
                                        }}
                                    </td>
                                    <td
                                        class="py-2 text-right font-bold text-green-700"
                                    >
                                        ${{
                                            (
                                                bid.won_quantity *
                                                auction.current_price
                                            ).toFixed(2)
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="text-gray-500">
                                    <td class="pt-2" colspan="2">Total</td>
                                    <td class="pt-2">
                                        {{
                                            winners(auction).reduce(
                                                (s, b) => s + b.won_quantity,
                                                0,
                                            )
                                        }}
                                        items
                                    </td>
                                    <td class="pt-2 text-right font-bold">
                                        ${{
                                            (
                                                winners(auction).reduce(
                                                    (s, b) =>
                                                        s + b.won_quantity,
                                                    0,
                                                ) * auction.current_price
                                            ).toFixed(2)
                                        }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div
                        v-if="
                            auction.bids.filter((b) => b.won_quantity === 0)
                                .length > 0
                        "
                        class="mt-4"
                    >
                        <h3 class="text-xs font-semibold text-gray-400 mb-1">
                            Unsuccessful bids
                        </h3>
                        <div class="text-xs text-gray-400 space-y-0.5">
                            <div
                                v-for="bid in auction.bids.filter(
                                    (b) => b.won_quantity === 0,
                                )"
                                :key="bid.id"
                            >
                                {{ bid.user.username }} — ${{
                                    Number(bid.amount).toFixed(2)
                                }}
                                <span v-if="bid.quantity > 1"
                                    >for {{ bid.quantity }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <router-link
                            :to="`/auctions/${auction.id}`"
                            class="text-xs text-blue-600 hover:underline"
                            >View auction</router-link
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
