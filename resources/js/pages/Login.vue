<script setup>
import { ref, inject, onMounted } from 'vue';
import { api } from '../api.js';

const onLogin = inject('onLogin');
const username = ref('');
const password = ref('');
const error = ref('');
const ssoEnabled = ref(false);

onMounted(async () => {
    try {
        const data = await api('/auth/sso/enabled');
        ssoEnabled.value = data.enabled;
    } catch (e) {
        // Fallback to false
    }
});

async function submit() {
    error.value = '';
    try {
        const data = await api('/login', {
            method: 'POST',
            body: JSON.stringify({ username: username.value, password: password.value }),
        });
        onLogin(data.user);
    } catch (e) {
        error.value = e.data?.message || 'Login failed.';
    }
}
</script>

<template>
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-bold mb-4">Login</h1>
        <div v-if="error" class="bg-red-100 text-red-700 p-3 rounded mb-4">{{ error }}</div>

        <div v-if="ssoEnabled" class="space-y-4">
            <p class="text-gray-600">Microsoft SSO is required for this site.</p>
            <a href="/auth/microsoft/redirect" class="w-full flex items-center justify-center gap-2 bg-gray-100 border text-gray-700 py-2 rounded hover:bg-gray-200">
                <svg class="w-5 h-5" viewBox="0 0 23 23"><path fill="#f3f3f3" d="M0 0h23v23H0z"/><path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/></svg>
                Login with Microsoft
            </a>
        </div>

        <form v-else @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Username</label>
                <input v-model="username" type="text" required
                    class="w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Password</label>
                <input v-model="password" type="password" required
                    class="w-full border rounded px-3 py-2" />
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                Login
            </button>
        </form>
        <p v-if="!ssoEnabled" class="mt-4 text-center text-sm text-gray-600">
            Don't have an account? <router-link to="/register" class="text-blue-600 hover:underline">Register</router-link>
        </p>
    </div>
</template>
