<script setup>
import { ref, computed, inject, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "../api.js";
import ConfirmDialog from "../ConfirmDialog.vue";

const router = useRouter();
const route = useRoute();
const user = inject("user");
const currencySymbol = inject("currencySymbol");

if (!user.value?.is_admin) {
    router.push("/");
}

/** @type {import("vue").Ref<Array>} */
const auctions = ref([]);
/** @type {import("vue").Ref<Array>} */
const rounds = ref([]);
/** @type {import("vue").Ref<Array>} */
const categories = ref([]);
const loading = ref(true);
const saving = ref(false);
const saveError = ref("");
const saveSuccess = ref("");
const confirmDialog = ref(null);

const selected = ref(/** @type {Set<number>} */ (new Set()));
const filterStatus = ref("all"); // all | active | ended | cancelled
const filterRound = ref(route.query.round_id ? String(route.query.round_id) : "all"); // all | unassigned | <round id as string>
const bulkAction = ref("assign_round"); // assign_round | assign_category | assign_location | end | cancel
const assignRoundId = ref(/** @type {number|null} */ (null));
const assignCategoryId = ref(/** @type {number|null} */ (null));
const assignLocation = ref("");

function syncRoundQuery(value) {
    const query = { ...route.query };
    if (value !== "all") {
        query.round_id = value;
    } else {
        delete query.round_id;
    }
    router.replace({ path: route.path, query });
}

let initialized = false;

onMounted(async () => {
    try {
        const [auctionData, roundData, currentData, categoryData] = await Promise.all([
            api("/auctions"),
            api("/rounds"),
            api("/rounds/current"),
            api("/categories"),
        ]);
        auctions.value = auctionData.auctions;
        rounds.value = roundData.rounds ?? [];
        categories.value = categoryData.categories ?? [];

        if (filterRound.value === "all" && currentData?.active?.id) {
            filterRound.value = String(currentData.active.id);
        }
        syncRoundQuery(filterRound.value);
    } finally {
        loading.value = false;
        initialized = true;
    }
});

watch(filterRound, (value) => {
    if (!initialized) return;
    syncRoundQuery(value);
});

const filteredAuctions = computed(() => {
    return auctions.value.filter((a) => {
        if (filterStatus.value !== "all") {
            const isActive = a.is_active;
            if (filterStatus.value === "active" && !isActive) return false;
            if (filterStatus.value === "ended" && (isActive || a.status === "cancelled"))
                return false;
            if (filterStatus.value === "cancelled" && a.status !== "cancelled") return false;
        }
        if (filterRound.value === "unassigned" && a.round !== null) return false;
        if (filterRound.value !== "all" && filterRound.value !== "unassigned") {
            if (a.round?.id !== Number(filterRound.value)) return false;
        }
        return true;
    });
});

function toggleAll() {
    if (allFilteredSelected.value) {
        for (const a of filteredAuctions.value) selected.value.delete(a.id);
    } else {
        for (const a of filteredAuctions.value) selected.value.add(a.id);
    }
    selected.value = new Set(selected.value);
}

function toggle(id) {
    if (selected.value.has(id)) {
        selected.value.delete(id);
    } else {
        selected.value.add(id);
    }
    selected.value = new Set(selected.value);
}

const allFilteredSelected = computed(
    () =>
        filteredAuctions.value.length > 0 &&
        filteredAuctions.value.every((a) => selected.value.has(a.id)),
);

const someFilteredSelected = computed(() =>
    filteredAuctions.value.some((a) => selected.value.has(a.id)),
);

const selectedInView = computed(() =>
    filteredAuctions.value.filter((a) => selected.value.has(a.id)),
);

async function executeBulk() {
    if (selectedInView.value.length === 0) return;
    saving.value = true;
    saveError.value = "";
    saveSuccess.value = "";
    const ids = selectedInView.value.map((a) => a.id);
    try {
        const body = { action: bulkAction.value, auction_ids: ids };
        if (bulkAction.value === "assign_round") body.round_id = assignRoundId.value;
        if (bulkAction.value === "assign_category") body.category_id = assignCategoryId.value;
        if (bulkAction.value === "assign_location")
            body.location = assignLocation.value.trim() || null;

        await api("/admin/auctions/bulk", {
            method: "PATCH",
            body: JSON.stringify(body),
        });

        // Update local state
        if (bulkAction.value === "assign_round") {
            const roundObj = assignRoundId.value
                ? (rounds.value.find((r) => r.id === assignRoundId.value) ?? null)
                : null;
            for (const a of auctions.value) {
                if (selected.value.has(a.id))
                    a.round = roundObj ? { id: roundObj.id, name: roundObj.name } : null;
            }
        } else if (bulkAction.value === "assign_location") {
            const loc = assignLocation.value.trim() || null;
            for (const a of auctions.value) {
                if (selected.value.has(a.id)) a.location = loc;
            }
        } else if (bulkAction.value === "assign_category") {
            const catObj = assignCategoryId.value
                ? (categories.value.find((c) => c.id === assignCategoryId.value) ?? null)
                : null;
            for (const a of auctions.value) {
                if (selected.value.has(a.id)) {
                    a.category_id = catObj?.id ?? null;
                    a.category = catObj ? { id: catObj.id, name: catObj.name } : null;
                }
            }
        } else if (bulkAction.value === "end") {
            for (const a of auctions.value) {
                if (selected.value.has(a.id) && a.is_active) {
                    a.status = "ended";
                    a.is_active = false;
                }
            }
        } else if (bulkAction.value === "cancel") {
            for (const a of auctions.value) {
                if (selected.value.has(a.id) && a.is_active) {
                    a.status = "cancelled";
                    a.is_active = false;
                }
            }
        }

        const count = ids.length;
        selected.value = new Set();
        saveSuccess.value = `${count} auction${count !== 1 ? "s" : ""} updated.`;
        setTimeout(() => (saveSuccess.value = ""), 3000);
    } catch (e) {
        saveError.value = e.data?.message || "Failed to update auctions.";
    } finally {
        saving.value = false;
    }
}

function applyBulk() {
    if (selectedInView.value.length === 0) return;
    if (bulkAction.value === "end" || bulkAction.value === "cancel") {
        const activeCount = selectedInView.value.filter((a) => a.is_active).length;
        if (activeCount === 0) {
            saveError.value = "None of the selected auctions are active.";
            return;
        }
        const label = bulkAction.value === "end" ? "end" : "cancel";
        confirmDialog.value = {
            message: `${label.charAt(0).toUpperCase() + label.slice(1)} ${activeCount} active auction${activeCount !== 1 ? "s" : ""}? This cannot be undone.`,
            confirmLabel: label.charAt(0).toUpperCase() + label.slice(1),
            danger: true,
            onConfirm: () => executeBulk(),
        };
    } else {
        executeBulk();
    }
}

function statusLabel(auction) {
    if (auction.is_active) return "Active";
    if (auction.status === "cancelled") return "Cancelled";
    return "Ended";
}

function statusClasses(auction) {
    if (auction.is_active)
        return "bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400";
    if (auction.status === "cancelled")
        return "bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400";
    return "bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400";
}

function formatDate(d) {
    if (!d) return "—";
    return d.slice(0, 10);
}
</script>

<template>
    <div>
        <ConfirmDialog
            v-if="confirmDialog"
            :message="confirmDialog.message"
            :confirm-label="confirmDialog.confirmLabel"
            :danger="confirmDialog.danger"
            @confirm="
                confirmDialog.onConfirm();
                confirmDialog = null;
            "
            @cancel="confirmDialog = null"
        />

        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Auction Listing</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
            Select auctions and apply bulk actions.
        </p>

        <div v-if="loading" class="text-sm text-gray-500 dark:text-gray-400">Loading…</div>

        <template v-else>
            <!-- Filters -->
            <div class="flex flex-wrap gap-3 mb-4 items-center">
                <div class="flex items-center gap-1.5">
                    <label class="text-xs text-gray-500 dark:text-gray-400">Status:</label>
                    <select
                        v-model="filterStatus"
                        class="text-sm border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200"
                    >
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="ended">Ended</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="flex items-center gap-1.5">
                    <label class="text-xs text-gray-500 dark:text-gray-400">Round:</label>
                    <select
                        v-model="filterRound"
                        class="text-sm border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200"
                    >
                        <option value="all">All</option>
                        <option value="unassigned">Unassigned</option>
                        <option v-for="r in rounds" :key="r.id" :value="String(r.id)">
                            {{ r.name }}
                        </option>
                    </select>
                </div>
                <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">
                    {{ filteredAuctions.length }} auction{{
                        filteredAuctions.length !== 1 ? "s" : ""
                    }}
                </span>
            </div>

            <!-- Bulk action bar -->
            <div
                class="flex flex-wrap gap-3 items-center mb-3 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2.5"
            >
                <span class="text-sm text-gray-600 dark:text-gray-400 shrink-0">
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{
                        selectedInView.length
                    }}</span>
                    selected
                </span>
                <div class="flex items-center gap-2 ml-auto flex-wrap">
                    <!-- Action selector -->
                    <select
                        v-model="bulkAction"
                        class="text-sm border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200"
                    >
                        <option value="assign_round">Assign round</option>
                        <option value="assign_category">Assign category</option>
                        <option value="assign_location">Set location</option>
                        <option value="end">End auctions</option>
                        <option value="cancel">Cancel auctions</option>
                    </select>

                    <!-- Round picker -->
                    <select
                        v-if="bulkAction === 'assign_round'"
                        v-model="assignRoundId"
                        class="text-sm border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200"
                    >
                        <option :value="null">— Unassign —</option>
                        <option v-for="r in rounds" :key="r.id" :value="r.id">
                            {{ r.name }}
                        </option>
                    </select>

                    <!-- Location input -->
                    <input
                        v-else-if="bulkAction === 'assign_location'"
                        v-model="assignLocation"
                        type="text"
                        placeholder="Location (leave blank to clear)"
                        class="text-sm border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 w-56"
                    />

                    <!-- Category picker -->
                    <select
                        v-else-if="bulkAction === 'assign_category'"
                        v-model="assignCategoryId"
                        class="text-sm border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200"
                    >
                        <option :value="null">— Uncategorize —</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>

                    <button
                        :disabled="selectedInView.length === 0 || saving"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg text-white disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="
                            bulkAction === 'end' || bulkAction === 'cancel'
                                ? 'bg-red-600 hover:bg-red-700'
                                : 'bg-purple-600 hover:bg-purple-700'
                        "
                        @click="applyBulk"
                    >
                        {{ saving ? "Saving…" : "Apply" }}
                    </button>
                </div>
                <p v-if="saveError" class="w-full text-xs text-red-600 dark:text-red-400">
                    {{ saveError }}
                </p>
                <p v-if="saveSuccess" class="w-full text-xs text-green-600 dark:text-green-400">
                    {{ saveSuccess }}
                </p>
            </div>

            <!-- Table -->
            <div
                v-if="filteredAuctions.length === 0"
                class="text-sm text-gray-500 dark:text-gray-400"
            >
                No auctions match the current filters.
            </div>
            <table v-else class="w-full text-sm border-collapse">
                <thead>
                    <tr
                        class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700"
                    >
                        <th class="pb-2 pr-3 w-8">
                            <input
                                type="checkbox"
                                :checked="allFilteredSelected"
                                :indeterminate="someFilteredSelected && !allFilteredSelected"
                                class="rounded"
                                @change="toggleAll"
                            />
                        </th>
                        <th class="pb-2 pr-4">Title</th>
                        <th class="pb-2 pr-4">Status</th>
                        <th class="pb-2 pr-4">Round</th>
                        <th class="pb-2 pr-4">Ends</th>
                        <th class="pb-2 text-right">Starting price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="auction in filteredAuctions"
                        :key="auction.id"
                        class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/40 cursor-pointer"
                        @click="toggle(auction.id)"
                    >
                        <td class="py-2 pr-3">
                            <input
                                type="checkbox"
                                :checked="selected.has(auction.id)"
                                class="rounded"
                                @click.stop
                                @change="toggle(auction.id)"
                            />
                        </td>
                        <td class="py-2 pr-4">
                            <router-link
                                :to="`/auctions/${auction.id}`"
                                class="font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                @click.stop
                            >
                                {{ auction.title }}
                            </router-link>
                        </td>
                        <td class="py-2 pr-4">
                            <span
                                class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full"
                                :class="statusClasses(auction)"
                            >
                                {{ statusLabel(auction) }}
                            </span>
                        </td>
                        <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">
                            {{ auction.round?.name ?? "—" }}
                        </td>
                        <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">
                            {{ formatDate(auction.ends_at) }}
                        </td>
                        <td class="py-2 text-right text-gray-600 dark:text-gray-400">
                            {{ currencySymbol }}{{ Number(auction.starting_price).toFixed(2) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </template>
    </div>
</template>
