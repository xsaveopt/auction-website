<script setup lang="ts">
import { ref, onMounted, watch, useId } from "vue";
import ConfirmDialog from "../components/ConfirmDialog.vue";
import { useAdminUsers } from "../composables/useAdminUsers";
import { overrideSaleQuoteUrl, useOverrideSales } from "../composables/useOverrideSales";
import { useRoute, useRouter } from "vue-router";
import { api } from "../lib/api";
import { formatDate, formatMoney } from "../lib/format";
import { injectUser, injectCurrencySymbol } from "../lib/injection";
import type { Auction, AuctionRound, ConfirmDialogState } from "../lib/types";

const router = useRouter();
const route = useRoute();
const user = injectUser();
const currencySymbol = injectCurrencySymbol();
const auctions = ref<Auction[]>([]);
const allRounds = ref<AuctionRound[]>([]);
const loading = ref(true);
const selectedRoundId = ref<number | null>(
    route.query.round_id ? Number(route.query.round_id) : null,
);
const confirmDialog = ref<ConfirmDialogState | null>(null);
const { users, loadUsers } = useAdminUsers();
const overrideSale = useOverrideSales({
    confirm: (dialog) => {
        confirmDialog.value = dialog;
    },
    onChange: async () => {
        const data = await loadLeftovers(selectedRoundId.value);
        auctions.value = data.auctions;
    },
});
const {
    sales,
    selectedLines,
    username: saleUsername,
    error: saleError,
    saving: saleSaving,
    total: saleTotal,
    isSelected,
    toggle: toggleLine,
    clear: clearSale,
    submit: submitSale,
    remove: removeSale,
} = overrideSale;

if (!user.value?.is_admin) {
    router.push("/");
}

function syncRoundQuery(roundId: number | null) {
    const query = { ...route.query };
    if (roundId !== null) {
        query.round_id = String(roundId);
    } else {
        delete query.round_id;
    }
    router.replace({ path: route.path, query });
}

async function loadLeftovers(roundId: number | null = null) {
    const url =
        roundId !== null ? `/auctions/leftovers?round_id=${roundId}` : "/auctions/leftovers";
    const data = await api<{ auctions: Auction[] }>(url);
    return data;
}

let initialized = false;

onMounted(async () => {
    try {
        const [roundsData, currentData] = await Promise.all([
            api<{ rounds: AuctionRound[] }>("/rounds"),
            api<{ active: AuctionRound | null }>("/rounds/current"),
        ]);
        allRounds.value = (roundsData.rounds ?? []).sort(
            (a: AuctionRound, b: AuctionRound) => b.id - a.id,
        );

        let roundId = selectedRoundId.value;
        if (roundId === null) {
            roundId = currentData?.active?.id ?? null;
            selectedRoundId.value = roundId;
        }
        syncRoundQuery(roundId);

        const [data] = await Promise.all([
            loadLeftovers(roundId),
            overrideSale.loadSales(),
            loadUsers(),
        ]);
        auctions.value = data.auctions;
    } finally {
        loading.value = false;
        initialized = true;
    }
});

watch(selectedRoundId, async (roundId) => {
    if (!initialized) return;
    clearSale();
    syncRoundQuery(roundId);
    loading.value = true;
    try {
        const data = await loadLeftovers(roundId);
        auctions.value = data.auctions;
    } finally {
        loading.value = false;
    }
});

