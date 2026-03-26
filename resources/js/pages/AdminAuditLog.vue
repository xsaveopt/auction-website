<script setup>
import { ref, inject, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "../api.js";

const props = defineProps({
    active: {
        type: Boolean,
        default: false,
    },
});
const router = useRouter();
const route = useRoute();
const user = inject("user");
const logs = ref([]);
const loading = ref(true);
const error = ref("");
const currentPage = ref(parseInt(route.query.page) || 1);
const lastPage = ref(1);
const total = ref(0);

if (!user.value?.is_admin) {
    router.push("/");
}

onMounted(async () => {
    await loadLogs(currentPage.value);
});

async function loadLogs(page = 1) {
    loading.value = true;
    error.value = "";
    try {
        const data = await api(`/admin/audit-log?page=${page}`);
        logs.value = data.logs;
        currentPage.value = data.current_page;
        lastPage.value = data.last_page;
        total.value = data.total;
        const query = { ...route.query };
        if (page > 1) {
            query.page = page;
        } else {
            delete query.page;
        }
        if (props.active) {
            router.replace({ path: route.path, query });
        }
    } catch (e) {
        error.value = "Failed to load audit log.";
    } finally {
        loading.value = false;
    }
}

async function goToPage(page) {
    if (page < 1 || page > lastPage.value) return;
    await loadLogs(page);
}

watch(
    () => route.query.page,
    (newPage) => {
        if (!props.active) {
            return;
        }

        const page = parseInt(newPage) || 1;
        if (page !== currentPage.value) {
            loadLogs(page);
        }
    },
);

watch(
    () => props.active,
    (isActive) => {
        if (!isActive) {
            return;
        }

        const page = parseInt(route.query.page) || 1;
        if (!logs.value.length || page !== currentPage.value) {
            loadLogs(page);
        }
    },
);

function formatDate(d) {
    if (!d) return "";
    return d.slice(0, 19).replace("T", " ");
}

function actionLabel(action) {
    const labels = {
        "auction.create": "Created auction",
        "auction.update": "Updated auction",
        "auction.delete": "Deleted auction",
        "auction.end": "Ended auction",
        "auction.cancel": "Cancelled auction",
        "auction.reactivate": "Reactivated auction",
        "auction.extend": "Extended auction",
        "bid.create": "Created bid (admin)",
        "bid.update": "Updated bid",
        "bid.delete": "Deleted bid",
        "announcement.create": "Posted announcement",
        "announcement.delete": "Removed announcement",
        "category.create": "Created category",
        "category.update": "Updated category",
        "category.delete": "Deleted category",
        "leftover_purchase.create": "Created leftover sale (admin)",
        "leftover_purchase.delete": "Deleted leftover sale",
        "question.answer": "Answered question",
        "question.delete": "Deleted question",
    };
    return labels[action] ?? action;
}

function actionColorClass(action) {
    if (action.endsWith(".delete") || action === "auction.cancel") {
        return "bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400";
    }
    if (action.endsWith(".create") || action === "announcement.create") {
        return "bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400";
    }
    return "bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400";
}

function formatData(data) {
    if (!data) return null;
    return Object.entries(data)
        .map(([k, v]) => `${k}: ${v}`)
        .join(" · ");
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold mb-4">Audit Log</h1>

        <p v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</p>

        <template v-else>
            <div
                v-if="error"
                class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-3 rounded mb-4"
            >
                {{ error }}
            </div>

            <p v-if="!error && logs.length === 0" class="text-gray-500 dark:text-gray-400">
                No audit log entries yet.
            </p>

            <template v-else>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                    {{ total }} total entries
                </p>

                <div class="overflow-x-auto rounded shadow">
                    <table class="w-full text-sm bg-white dark:bg-gray-800">
                        <thead>
                            <tr
                                class="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700"
                            >
                                <th class="px-4 py-2 font-medium">Time</th>
                                <th class="px-4 py-2 font-medium">Admin</th>
                                <th class="px-4 py-2 font-medium">Action</th>
                                <th class="px-4 py-2 font-medium">Target</th>
                                <th class="px-4 py-2 font-medium">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="log in logs"
                                :key="log.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            >
                                <td
                                    class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap"
                                >
                                    {{ formatDate(log.created_at) }}
                                </td>
                                <td
                                    class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap"
                                >
                                    {{ log.admin?.username ?? "—" }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="actionColorClass(log.action)"
                                    >
                                        {{ actionLabel(log.action) }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap"
                                >
                                    <span v-if="log.target_type">
                                        {{ log.target_type }} #{{ log.target_id }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td
                                    class="px-4 py-2 text-xs text-gray-600 dark:text-gray-300 max-w-xs truncate"
                                    :title="formatData(log.data)"
                                >
                                    {{ formatData(log.data) ?? "—" }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="lastPage > 1" class="mt-4 flex items-center gap-3">
                    <button
                        @click="goToPage(currentPage - 1)"
                        :disabled="currentPage <= 1"
                        class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm disabled:opacity-40 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Page {{ currentPage }} of {{ lastPage }}
                    </span>
                    <button
                        @click="goToPage(currentPage + 1)"
                        :disabled="currentPage >= lastPage"
                        class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm disabled:opacity-40 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Next
                    </button>
                </div>
            </template>
        </template>
    </div>
</template>
