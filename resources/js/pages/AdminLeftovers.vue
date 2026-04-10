<script setup>
import { ref, inject, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const route = useRoute();
const user = inject("user");
const currencySymbol = inject("currencySymbol");
const auctions = ref([]);
const allRounds = ref([]);
const loading = ref(true);
const selectedRoundId = ref(route.query.round_id ? Number(route.query.round_id) : null);

if (!user.value?.is_admin) {
    router.push("/");
}

function syncRoundQuery(roundId) {
    const query = { ...route.query };
    if (roundId !== null) {
        query.round_id = String(roundId);
    } else {
        delete query.round_id;
    }
    router.replace({ path: route.path, query });
}

async function loadLeftovers(roundId = null) {
    const url =
        roundId !== null ? `/auctions/leftovers?round_id=${roundId}` : "/auctions/leftovers";
    const data = await api(url);
    return data;
}

let initialized = false;

onMounted(async () => {
    try {
        const [roundsData, currentData] = await Promise.all([
            api("/rounds"),
            api("/rounds/current"),
        ]);
        allRounds.value = (roundsData.rounds ?? []).sort((a, b) => b.id - a.id);

        let roundId = selectedRoundId.value;
        // Default to active round if no round is specified in the URL
        if (roundId === null) {
            roundId = currentData?.active?.id ?? null;
            selectedRoundId.value = roundId;
        }
        syncRoundQuery(roundId);

        const data = await loadLeftovers(roundId);
        auctions.value = data.auctions;
    } finally {
        loading.value = false;
        initialized = true;
    }
});

watch(selectedRoundId, async (roundId) => {
    if (!initialized) return;
    syncRoundQuery(roundId);
    loading.value = true;
    try {
        const data = await loadLeftovers(roundId);
        auctions.value = data.auctions;
    } finally {
        loading.value = false;
    }
});

function formatDate(d) {
    if (!d) return "";
    return d.slice(0, 10);
}

function formatMoney(amount) {
    return `${currencySymbol.value}${Number(amount ?? 0).toFixed(2)}`;
}

function exportCsv() {
    const rows = [
        ["Auction", "Ended", "Location", "Total qty", "Sold", "Leftover", "Starting price"],
        ...auctions.value.map((a) => [
            a.title,
            formatDate(a.ends_at),
            a.location ?? "",
            a.quantity,
            a.quantity - a.leftover_quantity,
            a.leftover_quantity,
            Number(a.starting_price ?? 0).toFixed(2),
        ]),
    ];
    const csv = rows
        .map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(","))
        .join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "leftovers.csv";
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <div>
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Leftover Items</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Ended auctions with unsold stock — items that can be thrown away or otherwise disposed
            of.
        </p>

        <!-- Round filter + export -->
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <template v-if="allRounds.length > 0">
                <label class="text-sm text-gray-500 dark:text-gray-400 shrink-0">Round:</label>
                <select
                    v-model="selectedRoundId"
                    class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200"
                >
                    <option :value="null">All rounds</option>
                    <option v-for="r in allRounds" :key="r.id" :value="r.id">
                        {{ r.name }}
                    </option>
                </select>
            </template>
            <button
                v-if="auctions.length > 0"
                @click="exportCsv"
                class="ml-auto text-xs px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
                Export CSV
            </button>
        </div>

        <div v-if="loading" class="text-sm text-gray-500 dark:text-gray-400">Loading…</div>

        <div v-else-if="auctions.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
            No leftover items — all ended auctions are fully sold.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr
                        class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700"
                    >
                        <th class="pb-2 pr-4">Auction</th>
                        <th class="pb-2 pr-4">Ended</th>
                        <th class="pb-2 pr-4">Location</th>
                        <th class="pb-2 pr-4 text-right">Total qty</th>
                        <th class="pb-2 pr-4 text-right">Sold</th>
                        <th
                            class="pb-2 pr-4 text-right font-bold text-orange-600 dark:text-orange-400"
                        >
                            Leftover
                        </th>
                        <th class="pb-2 text-right">Starting price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="auction in auctions"
                        :key="auction.id"
                        class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                    >
                        <td class="py-2 pr-4">
                            <router-link
                                :to="`/auctions/${auction.id}`"
                                class="font-medium text-blue-600 dark:text-blue-400 hover:underline"
                            >
                                {{ auction.title }}
                            </router-link>
                            <span
                                v-if="auction.category"
                                class="ml-2 text-xs text-gray-400 dark:text-gray-500"
                            >
                                {{ auction.category.name }}
                            </span>
                        </td>
                        <td class="py-2 pr-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ formatDate(auction.ends_at) }}
                        </td>
                        <td class="py-2 pr-4 text-gray-600 dark:text-gray-300">
                            {{ auction.location || "—" }}
                        </td>
                        <td class="py-2 pr-4 text-right text-gray-700 dark:text-gray-300">
                            {{ auction.quantity }}
                        </td>
                        <td class="py-2 pr-4 text-right text-gray-700 dark:text-gray-300">
                            {{ auction.quantity - auction.leftover_quantity }}
                        </td>
                        <td
                            class="py-2 pr-4 text-right font-semibold text-orange-600 dark:text-orange-400"
                        >
                            {{ auction.leftover_quantity }}
                        </td>
                        <td class="py-2 text-right text-gray-600 dark:text-gray-400">
                            {{ formatMoney(auction.starting_price) }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr
                        class="border-t border-gray-200 dark:border-gray-700 font-semibold text-gray-700 dark:text-gray-300"
                    >
                        <td class="pt-2 pr-4" colspan="3">Total</td>
                        <td class="pt-2 pr-4 text-right">
                            {{ auctions.reduce((s, a) => s + a.quantity, 0) }}
                        </td>
                        <td class="pt-2 pr-4 text-right">
                            {{
                                auctions.reduce((s, a) => s + (a.quantity - a.leftover_quantity), 0)
                            }}
                        </td>
                        <td class="pt-2 pr-4 text-right text-orange-600 dark:text-orange-400">
                            {{ auctions.reduce((s, a) => s + a.leftover_quantity, 0) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
