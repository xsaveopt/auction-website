import { computed, ref, onMounted, onUnmounted, provide, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "../api";
import { HEARTBEAT_INTERVAL_MS, presencePayload } from "../presence";
import { usePushNotifications } from "../pushNotifications";
import { useTheme } from "../useTheme";
import { useNotifications } from "../useNotifications";
import type { Auction, CurrentRound, HeartbeatData, Schedule, User } from "../types";

interface RawSchedule extends Schedule {
    server_time?: string;
    server_time_local?: string;
    currency_symbol?: string;
    site_locked?: boolean;
    lock_message?: string | null;
}

interface ScheduleBar {
    open: boolean;
    percent: number;
    label: string;
}

interface TrackedAuctionState {
    title: string;
    in_active: boolean;
    won_quantity?: number;
    won?: boolean;
}

function errorMessage(error: unknown): string | undefined {
    return error instanceof Error ? error.message : undefined;
}

export function useAppShell() {
    const { isDark, toggleTheme } = useTheme();
    const { notify } = useNotifications();
    const {
        pushSupported,
        pushEnabled,
        pushStateKnown,
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
            notify(errorMessage(error) ?? "Couldn't enable browser notifications.", "error", 8000);
        }
    }

    const route = useRoute();
    const router = useRouter();
    const user = ref<User | null>(null);
    const loading = ref(true);
    const rawSchedule = ref<RawSchedule | null>(null);
    const ssoEnabled = ref(false);
    const currencySymbol = ref("$");
    const now = ref(new Date());
    const heartbeatData = ref<HeartbeatData | null>(null);
    const siteLocked = ref(false);
    const lockMessage = ref<string | null>(null);
    const currentRound = ref<CurrentRound>({ active: null, ended: [] });
    let serverOffsetMs = 0;
    const serverClockSeconds = ref(0);

    function parseTime(str: string) {
        const [h, m] = str.split(":").map(Number);
        return h * 60 + m;
    }

    function isWeekend(date: Date) {
        const day = date.getDay();
        return day === 0 || day === 6;
    }

    function isBiddingOpenNow(sched: RawSchedule | null, date: Date) {
        if (!sched) return true;
        if (sched.weekends_open && isWeekend(date)) return true;
        const current = date.getHours() * 60 + date.getMinutes();
        return current < parseTime(sched.closed_start) || current >= parseTime(sched.closed_end);
    }

    function currentFractionalMinutes(date: Date) {
        return date.getHours() * 60 + date.getMinutes() + date.getSeconds() / 60;
    }

    function formatRemaining(minutes: number) {
        const total = Math.ceil(minutes);
        if (total < 60) return `${total}m`;
        const h = Math.floor(total / 60);
        const m = total % 60;
        if (h < 24) return `${h}h ${m}m`;
        const d = Math.floor(h / 24);
        return `${d}d ${h % 24}h`;
    }

    const schedule = computed<Schedule | null>(() => {
        if (!rawSchedule.value) return null;
        return {
            ...rawSchedule.value,
            is_open: isBiddingOpenNow(rawSchedule.value, now.value),
        };
    });

    const scheduleBar = computed<ScheduleBar | null>(() => {
        if (!rawSchedule.value || !rawSchedule.value.enabled) return null;
        const date = now.value;
        const day = date.getDay();
        const current = currentFractionalMinutes(date);
        const start = parseTime(rawSchedule.value.closed_start);
        const end = parseTime(rawSchedule.value.closed_end);
        const isOpen = schedule.value?.is_open;
        const weekendsOpen = rawSchedule.value.weekends_open;

        if (!isOpen) {
            const total = end - start;
            const elapsed = current - start;
            const remaining = end - current;
            return {
                open: false,
                percent: (elapsed / total) * 100,
                label: `Bidding opens in ${formatRemaining(remaining)}`,
            };
        }

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

        if (current < start) {
            const remaining = start - current;

            if (day === 1 && weekendsOpen) {
                const total = 1440 - end + 2 * 1440 + start;
                const elapsed = 1440 - end + 2 * 1440 + current;
                return {
                    open: true,
                    percent: (elapsed / total) * 100,
                    label: `Bidding closes in ${formatRemaining(remaining)}`,
                };
            }

            const total = 1440 - end + start;
            const elapsed = 1440 - end + current;
            return {
                open: true,
                percent: (elapsed / total) * 100,
                label: `Bidding closes in ${formatRemaining(remaining)}`,
            };
        }

        const isFriday = day === 5;
        if (isFriday && weekendsOpen) {
            const total = 1440 - end + 2 * 1440 + start;
            const elapsed = current - end;
            const remaining = total - elapsed;
            return {
                open: true,
                percent: (elapsed / total) * 100,
                label: `Bidding open for the weekend · closes in ${formatRemaining(remaining)}`,
            };
        }

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
            const data = await api<{ user: User | null }>("/user");
            user.value = data.user;
        } catch {
            user.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function fetchSchedule() {
        try {
            const data = await api<{ schedule: RawSchedule | null }>("/schedule");
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
        } catch {}
    }

    async function fetchSsoEnabled() {
        try {
            const data = await api<{ enabled: boolean }>("/auth/sso/enabled");
            ssoEnabled.value = !!data.enabled;
        } catch {
            ssoEnabled.value = false;
        }
    }

    async function fetchCurrentRound() {
        try {
            const data = await api<CurrentRound>("/rounds/current");
            currentRound.value = data;
        } catch {}
    }

    async function sendPresenceHeartbeat() {
        try {
            const data = await api<HeartbeatData>("/presence/heartbeat", {
                method: "POST",
                body: JSON.stringify(presencePayload(route)),
            });
            heartbeatData.value = data;
        } catch {}
    }

    const trackedAuctionStates = ref<Record<string, TrackedAuctionState>>({});
    let myAuctionsInterval: ReturnType<typeof setInterval> | undefined;

    async function pollMyAuctions() {
        if (!user.value || user.value.is_admin) return;
        const viewingAuctionId = route.params.id ? String(route.params.id) : null;
        try {
            const data = await api<{ active?: Auction[]; won?: Auction[]; lost?: Auction[] }>(
                "/my-auctions",
            );
            const newStates: Record<string, TrackedAuctionState> = {};
            for (const auction of data.active ?? []) {
                const myBid = auction.bids?.find((b) => b.user?.id === user.value?.id);
                newStates[String(auction.id)] = {
                    title: auction.title,
                    in_active: true,
                    won_quantity: myBid?.won_quantity ?? 0,
                };
            }
            for (const auction of data.won ?? []) {
                newStates[String(auction.id)] = {
                    title: auction.title,
                    in_active: false,
                    won: true,
                };
            }
            for (const auction of data.lost ?? []) {
                newStates[String(auction.id)] = {
                    title: auction.title,
                    in_active: false,
                    won: false,
                };
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
                            notify(
                                `Auction "${prev.title}" has ended — you didn't win.`,
                                "info",
                                8000,
                            );
                        }
                    } else if (prev.in_active && curr.in_active) {
                        if ((prev.won_quantity ?? 0) > 0 && (curr.won_quantity ?? 0) === 0) {
                            notify(`You've been overbid on "${prev.title}"!`, "warning", 8000);
                        }
                    }
                }
            }

            trackedAuctionStates.value = newStates;
        } catch {}
    }

    let scheduleInterval: ReturnType<typeof setInterval> | undefined;
    let presenceInterval: ReturnType<typeof setInterval> | null = null;
    let clockInterval: ReturnType<typeof setInterval> | undefined;
    function startHeartbeat() {
        if (!presenceInterval) {
            sendPresenceHeartbeat();
            presenceInterval = setInterval(sendPresenceHeartbeat, HEARTBEAT_INTERVAL_MS);
        }
    }

    function stopHeartbeat() {
        if (presenceInterval) clearInterval(presenceInterval);
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
        fetchCurrentRound();
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
                        errorMessage(error) ??
                            "Couldn't start background notifications on this device.",
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
                        errorMessage(error) ??
                            "Couldn't sync background notifications for this browser.",
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
                    errorMessage(error) ?? "Couldn't disable bidder notifications on this browser.",
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
                    errorMessage(error) ?? "Couldn't disable browser notifications on this device.",
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

    function onLogin(u: User) {
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
    provide("currentRound", currentRound);

    return {
        isDark,
        toggleTheme,
        pushEnabled,
        pushStateKnown,
        browserPermission,
        notificationsSupported,
        handleNotificationBell,
        user,
        loading,
        ssoEnabled,
        siteLocked,
        lockMessage,
        scheduleBar,
        serverClock,
        shellWidthClass,
        logout,
    };
}
