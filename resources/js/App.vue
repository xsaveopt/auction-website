<script setup lang="ts">
import NotificationToast from "./NotificationToast.vue";
import { useAppShell } from "./composables/useAppShell";

const {
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
} = useAppShell();
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
                    <!-- Notifications enabled: subtle icon-only bell -->
                    <button
                        v-if="
                            notificationsSupported &&
                            user &&
                            !user.is_admin &&
                            pushStateKnown &&
                            pushEnabled
                        "
                        @click="handleNotificationBell"
                        class="p-1.5 rounded-lg text-green-500 dark:text-green-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Browser notifications enabled"
                    >
                        <svg
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
                    </button>
                    <!-- Notifications blocked: muted icon with tooltip -->
                    <button
                        v-else-if="
                            notificationsSupported &&
                            user &&
                            !user.is_admin &&
                            pushStateKnown &&
                            browserPermission === 'denied'
                        "
                        disabled
                        class="p-1.5 rounded-lg text-gray-400 dark:text-gray-600 cursor-default"
                        title="Notifications blocked — enable in browser settings"
                    >
                        <svg
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
                    <!-- Notifications not yet enabled: prominent button with text -->
                    <button
                        v-else-if="
                            notificationsSupported && user && !user.is_admin && pushStateKnown
                        "
                        @click="handleNotificationBell"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition-colors"
                        title="Enable push notifications"
                    >
                        <svg
                            class="w-4 h-4 shrink-0"
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
                        <span class="hidden sm:inline">Enable notifications</span>
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
                            to="/admin/results"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                            >Admin</router-link
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
