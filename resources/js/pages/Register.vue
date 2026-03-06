<script setup>
import { ref, inject } from 'vue';
import { api } from '../api.js';

const onLogin = inject('onLogin');
const username = ref('');
const password = ref('');
const errors = ref({});

async function submit() {
    errors.value = {};
    try {
        const data = await api('/register', {
            method: 'POST',
            body: JSON.stringify({ username: username.value, password: password.value }),
        });
        onLogin(data.user);
    } catch (e) {
        if (e.data?.errors) {
            errors.value = e.data.errors;
        } else {
            errors.value = { general: [e.data?.message || 'Registration failed.'] };
        }
    }
}
</script>

<template>
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-bold mb-4">Register</h1>
        <div v-if="errors.general" class="bg-red-100 text-red-700 p-3 rounded mb-4">
            {{ errors.general[0] }}
        </div>
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Username</label>
                <input v-model="username" type="text" required
                    class="w-full border rounded px-3 py-2" />
                <p v-if="errors.username" class="text-red-600 text-sm mt-1">{{ errors.username[0] }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Password</label>
                <input v-model="password" type="password" required
                    class="w-full border rounded px-3 py-2" />
                <p v-if="errors.password" class="text-red-600 text-sm mt-1">{{ errors.password[0] }}</p>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                Register
            </button>
        </form>
        <p class="mt-4 text-center text-sm text-gray-600">
            Already have an account? <router-link to="/login" class="text-blue-600 hover:underline">Login</router-link>
        </p>
    </div>
</template>
