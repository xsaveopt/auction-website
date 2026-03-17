<script setup>
import { computed, ref, onMounted, onUnmounted, provide, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "./api.js";
import { HEARTBEAT_INTERVAL_MS, presencePayload } from "./presence.js";
import { useTheme } from "./useTheme.js";

const { isDark, toggleTheme } = useTheme();

const route = useRoute();
const router = useRouter();
const user = ref(null);
const loading = ref(true);
const rawSchedule = ref(null);
const ssoEnabled = ref(false);
const currencySymbol = ref("$");
const now = ref(new Date());
const heartbeatData = ref(null);
let serverOffsetMs = 0; // server time minus browser time
const serverClockSeconds = ref(0); // seconds since midnight in server-local time

function parseTime(str) {
    const [h, m] = str.split(":").map(Number);
    return h * 60 + m;
}

function isWeekend(date) {
    const day = date.getDay();
    return day === 0 || day === 6;
}

function isBiddingOpenNow(sched, date) {
    if (!sched) return true;
    if (sched.weekends_open && isWeekend(date)) return true;
    const current = date.getHours() * 60 + date.getMinutes();
    return current < parseTime(sched.closed_start) || current >= parseTime(sched.closed_end);
}

function currentFractionalMinutes(date) {
    return date.getHours() * 60 + date.getMinutes() + date.getSeconds() / 60;
}

function formatRemaining(minutes) {
    const total = Math.ceil(minutes);
    if (total < 60) return `${total}m`;
    const h = Math.floor(total / 60);
    const m = total % 60;
    if (h < 24) return `${h}h ${m}m`;
    const d = Math.floor(h / 24);
    return `${d}d ${h % 24}h`;
}

const schedule = computed(() => {
    if (!rawSchedule.value) return null;
    return {
        ...rawSchedule.value,
        is_open: isBiddingOpenNow(rawSchedule.value, now.value),
    };
});

const scheduleBar = computed(() => {
    if (!rawSchedule.value || !rawSchedule.value.enabled) return null;
    const date = now.value;
    const day = date.getDay();
    const current = currentFractionalMinutes(date);
    const start = parseTime(rawSchedule.value.closed_start);
    const end = parseTime(rawSchedule.value.closed_end);
    const isOpen = schedule.value?.is_open;
    const weekendsOpen = rawSchedule.value.weekends_open;

    if (!isOpen) {
        // Closed: progress from closed_start → closed_end
        const total = end - start;
        const elapsed = current - start;
        const remaining = end - current;
        return {
            open: false,
            percent: (elapsed / total) * 100,
            label: `Bidding opens in ${formatRemaining(remaining)}`,
        };
    }

    // --- Market is open ---

    // Weekend with weekends_open: window is Friday closed_end → Monday closed_start
    if (weekendsOpen && isWeekend(date)) {
        const total = 1440 - end + 2 * 1440 + start;
        const daysSinceFriday = day === 6 ? 1 : 2;
        const elapsed = 1440 - end + (daysSinceFriday - 1) * 1440 + current;
        const remaining = total - elapsed;
        return {
            open: true,
            percent: (elapsed / total) * 100,
            label: `Bidding open for the weekend · closes in ${formatRemaining(remaining)}`,
        };
    }

    // Open before work hours
    if (current < start) {
        const remaining = start - current;

        if (day === 1 && weekendsOpen) {
            // Monday morning, weekends were open: window started at Friday's closed_end
            const total = 1440 - end + 2 * 1440 + start;
            const elapsed = 1440 - end + 2 * 1440 + current;
            return {
                open: true,
                percent: (elapsed / total) * 100,
                label: `Bidding closes in ${formatRemaining(remaining)}`,
            };
        }

        // Regular weekday morning: window started at previous day's closed_end
        const total = 1440 - end + start;
        const elapsed = 1440 - end + current;
        return {
            open: true,
            percent: (elapsed / total) * 100,
            label: `Bidding closes in ${formatRemaining(remaining)}`,
        };
    }

    // Open after work hours — next close depends on weekends_open
    const isFriday = day === 5;
    if (isFriday && weekendsOpen) {
        // Friday evening: open until Monday's closed_start
        const total = 1440 - end + 2 * 1440 + start;
        const elapsed = current - end;
        const remaining = total - elapsed;
        return {
            open: true,
            percent: (elapsed / total) * 100,
            label: `Bidding open for the weekend · closes in ${formatRemaining(remaining)}`,
        };
    }

    // Regular weekday evening: open until tomorrow's closed_start
    const total = 1440 - end + start;
    const elapsed = current - end;
    const remaining = total - elapsed;
    return {
        open: true,
        percent: (elapsed / total) * 100,
        label: `Bidding closes in ${formatRemaining(remaining)}`,
    };
});

const serverClock = computed(() => {
    const total = ((serverClockSeconds.value % 86400) + 86400) % 86400;
    const h = String(Math.floor(total / 3600)).padStart(2, "0");
    const m = String(Math.floor((total % 3600) / 60)).padStart(2, "0");
    const s = String(total % 60).padStart(2, "0");
    return `${h}:${m}:${s}`;
});

const shellWidthClass = "max-w-7xl";

async function fetchUser() {
    try {
        const data = await api("/user");
        user.value = data.user;
    } catch {
        user.value = null;
    } finally {
        loading.value = false;
    }
}

async function fetchSchedule() {
    try {
        const data = await api("/schedule");
        rawSchedule.value = data.schedule;
        if (data.schedule?.server_time) {
            serverOffsetMs = new Date(data.schedule.server_time).getTime() - Date.now();
            now.value = new Date(Date.now() + serverOffsetMs);
        }
        if (data.schedule?.server_time_local) {
            const [h, m, s] = data.schedule.server_time_local.split(":").map(Number);
            serverClockSeconds.value = h * 3600 + m * 60 + s;
        }
        if (data.schedule?.currency_symbol) {
            currencySymbol.value = data.schedule.currency_symbol;
        }
    } catch {
        // ignore
    }
}

async function fetchSsoEnabled() {
    try {
        const data = await api("/auth/sso/enabled");
        ssoEnabled.value = !!data.enabled;
    } catch {
        ssoEnabled.value = false;
    }
}

async function sendPresenceHeartbeat() {
    try {
        const data = await api("/presence/heartbeat", {
            method: "POST",
            body: JSON.stringify(presencePayload(route)),
        });
        heartbeatData.value = data;
    } catch {
        // ignore presence failures so navigation stays responsive
    }
}

// Poll schedule every 60s to keep is_open in sync
let scheduleInterval;
let presenceInterval;
let clockInterval;
function startHeartbeat() {
    if (!presenceInterval) {
        sendPresenceHeartbeat();
        presenceInterval = setInterval(sendPresenceHeartbeat, HEARTBEAT_INTERVAL_MS);
    }
}

function stopHeartbeat() {
    clearInterval(presenceInterval);
    presenceInterval = null;
}

function handleVisibilityChange() {
    if (document.hidden) {
        stopHeartbeat();
    } else {
        startHeartbeat();
    }
}

onMounted(() => {
    fetchUser();
    fetchSchedule();
    fetchSsoEnabled();
    startHeartbeat();
    scheduleInterval = setInterval(fetchSchedule, 60000);
    clockInterval = setInterval(() => {
        now.value = new Date(Date.now() + serverOffsetMs);
        serverClockSeconds.value++;
    }, 1000);
    document.addEventListener("visibilitychange", handleVisibilityChange);
});
watch(
    () => route.fullPath,
    () => {
        sendPresenceHeartbeat();
    },
);
onUnmounted(() => {
    clearInterval(scheduleInterval);
    stopHeartbeat();
    clearInterval(clockInterval);
    document.removeEventListener("visibilitychange", handleVisibilityChange);
});

async function logout() {
    await api("/logout", { method: "POST" });
    user.value = null;
    if (ssoEnabled.value) {
        window.location.href = "/";
    } else {
        router.push("/");
    }
}

function onLogin(u) {
    user.value = u;
    router.push("/");
}

provide("user", user);
provide("onLogin", onLogin);
provide("schedule", schedule);
provide("currencySymbol", currencySymbol);
provide("heartbeatData", heartbeatData);
</script>

<template>
    <div v-if="!loading">
        <nav class="bg-white dark:bg-gray-800 shadow dark:shadow-gray-700/20 mb-6">
            <div :class="[shellWidthClass, 'mx-auto px-4 py-3 flex items-center justify-between']">
                <router-link to="/" class="text-xl font-bold text-gray-800 dark:text-gray-100"
                    >Auction House</router-link
                >
                <div class="flex items-center gap-4">
                    <div v-if="scheduleBar" class="flex items-center gap-2">
                        <div
                            class="w-28 h-2 rounded-full overflow-hidden"
                            :class="
                                scheduleBar.open
                                    ? 'bg-green-100 dark:bg-green-900'
                                    : 'bg-orange-100 dark:bg-orange-900'
                            "
                        >
                            <div
                                class="h-full rounded-full transition-[width] duration-1000 ease-linear"
                                :class="scheduleBar.open ? 'bg-green-500' : 'bg-orange-500'"
                                :style="{ width: scheduleBar.percent + '%' }"
                            ></div>
                        </div>
                        <span
                            class="text-xs whitespace-nowrap"
                            :class="
                                scheduleBar.open
                                    ? 'text-green-700 dark:text-green-400'
                                    : 'text-orange-700 dark:text-orange-400'
                            "
                        >
                            {{ scheduleBar.label }}
                        </span>
                    </div>
                    <span
                        class="text-xs tabular-nums text-gray-500 dark:text-gray-400"
                        title="Server time"
                        >{{ serverClock }}</span
                    >
                    <button
                        @click="toggleTheme"
                        class="p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                    >
                        <!-- Sun (shown in dark mode, click to go light) -->
                        <svg
                            v-if="isDark"
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <circle cx="12" cy="12" r="5" />
                            <path
                                d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"
                            />
                        </svg>
                        <!-- Moon (shown in light mode, click to go dark) -->
                        <svg
                            v-else
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                        </svg>
                    </button>
                    <template v-if="user">
                        <span class="text-gray-600 dark:text-gray-300">{{ user.username }}</span>
                        <router-link
                            to="/dashboard"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                            >My Bids</router-link
                        >
                        <router-link
                            v-if="user.is_admin"
                            to="/admin/results"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                            >Results</router-link
                        >
                        <router-link
                            v-if="user.is_admin"
                            to="/admin/questions"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                            >Questions</router-link
                        >
                        <router-link
                            v-if="user.is_admin"
                            to="/auctions/new"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                            >Sell Item</router-link
                        >
                        <button
                            @click="logout"
                            class="text-red-600 dark:text-red-400 hover:underline"
                        >
                            Logout
                        </button>
                    </template>
                    <template v-else>
                        <a
                            v-if="ssoEnabled"
                            href="/auth/microsoft/redirect"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                            >Login with Microsoft</a
                        >
                        <template v-else>
                            <router-link
                                to="/login"
                                class="text-blue-600 dark:text-blue-400 hover:underline"
                                >Login</router-link
                            >
                            <router-link
                                to="/register"
                                class="text-blue-600 dark:text-blue-400 hover:underline"
                                >Register</router-link
                            >
                        </template>
                    </template>
                </div>
            </div>
        </nav>
        <main :class="[shellWidthClass, 'mx-auto px-4']">
            <router-view />
        </main>
    </div>
</template>