function exportCsv() {
    const rows: (string | number)[][] = [
        ["Auction", "Ended", "Location", "Total qty", "Sold", "Leftover", "Starting price"],
        ...auctions.value.map((a: Auction) => [
            a.title,
            formatDate(a.ends_at, "day"),
            a.location ?? "",
            a.quantity,
            a.quantity - (a.leftover_quantity ?? 0),
            a.leftover_quantity ?? 0,
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

const uid = useId();
</script>

<template>
    <div>
        <ConfirmDialog
            v-if="confirmDialog"
            :title="confirmDialog.title"
            :message="confirmDialog.message"
            :confirm-label="confirmDialog.confirmLabel"
            :danger="confirmDialog.danger"
            @confirm="
                confirmDialog.onConfirm();
                confirmDialog = null;
            "
            @cancel="confirmDialog = null"
        />

        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Leftover Items</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Ended auctions with unsold stock — items that can be thrown away or otherwise disposed
            of.
        </p>

        <!-- Round filter + export -->
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <template v-if="allRounds.length > 0">
                <label :for="`${uid}-1`" class="text-sm text-gray-500 dark:text-gray-400 shrink-0"
                    >Round:</label
                >
                <select
                    :id="`${uid}-1`"
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
                        <th class="pb-2 pr-2 w-6"><span class="sr-only">Select</span></th>
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
                        <td class="py-2 pr-2">
                            <input
                                type="checkbox"
                                :checked="isSelected(auction)"
                                :aria-label="`Select ${auction.title} for an override sale`"
                                @change="toggleLine(auction)"
                            />
                        </td>
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
                            {{ formatDate(auction.ends_at, "day") }}
                        </td>
                        <td class="py-2 pr-4 text-gray-600 dark:text-gray-300">
                            {{ auction.location || "—" }}
                        </td>
                        <td class="py-2 pr-4 text-right text-gray-700 dark:text-gray-300">
                            {{ auction.quantity }}
                        </td>
                        <td class="py-2 pr-4 text-right text-gray-700 dark:text-gray-300">
                            {{ auction.quantity - (auction.leftover_quantity ?? 0) }}
                        </td>
                        <td
                            class="py-2 pr-4 text-right font-semibold text-orange-600 dark:text-orange-400"
                        >
                            {{ auction.leftover_quantity }}
                        </td>
                        <td class="py-2 text-right text-gray-600 dark:text-gray-400">
                            {{ formatMoney(auction.starting_price, currencySymbol) }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr
                        class="border-t border-gray-200 dark:border-gray-700 font-semibold text-gray-700 dark:text-gray-300"
                    >
                        <td class="pt-2 pr-4" colspan="4">Total</td>
                        <td class="pt-2 pr-4 text-right">
                            {{ auctions.reduce((s, a) => s + a.quantity, 0) }}
                        </td>
                        <td class="pt-2 pr-4 text-right">
                            {{
                                auctions.reduce(
                                    (s, a) => s + (a.quantity - (a.leftover_quantity ?? 0)),
                                    0,
                                )
                            }}
                        </td>
                        <td class="pt-2 pr-4 text-right text-orange-600 dark:text-orange-400">
                            {{ auctions.reduce((s, a) => s + (a.leftover_quantity ?? 0), 0) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <form
            v-if="selectedLines.length > 0"
            @submit.prevent="submitSale"
            class="mt-6 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-900/10 p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Override sale</h3>
                <button
                    type="button"
                    @click="clearSale"
                    class="text-xs text-gray-500 dark:text-gray-400 hover:underline"
                >
                    Clear selection
                </button>
            </div>
            <div
                v-if="saleError"
                class="mb-3 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-2 rounded text-sm"
            >
                {{ saleError }}
            </div>
            <table class="w-full text-sm mb-3">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400">
                        <th class="pb-1 pr-4 font-medium">Auction</th>
                        <th class="pb-1 pr-4 font-medium">Qty</th>
                        <th class="pb-1 pr-4 font-medium">Price / item</th>
                        <th class="pb-1 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in selectedLines" :key="line.auction.id">
                        <td class="py-1 pr-4">{{ line.auction.title }}</td>
                        <td class="py-1 pr-4">
                            <input
                                v-model="line.quantity"
                                type="number"
                                min="1"
                                :max="line.auction.leftover_quantity"
                                required
                                :aria-label="`Quantity of ${line.auction.title}`"
                                class="border rounded px-2 py-1 w-20"
                            />
                            <span class="ml-1 text-xs text-gray-400 dark:text-gray-500"
                                >/ {{ line.auction.leftover_quantity }}</span
                            >
                        </td>
                        <td class="py-1 pr-4">
                            <input
                                v-model="line.price"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                :aria-label="`Price per item of ${line.auction.title}`"
                                class="border rounded px-2 py-1 w-28"
                            />
                        </td>
                        <td class="py-1 text-right">
                            {{
                                formatMoney(
                                    Number(line.quantity || 0) * Number(line.price || 0),
                                    currencySymbol,
                                )
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <label
                        :for="`${uid}-buyer`"
                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                        >Buyer</label
                    >
                    <select
                        :id="`${uid}-buyer`"
                        v-model="saleUsername"
                        required
                        class="border rounded px-3 py-2 w-48"
                    >
                        <option value="" disabled>Select user</option>
                        <option v-for="u in users" :key="u.id" :value="u.username">
                            {{ u.username }}
                        </option>
                    </select>
                </div>
                <div class="flex items-center gap-4">
                    <span class="font-semibold text-gray-800 dark:text-gray-100">
                        Total {{ formatMoney(saleTotal, currencySymbol) }}
                    </span>
                    <button
                        type="submit"
                        :disabled="saleSaving"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60 text-sm"
                    >
                        {{ saleSaving ? "Saving..." : "Create override sale" }}
                    </button>
                </div>
            </div>
        </form>

        <div v-if="sales.length > 0" class="mt-8">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                Override sales
            </h3>
            <ul class="divide-y dark:divide-gray-700">
                <li
                    v-for="sale in sales"
                    :key="sale.id"
                    class="py-3 flex flex-wrap items-start justify-between gap-3"
                >
                    <div class="text-sm">
                        <p class="font-medium text-gray-800 dark:text-gray-100">
                            #{{ sale.id }} · {{ sale.user.username }}
                            <span class="ml-2 text-xs font-normal text-gray-400 dark:text-gray-500">
                                {{ formatDate(sale.created_at) }}
                            </span>
                        </p>
                        <p
                            v-for="item in sale.items"
                            :key="item.auction_id"
                            class="text-gray-500 dark:text-gray-400"
                        >
                            {{ item.quantity }} × {{ item.auction_title }} at
                            {{ formatMoney(item.price_per_item, currencySymbol) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="font-semibold text-gray-800 dark:text-gray-100">
                            {{ formatMoney(sale.total, currencySymbol) }}
                        </span>
                        <a
                            :href="overrideSaleQuoteUrl(sale.id)"
                            target="_blank"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                        >
                            Quote
                        </a>
                        <button
                            type="button"
                            @click="removeSale(sale)"
                            class="text-gray-400 hover:text-red-500 dark:hover:text-red-400"
                        >
                            Delete
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
