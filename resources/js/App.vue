<script setup>
import { computed, ref, onMounted, onUnmounted, provide, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "./api.js";
import { HEARTBEAT_INTERVAL_MS, presencePayload } from "./presence.js";

const route = useRoute();
const router = useRouter();
const user = ref(null);
const loading = ref(true);
const rawSchedule = ref(null);
const ssoEnabled = ref(false);
const now = ref(new Date());

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
    return (
        current < parseTime(sched.closed_start) ||
        current >= parseTime(sched.closed_end)
    );
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
    if (!rawSchedule.value) return null;
    const date = now.value;
    const day = date.getDay();
    const current = currentFractionalMinutes(date);
    const start = parseTime(rawSchedule.value.closed_start);
    const end = parseTime(rawSchedule.value.closed_end);
    const isOpen = schedule.value?.is_open;
    const weekendsOpen = rawSchedule.value.weekends_open;

    // Weekend with weekends_open enabled
    if (weekendsOpen && isWeekend(date)) {
        const daysUntilMonday = day === 6 ? 2 : 1;
        const remaining =
            (daysUntilMonday - 1) * 1440 + (1440 - current) + start;
        return {
            open: true,
            percent: 0,
            label: `Open all weekend · closes in ${formatRemaining(remaining)}`,
        };
    }

    if (!isOpen) {
        // Closed: progress through closed_start → closed_end
        const total = end - start;
        const elapsed = current - start;
        const remaining = end - current;
        return {
            open: false,
            percent: (elapsed / total) * 100,
            label: `Opens in ${formatRemaining(remaining)}`,
        };
    }

    // Open before work hours
    if (current < start) {
        const remaining = start - current;
        // Open window: previous closed_end (or midnight Mon) → closed_start
        // For Monday morning, window started at midnight; other days at previous closed_end
        const windowStart = day === 1 ? 0 : 0; // simplify: midnight to closed_start
        const total = start - windowStart;
        const elapsed = current - windowStart;
        return {
            open: true,
            percent: (elapsed / total) * 100,
            label: `Closes in ${formatRemaining(remaining)}`,
        };
    }

    // Open after work hours — next close depends on weekends_open
    const isFriday = day === 5;
    if (isFriday && weekendsOpen) {
        // Open until Monday's closed_start
        const remaining = 1440 - current + 2 * 1440 + start;
        const total = 1440 - end + 2 * 1440 + start;
        const elapsed = current - end;
        return {
            open: true,
            percent: (elapsed / total) * 100,
            label: `Open for the weekend · closes in ${formatRemaining(remaining)}`,
        };
    }
    // Regular weekday evening (or Friday with weekends not open): open until tomorrow's closed_start
    const remaining = 1440 - current + start;
    const total = 1440 - end + start;
    const elapsed = current - end;
    return {
        open: true,
        percent: (elapsed / total) * 100,
        label: `Closes in ${formatRemaining(remaining)}`,
    };
});

const isAuctionDetailPage = computed(() =>
    /^\/auctions\/[^/]+$/.test(route.path),
);
const shellWidthClass = computed(() =>
    isAuctionDetailPage.value ? "max-w-7xl" : "max-w-4xl",
);

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
        await api("/presence/heartbeat", {
            method: "POST",
            body: JSON.stringify(presencePayload(route)),
        });
    } catch {
        // ignore presence failures so navigation stays responsive
    }
}

// Poll schedule every 60s to keep is_open in sync
let scheduleInterval;
let presenceInterval;
let clockInterval;
onMounted(() => {
    fetchUser();
    fetchSchedule();
    fetchSsoEnabled();
    sendPresenceHeartbeat();
    scheduleInterval = setInterval(fetchSchedule, 60000);
    presenceInterval = setInterval(
        sendPresenceHeartbeat,
        HEARTBEAT_INTERVAL_MS,
    );
    clockInterval = setInterval(() => {
        now.value = new Date();
    }, 1000);
});
watch(
    () => route.fullPath,
    () => {
        sendPresenceHeartbeat();
    },
);
onUnmounted(() => {
    clearInterval(scheduleInterval);
    clearInterval(presenceInterval);
    clearInterval(clockInterval);
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
</script>

<template>
    <div v-if="!loading">
        <nav class="bg-white shadow mb-6">
            <div
                :class="[
                    shellWidthClass,
                    'mx-auto px-4 py-3 flex items-center justify-between',
                ]"
            >
                <router-link to="/" class="text-xl font-bold text-gray-800"
                    >Auction House</router-link
                >
                <div class="flex items-center gap-4">
                    <div v-if="scheduleBar" class="flex items-center gap-2">
                        <div
                            class="w-28 h-2 rounded-full overflow-hidden"
                            :class="
                                scheduleBar.open
                                    ? 'bg-green-100'
                                    : 'bg-orange-100'
                            "
                        >
                            <div
                                class="h-full rounded-full transition-[width] duration-1000 ease-linear"
                                :class="
                                    scheduleBar.open
                                        ? 'bg-green-500'
                                        : 'bg-orange-500'
                                "
                                :style="{ width: scheduleBar.percent + '%' }"
                            ></div>
                        </div>
                        <span
                            class="text-xs whitespace-nowrap"
                            :class="
                                scheduleBar.open
                                    ? 'text-green-700'
                                    : 'text-orange-700'
                            "
                        >
                            {{ scheduleBar.label }}
                        </span>
                    </div>
                    <template v-if="user">
                        <span class="text-gray-600">{{ user.username }}</span>
                        <router-link
                            v-if="user.is_admin"
                            to="/admin/results"
                            class="text-blue-600 hover:underline"
                            >Results</router-link
                        >
                        <router-link
                            v-if="user.is_admin"
                            to="/admin/questions"
                            class="text-blue-600 hover:underline"
                            >Questions</router-link
                        >
                        <router-link
                            v-if="user.is_admin"
                            to="/auctions/new"
                            class="text-blue-600 hover:underline"
                            >Sell Item</router-link
                        >
                        <button
                            @click="logout"
                            class="text-red-600 hover:underline"
                        >
                            Logout
                        </button>
                    </template>
                    <template v-else>
                        <a
                            v-if="ssoEnabled"
                            href="/auth/microsoft/redirect"
                            class="text-blue-600 hover:underline"
                            >Login with Microsoft</a
                        >
                        <template v-else>
                            <router-link
                                to="/login"
                                class="text-blue-600 hover:underline"
                                >Login</router-link
                            >
                            <router-link
                                to="/register"
                                class="text-blue-600 hover:underline"
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
