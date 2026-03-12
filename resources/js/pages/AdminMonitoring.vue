<script setup>
import { computed, inject, onMounted, onUnmounted, ref } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const user = inject("user");
const currencySymbol = inject("currencySymbol");
const monitoring = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const error = ref("");

let refreshTimer = null;

if (!user.value?.is_admin) {
    router.push("/");
}

onMounted(async () => {
    await loadMonitoring();
    refreshTimer = window.setInterval(() => {
        void loadMonitoring({ background: true });
    }, 30000);
});

onUnmounted(() => {
    if (refreshTimer !== null) {
        window.clearInterval(refreshTimer);
    }
});

async function loadMonitoring(options = {}) {
    const { background = false } = options;

    if (background) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        error.value = "";
        const data = await api("/admin/monitoring");
        monitoring.value = data.monitoring;
    } catch (e) {
        error.value = e.data?.message || "Failed to load monitoring data.";
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
}

function formatNumber(value, digits = 0) {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });
}

function formatMoney(amount) {
    return `${currencySymbol.value}${formatNumber(amount, 2)}`;
}

function formatDuration(ms) {
    if (ms === null || ms === undefined) {
        return "—";
    }

    if (ms >= 1000) {
        return `${formatNumber(ms / 1000, 2)}s`;
    }

    return `${formatNumber(ms, 0)}ms`;
}

function formatBytes(bytes) {
    if (bytes === null || bytes === undefined) {
        return "—";
    }

    const units = ["B", "KB", "MB", "GB", "TB"];
    let value = Number(bytes);
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${formatNumber(value, value >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function formatUptime(seconds) {
    if (seconds === null || seconds === undefined) {
        return "—";
    }

    const totalSeconds = Math.max(Number(seconds), 0);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);

    if (days > 0) {
        return `${days}d ${hours}h`;
    }

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }

    return `${minutes}m`;
}

function formatMinuteLabel(value) {
    return new Date(value).toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    });
}

function statusTone(status) {
    if (status >= 500) {
        return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400";
    }

    if (status >= 400) {
        return "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400";
    }

    if (status >= 300) {
        return "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400";
    }

    return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400";
}

const summaryCards = computed(() => {
    if (!monitoring.value) {
        return [];
    }

    const requests = monitoring.value.requests;
    const application = monitoring.value.application;

    return [
        {
            label: "Requests / second",
            value: formatNumber(requests.requests_per_second, 2),
            detail: `${formatNumber(requests.requests_last_minute)} requests in the last minute`,
            accent: "text-blue-700 dark:text-blue-400",
        },
        {
            label: "Average latency",
            value: formatDuration(requests.average_latency_ms),
            detail: `p50 ${formatDuration(requests.p50_latency_ms)} · p95 ${formatDuration(requests.p95_latency_ms)}`,
            accent: "text-emerald-700 dark:text-emerald-400",
        },
        {
            label: "Server error rate",
            value: `${formatNumber(requests.error_rate_percent, 1)}%`,
            detail: `${formatNumber(requests.server_errors_last_five_minutes)} 5xx responses in the last 5 minutes`,
            accent: "text-red-700 dark:text-red-400",
        },
        {
            label: "Online users",
            value: formatNumber(application.online_users),
            detail: `${formatNumber(application.active_auctions)} active auctions · ${formatNumber(application.bids_today)} bids today`,
            accent: "text-violet-700 dark:text-violet-400",
        },
        {
            label: "Current live bid total",
            value: formatMoney(application.current_bid_total),
            detail: `${formatNumber(application.total_bids)} bids placed overall`,
            accent: "text-amber-700 dark:text-amber-400",
        },
        {
            label: "Slow requests",
            value: formatNumber(requests.slow_requests_last_five_minutes),
            detail: `Requests over 1s in the last 5 minutes · max ${formatDuration(requests.max_latency_ms)}`,
            accent: "text-slate-700 dark:text-slate-300",
        },
    ];
});

const traffic = computed(() => monitoring.value?.traffic ?? []);
const slowPaths = computed(() => monitoring.value?.slow_paths ?? []);
const statusCodes = computed(() => monitoring.value?.status_codes ?? []);
const appData = computed(() => monitoring.value?.application ?? null);
const caddy = computed(() => monitoring.value?.caddy ?? null);

const maxTrafficCount = computed(() =>
    Math.max(1, ...traffic.value.map((point) => point.request_count)),
);

