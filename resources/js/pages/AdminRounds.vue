<script setup>
import { ref, inject, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const user = inject("user");
const rounds = ref([]);
const loading = ref(true);
const creating = ref(false);
const newRoundName = ref("");
const createError = ref("");
const closingId = ref(null);
const closeError = ref("");
const confirmingClose = ref(null);

if (!user.value?.is_admin) {
    router.push("/");
}

onMounted(async () => {
    await load();
});

async function load() {
    loading.value = true;
    try {
        const data = await api("/rounds");
        rounds.value = data.rounds;
    } finally {
        loading.value = false;
    }
}

const activeRound = () => rounds.value.find((r) => r.status === "active");

async function createRound() {
    if (!newRoundName.value.trim()) return;
    creating.value = true;
    createError.value = "";
    try {
        const data = await api("/rounds", {
            method: "POST",
            body: JSON.stringify({ name: newRoundName.value.trim() }),
        });
        rounds.value.unshift(data.round);
        newRoundName.value = "";
    } catch (e) {
        createError.value = e.data?.message || "Failed to create round.";
    } finally {
        creating.value = false;
    }
}

function askClose(round) {
    confirmingClose.value = round;
}

function cancelClose() {
    confirmingClose.value = null;
}

async function confirmClose() {
    const round = confirmingClose.value;
    if (!round) return;
    confirmingClose.value = null;
    closingId.value = round.id;
    closeError.value = "";
    try {
        const data = await api(`/rounds/${round.id}/close`, { method: "POST" });
        const idx = rounds.value.findIndex((r) => r.id === round.id);
        if (idx !== -1) rounds.value[idx] = data.round;
    } catch (e) {
        closeError.value = e.data?.message || "Failed to close round.";
    } finally {
        closingId.value = null;
    }
}

function formatDate(d) {
    if (!d) return "—";
    return d.slice(0, 16).replace("T", " ");
}

function resultsLink(round) {
    return `/admin/results?round_id=${round.id}`;
}
</script>

<template>
    <div>
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Auction Rounds</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
            Group auctions into named rounds. Closing a round immediately ends all active auctions
            within it.
        </p>

        <!-- Create form -->
        <div class="mb-6">
            <p v-if="activeRound()" class="text-xs text-amber-600 dark:text-amber-400 mb-2">
                A round is currently active. Close it before creating a new one.
            </p>
            <form class="flex gap-2 items-center" @submit.prevent="createRound">
                <input
                    v-model="newRoundName"
                    type="text"
                    placeholder="Round name, e.g. Q2 2026"
                    :disabled="!!activeRound() || creating"
                    class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 disabled:opacity-50"
                />
                <button
                    type="submit"
                    :disabled="!!activeRound() || creating || !newRoundName.trim()"
                    class="px-4 py-2 text-sm font-medium bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ creating ? "Creating…" : "New Round" }}
                </button>
            </form>
            <p v-if="createError" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ createError }}
            </p>
        </div>

        <!-- Confirm close dialog -->
        <div
            v-if="confirmingClose"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        >
            <div
                class="bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 max-w-sm w-full mx-4 border border-gray-200 dark:border-gray-700"
            >
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Close "{{ confirmingClose.name }}"?
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-5">
                    This will immediately end all active auctions in this round. This cannot be
                    undone.
                </p>
                <div class="flex gap-3 justify-end">
                    <button
                        class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                        @click="cancelClose"
                    >
                        Cancel
                    </button>
                    <button
                        class="px-4 py-2 text-sm font-medium bg-orange-600 text-white rounded-lg hover:bg-orange-700"
                        @click="confirmClose"
                    >
                        Close Round
                    </button>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-sm text-gray-500 dark:text-gray-400">Loading…</div>

        <div v-else-if="rounds.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
            No rounds yet. Create one above to get started.
        </div>

        <div v-else>
            <p v-if="closeError" class="mb-3 text-sm text-red-600 dark:text-red-400">
                {{ closeError }}
            </p>
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr
                        class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700"
                    >
                        <th class="pb-2 pr-4">Round</th>
                        <th class="pb-2 pr-4">Status</th>
                        <th class="pb-2 pr-4 text-right">Auctions</th>
                        <th class="pb-2 pr-4">Closed</th>
                        <th class="pb-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="round in rounds"
                        :key="round.id"
                        class="border-b border-gray-100 dark:border-gray-800"
                    >
                        <td class="py-2.5 pr-4 font-medium text-gray-800 dark:text-gray-100">
                            {{ round.name }}
                        </td>
                        <td class="py-2.5 pr-4">
                            <span
                                v-if="round.status === 'active'"
                                class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"
                                ></span>
                                Active
                            </span>
                            <span
                                v-else
                                class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400"
                            >
                                Ended
                            </span>
                        </td>
                        <td class="py-2.5 pr-4 text-right text-gray-600 dark:text-gray-400">
                            {{ round.auction_count ?? "—" }}
                        </td>
                        <td class="py-2.5 pr-4 text-gray-500 dark:text-gray-400">
                            {{ formatDate(round.ends_at) }}
                        </td>
                        <td class="py-2.5">
                            <div class="flex gap-2 items-center">
                                <button
                                    v-if="round.status === 'active'"
                                    :disabled="closingId === round.id"
                                    class="text-xs font-medium px-3 py-1.5 rounded-lg bg-orange-100 text-orange-700 hover:bg-orange-200 dark:bg-orange-900/30 dark:text-orange-400 dark:hover:bg-orange-900/50 disabled:opacity-50"
                                    @click="askClose(round)"
                                >
                                    {{ closingId === round.id ? "Closing…" : "Close Round" }}
                                </button>
                                <router-link
                                    v-if="round.status === 'ended'"
                                    :to="resultsLink(round)"
                                    class="text-xs font-medium px-3 py-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/40"
                                >
                                    View Results →
                                </router-link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
