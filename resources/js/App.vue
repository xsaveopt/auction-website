<script setup>
import { ref, onMounted, onUnmounted, provide } from 'vue';
import { useRouter } from 'vue-router';
import { api } from './api.js';

const router = useRouter();
const user = ref(null);
const loading = ref(true);
const schedule = ref(null);
const ssoEnabled = ref(false);

async function fetchUser() {
    try {
        const data = await api('/user');
        user.value = data.user;
    } catch {
        user.value = null;
    } finally {
        loading.value = false;
    }
}

async function fetchSchedule() {
    try {
        const data = await api('/schedule');
        schedule.value = data.schedule;
    } catch {
        // ignore
    }
}

async function fetchSsoEnabled() {
    try {
        const data = await api('/auth/sso/enabled');
        ssoEnabled.value = !!data.enabled;
    } catch {
        ssoEnabled.value = false;
    }
}

// Poll schedule every 60s to keep is_open in sync
let scheduleInterval;
onMounted(() => {
    fetchUser();
    fetchSchedule();
    fetchSsoEnabled();
    scheduleInterval = setInterval(fetchSchedule, 60000);
});
onUnmounted(() => clearInterval(scheduleInterval));

async function logout() {
    await api('/logout', { method: 'POST' });
    user.value = null;
    router.push('/');
}

function onLogin(u) {
    user.value = u;
    router.push('/');
}

provide('user', user);
provide('onLogin', onLogin);
provide('schedule', schedule);
</script>

<template>
    <div v-if="!loading">
        <nav class="bg-white shadow mb-6">
            <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
                <router-link to="/" class="text-xl font-bold text-gray-800">Auction House</router-link>
                <div class="flex items-center gap-4">
                    <span v-if="schedule && !schedule.is_open"
                        class="text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded">
                        Bidding closed ({{ schedule.closed_start }} – {{ schedule.closed_end }})
                    </span>
                    <span v-else-if="schedule"
                        class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">
                        Bidding open
                    </span>
                    <template v-if="user">
                        <span class="text-gray-600">{{ user.username }}</span>
                        <router-link v-if="user.is_admin" to="/admin/results" class="text-blue-600 hover:underline">Results</router-link>
                        <router-link v-if="user.is_admin" to="/auctions/new" class="text-blue-600 hover:underline">Sell Item</router-link>
                        <button @click="logout" class="text-red-600 hover:underline">Logout</button>
                    </template>
                    <template v-else>
                        <a v-if="ssoEnabled" href="/auth/microsoft/redirect" class="text-blue-600 hover:underline">Login with Microsoft</a>
                        <template v-else>
                            <router-link to="/login" class="text-blue-600 hover:underline">Login</router-link>
                            <router-link to="/register" class="text-blue-600 hover:underline">Register</router-link>
                        </template>
                    </template>
                </div>
            </div>
        </nav>
        <main class="max-w-4xl mx-auto px-4">
            <router-view />
        </main>
    </div>
</template>
