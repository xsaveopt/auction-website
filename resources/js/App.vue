<script setup>
import { computed, ref, onMounted, onUnmounted, provide, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "./api.js";
import { HEARTBEAT_INTERVAL_MS, presencePayload } from "./presence.js";
import { usePushNotifications } from "./pushNotifications.js";
import { useTheme } from "./useTheme.js";
import { useNotifications } from "./useNotifications.js";
import NotificationToast from "./NotificationToast.vue";

const { isDark, toggleTheme } = useTheme();
const { notify } = useNotifications();
const {
    pushSupported,
    pushEnabled,
    browserPermission,
    registerPushServiceWorker,
    refreshPushSubscriptionState,
    syncPushSubscription,
    enablePushNotifications,
    clearPushSubscription,
} = usePushNotifications();
const notificationsSupported = pushSupported;

async function handleNotificationBell() {
    try {
        const enabled = await enablePushNotifications();
        if (enabled) {
            notify("Browser notifications enabled.", "success");
        } else if (browserPermission.value === "denied") {
            notify("Notifications are blocked in your browser settings.", "warning", 8000);
        }
    } catch (error) {
        notify(error.message ?? "Couldn't enable browser notifications.", "error", 8000);
    }
}

const route = useRoute();
const router = useRouter();
const user = ref(null);
const loading = ref(true);
const rawSchedule = ref(null);
const ssoEnabled = ref(false);
const currencySymbol = ref("$");
const now = ref(new Date());
const heartbeatData = ref(null);
const siteLocked = ref(false);
const lockMessage = ref(null);
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

const shellWidthClass = "max-w-[1800px]";

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
        siteLocked.value = !!data.schedule?.site_locked;
        lockMessage.value = data.schedule?.lock_message || null;
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

// Cross-page bid state tracking for notifications
const trackedAuctionStates = ref({});
let myAuctionsInterval;

async function pollMyAuctions() {
    if (!user.value || user.value.is_admin) return;
    // Skip notifications for the auction currently open in AuctionDetail
    const viewingAuctionId = route.params.id ? String(route.params.id) : null;
    try {
        const data = await api("/my-auctions");
        /** @type {Record<string, {title: string, in_active: boolean, won_quantity?: number, won?: boolean}>} */
        const newStates = {};
        for (const auction of data.active ?? []) {
            const myBid = auction.bids?.find((b) => b.user.id === user.value.id);
            newStates[String(auction.id)] = {
                title: auction.title,
                in_active: true,
                won_quantity: myBid?.won_quantity ?? 0,
            };
        }
        for (const auction of data.won ?? []) {
            newStates[String(auction.id)] = { title: auction.title, in_active: false, won: true };
        }
        for (const auction of data.lost ?? []) {
            newStates[String(auction.id)] = { title: auction.title, in_active: false, won: false };
        }

        if (Object.keys(trackedAuctionStates.value).length > 0) {
            for (const [id, prev] of Object.entries(trackedAuctionStates.value)) {
                if (id === viewingAuctionId) continue;
                const curr = newStates[id];
                if (!curr) continue;
                if (prev.in_active && !curr.in_active) {
                    if (curr.won) {
                        notify(`You won "${prev.title}"!`, "success", 10000);
                    } else {
                        notify(`Auction "${prev.title}" has ended — you didn't win.`, "info", 8000);
                    }
                } else if (prev.in_active && curr.in_active) {
                    if ((prev.won_quantity ?? 0) > 0 && (curr.won_quantity ?? 0) === 0) {
                        notify(`You've been overbid on "${prev.title}"!`, "warning", 8000);
                    }
                }
            }
        }

        trackedAuctionStates.value = newStates;
    } catch {
        // ignore
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

    if (pushSupported.value) {
        registerPushServiceWorker()
            .then(() => refreshPushSubscriptionState())
            .catch((error) => {
                notify(
                    error.message ?? "Couldn't start background notifications on this device.",
                    "error",
                    8000,
                );
            });
    }
});

watch(user, async (newUser) => {
    clearInterval(myAuctionsInterval);
    myAuctionsInterval = undefined;
    trackedAuctionStates.value = {};
    if (newUser && !newUser.is_admin) {
        pollMyAuctions();
        myAuctionsInterval = setInterval(pollMyAuctions, 30000);

        if (browserPermission.value === "granted") {
            try {
                await syncPushSubscription();
            } catch (error) {
                notify(
                    error.message ?? "Couldn't sync background notifications for this browser.",
                    "error",
                    8000,
                );
            }
        }
    } else if (newUser?.is_admin && browserPermission.value === "granted") {
        try {
            await clearPushSubscription();
        } catch (error) {
            notify(
                error.message ?? "Couldn't disable bidder notifications on this browser.",
                "warning",
                8000,
            );
        }
    }
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
    clearInterval(myAuctionsInterval);
    document.removeEventListener("visibilitychange", handleVisibilityChange);
});

async function logout() {
    if (browserPermission.value === "granted") {
        try {
            await clearPushSubscription();
        } catch (error) {
            notify(
                error.message ?? "Couldn't disable browser notifications on this device.",
                "warning",
                8000,
            );
        }
    }

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
provide("now", now);
provide("notify", notify);
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
                        v-if="notificationsSupported && user && !user.is_admin"
                        @click="handleNotificationBell"
                        :disabled="browserPermission === 'denied'"
                        class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:cursor-default"
                        :class="
                            pushEnabled
                                ? 'text-green-500 dark:text-green-400'
                                : 'text-gray-500 dark:text-gray-400'
                        "
                        :title="
                            pushEnabled
                                ? 'Browser notifications enabled'
                                : browserPermission === 'denied'
                                  ? 'Notifications blocked — enable in browser settings'
                                  : browserPermission === 'granted'
                                    ? 'Finish enabling browser notifications'
                                    : 'Enable browser notifications'
                        "
                    >
                        <svg
                            v-if="browserPermission !== 'denied'"
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                            />
                        </svg>
                        <svg
                            v-else
                            class="w-4 h-4 opacity-40"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                            />
                            <line x1="2" y1="2" x2="22" y2="22" stroke-linecap="round" />
                        </svg>
                    </button>
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
                            v-if="!user.is_admin"
                            to="/dashboard"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                            >My Bids</router-link
                        >
                        <router-link
                            v-if="user.is_admin"
                            to="/admin"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                            >Admin</router-link
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
        <div
            v-if="siteLocked && !user?.is_admin"
            class="border-l-4 border-orange-400 bg-orange-50 dark:bg-orange-900/30 dark:border-orange-600 p-6 text-center"
            :class="[shellWidthClass, 'mx-auto my-8 rounded-lg']"
        >
            <div class="flex flex-col items-center gap-3">
                <svg
                    class="w-10 h-10 text-orange-500 dark:text-orange-400"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                    />
                </svg>
                <h2 class="text-xl font-semibold text-orange-800 dark:text-orange-200">
                    Site Temporarily Closed
                </h2>
                <p class="text-orange-700 dark:text-orange-300">
                    {{
                        lockMessage ||
                        "The auction house is temporarily closed for maintenance. Please check back soon!"
                    }}
                </p>
            </div>
        </div>
        <div
            v-if="siteLocked && user?.is_admin"
            class="border-l-4 border-orange-400 bg-orange-50 dark:bg-orange-900/30 dark:border-orange-600 px-4 py-2 mb-4"
            :class="[shellWidthClass, 'mx-auto rounded']"
        >
            <span class="text-sm text-orange-700 dark:text-orange-300 font-medium"
                >Site is locked for non-admin users.</span
            >
        </div>
        <main v-if="!siteLocked || user?.is_admin" :class="[shellWidthClass, 'mx-auto px-4']">
            <router-view />
        </main>
    </div>
    <NotificationToast />
</template>