const maxLatency = computed(() =>
    Math.max(1, ...traffic.value.map((point) => point.avg_latency_ms)),
);
</script>

<template>
    <div>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold">Admin Monitoring</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Live traffic, latency, and infrastructure signals for the auction app.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <p v-if="monitoring?.generated_at" class="text-xs text-gray-500 dark:text-gray-400">
                    Updated {{ new Date(monitoring.generated_at).toLocaleTimeString() }}
                </p>
                <button
                    @click="loadMonitoring({ background: true })"
                    class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                    :disabled="refreshing"
                >
                    {{ refreshing ? "Refreshing..." : "Refresh" }}
                </button>
            </div>
        </div>

        <div
            v-if="error"
            class="mb-4 rounded bg-red-100 p-3 text-red-700 dark:bg-red-900/30 dark:text-red-400"
        >
            {{ error }}
        </div>

        <p v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</p>

        <template v-else-if="monitoring">
            <div class="mb-6 grid gap-3 lg:grid-cols-3">
                <div
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="rounded bg-white p-4 shadow dark:bg-gray-800"
                >
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ card.label }}
                    </p>
                    <p class="mt-2 text-2xl font-bold" :class="card.accent">
                        {{ card.value }}
                    </p>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ card.detail }}
                    </p>
                </div>
            </div>

            <div class="mb-6 grid gap-6 xl:grid-cols-[1.6fr_1fr]">
                <section class="rounded bg-white p-5 shadow dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Traffic over the last 30 minutes</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Each row shows request volume, average latency, and 5xx errors.
                            </p>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Max {{ formatNumber(maxTrafficCount) }} req/min
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="point in traffic"
                            :key="point.minute"
                            class="grid gap-2 md:grid-cols-[72px_1fr_84px_56px]"
                        >
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ formatMinuteLabel(point.minute) }}
                            </p>
                            <div>
                                <div
                                    class="h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                                >
                                    <div
                                        class="h-full rounded-full bg-blue-500"
                                        :style="{
                                            width: `${Math.max(
                                                (point.request_count / maxTrafficCount) * 100,
                                                point.request_count > 0 ? 4 : 0,
                                            )}%`,
                                        }"
                                    ></div>
                                </div>
                                <div
                                    class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900"
                                >
                                    <div
                                        class="h-full rounded-full bg-emerald-500"
                                        :style="{
                                            width: `${Math.max(
                                                (point.avg_latency_ms / maxLatency) * 100,
                                                point.avg_latency_ms > 0 ? 4 : 0,
                                            )}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                            <p class="text-right text-sm text-gray-700 dark:text-gray-200">
                                {{ formatNumber(point.request_count) }} req
                            </p>
                            <p class="text-right text-xs text-gray-500 dark:text-gray-400">
                                {{ formatDuration(point.avg_latency_ms) }}
                                <span
                                    v-if="point.error_count > 0"
                                    class="ml-1 font-medium text-red-600 dark:text-red-400"
                                >
                                    · {{ point.error_count }} 5xx
                                </span>
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded bg-white p-5 shadow dark:bg-gray-800">
                    <h2 class="text-lg font-semibold">Recent status codes</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Counts from the last 15 minutes of Laravel requests.
                    </p>

                    <div v-if="statusCodes.length" class="flex flex-wrap gap-2">
                        <span
                            v-for="status in statusCodes"
                            :key="status.status"
                            class="rounded-full px-3 py-1 text-sm font-medium"
                            :class="statusTone(status.status)"
                        >
                            {{ status.status }} · {{ formatNumber(status.request_count) }}
                        </span>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        No recent requests yet.
                    </p>
                </section>
            </div>

            <div class="mb-6 grid gap-6 xl:grid-cols-[1.2fr_1fr]">
                <section class="rounded bg-white p-5 shadow dark:bg-gray-800">
                    <h2 class="text-lg font-semibold">Slowest paths</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Average latency over the last 15 minutes for endpoints with at least 3
                        requests.
                    </p>

                    <div v-if="slowPaths.length" class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b text-left text-gray-500 dark:border-gray-700 dark:text-gray-400"
                                >
                                    <th class="pb-2 font-medium">Path</th>
                                    <th class="pb-2 font-medium text-right">Req</th>
                                    <th class="pb-2 font-medium text-right">Avg</th>
                                    <th class="pb-2 font-medium text-right">Max</th>
                                    <th class="pb-2 font-medium text-right">5xx</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="path in slowPaths"
                                    :key="path.path"
                                    class="border-b last:border-0 dark:border-gray-700"
                                >
                                    <td
                                        class="py-2 font-mono text-xs text-gray-700 dark:text-gray-200"
                                    >
                                        {{ path.path }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatNumber(path.request_count) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatDuration(path.avg_latency_ms) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatDuration(path.max_latency_ms) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatNumber(path.error_count) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        Not enough recent traffic to rank slow paths yet.
                    </p>
                </section>

                <section class="rounded bg-white p-5 shadow dark:bg-gray-800">
                    <h2 class="text-lg font-semibold">Caddy runtime</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Read from Caddy's internal Prometheus metrics endpoint.
                    </p>

                    <template v-if="caddy?.available">
                        <div class="grid gap-3">
                            <div
                                class="rounded border border-gray-200 px-4 py-3 dark:border-gray-700"
                            >
                                <p
                                    class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Uptime
                                </p>
                                <p class="mt-1 text-xl font-bold text-blue-700 dark:text-blue-400">
                                    {{ formatUptime(caddy.uptime_seconds) }}
                                </p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    class="rounded border border-gray-200 px-4 py-3 dark:border-gray-700"
                                >
                                    <p
                                        class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Lifetime HTTP requests
                                    </p>
                                    <p class="mt-1 text-lg font-bold">
                                        {{ formatNumber(caddy.total_http_requests) }}
                                    </p>
                                </div>
                                <div
                                    class="rounded border border-gray-200 px-4 py-3 dark:border-gray-700"
                                >
                                    <p
                                        class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Average response
                                    </p>
                                    <p class="mt-1 text-lg font-bold">
                                        {{ formatDuration(caddy.average_response_ms) }}
                                    </p>
                                </div>
                                <div
                                    class="rounded border border-gray-200 px-4 py-3 dark:border-gray-700"
                                >
                                    <p
                                        class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Goroutines
                                    </p>
                                    <p class="mt-1 text-lg font-bold">
                                        {{ formatNumber(caddy.goroutines) }}
                                    </p>
                                </div>
                                <div
                                    class="rounded border border-gray-200 px-4 py-3 dark:border-gray-700"
                                >
                                    <p
                                        class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Heap in use
                                    </p>
                                    <p class="mt-1 text-lg font-bold">
                                        {{ formatBytes(caddy.heap_inuse_bytes) }}
                                    </p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Resident memory: {{ formatBytes(caddy.resident_memory_bytes) }}
                            </p>
                        </div>
                    </template>
                    <div
                        v-else
                        class="rounded border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
                    >
                        Caddy metrics are currently unavailable. This is expected in local Octane
                        development if FrankenPHP/Caddy is not the running server.
                        <p v-if="caddy?.error" class="mt-2 break-words font-mono text-xs">
                            {{ caddy.error }}
                        </p>
                    </div>
                </section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded bg-white p-5 shadow dark:bg-gray-800">
                    <h2 class="text-lg font-semibold">Top watched auctions</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Based on the live presence heartbeat.
                    </p>

                    <div v-if="appData?.top_watched_auctions?.length" class="space-y-3">
                        <div
                            v-for="auction in appData.top_watched_auctions"
                            :key="auction.id"
                            class="rounded border border-gray-200 px-4 py-3 dark:border-gray-700"
                        >
                            <router-link
                                :to="`/auctions/${auction.id}`"
                                class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                            >
                                {{ auction.title }}
                            </router-link>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ formatNumber(auction.watcher_count) }} watcher{{
                                    auction.watcher_count !== 1 ? "s" : ""
                                }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        No active watchers right now.
                    </p>
                </section>

                <section class="rounded bg-white p-5 shadow dark:bg-gray-800">
                    <h2 class="text-lg font-semibold">Hot auctions</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Most competitive active auctions by bid count.
                    </p>

                    <div v-if="appData?.hot_auctions?.length" class="space-y-3">
                        <div
                            v-for="auction in appData.hot_auctions"
                            :key="auction.id"
                            class="rounded border border-gray-200 px-4 py-3 dark:border-gray-700"
                        >
                            <router-link
                                :to="`/auctions/${auction.id}`"
                                class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                            >
                                {{ auction.title }}
                            </router-link>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ formatNumber(auction.bid_count) }} bid{{
                                    auction.bid_count !== 1 ? "s" : ""
                                }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        No hot auctions yet.
                    </p>
                </section>
            </div>
        </template>
    </div>
</template>
