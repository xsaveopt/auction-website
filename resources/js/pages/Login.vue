<script setup>
import { ref, inject } from 'vue';
import { api } from '../api.js';

const onLogin = inject('onLogin');
const username = ref('');
const password = ref('');
const error = ref('');

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
        <form @submit.prevent="submit" class="space-y-4">
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
        <p class="mt-4 text-center text-sm text-gray-600">
            Don't have an account? <router-link to="/register" class="text-blue-600 hover:underline">Register</router-link>
        </p>
    </div>
</template>
