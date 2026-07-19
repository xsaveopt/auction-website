<script setup lang="ts">
import { ref, onMounted } from "vue";
import { api, ApiError } from "../api";
import { injectOnLogin } from "../injection";
import type { User } from "../types";

const onLogin = injectOnLogin();
const username = ref("");
const password = ref("");
const errors = ref<Record<string, string[]>>({});
const ssoEnabled = ref(false);

onMounted(async () => {
    try {
        const data = await api<{ enabled: boolean }>("/auth/sso/enabled");
        ssoEnabled.value = data.enabled;
    } catch {
        ssoEnabled.value = false;
    }
});

async function submit() {
    errors.value = {};
    try {
        const data = await api<{ user: User }>("/register", {
            method: "POST",
            body: JSON.stringify({
                username: username.value,
                password: password.value,
            }),
        });
        onLogin(data.user);
    } catch (e) {
        if (e instanceof ApiError && e.data.errors) {
            errors.value = e.data.errors;
        } else {
            errors.value = {
                general: [(e instanceof ApiError && e.data.message) || "Registration failed."],
            };
        }
    }
}
</script>

<template>
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-bold mb-4">Register</h1>
        <div
            v-if="errors.general"
            class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-3 rounded mb-4"
        >
            {{ errors.general[0] }}
        </div>

        <div v-if="ssoEnabled" class="space-y-4">
            <p class="text-gray-600 dark:text-gray-400">
                Microsoft SSO is required for account creation.
            </p>
            <a
                href="/auth/microsoft/redirect"
                class="w-full flex items-center justify-center gap-2 bg-gray-100 dark:bg-gray-700 border dark:border-gray-600 text-gray-700 dark:text-gray-200 py-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"
            >
                <svg class="w-5 h-5" viewBox="0 0 23 23">
                    <path fill="#f3f3f3" d="M0 0h23v23H0z" />
                    <path fill="#f35325" d="M1 1h10v10H1z" />
                    <path fill="#81bc06" d="M12 1h10v10H12z" />
                    <path fill="#05a6f0" d="M1 12h10v10H1z" />
                    <path fill="#ffba08" d="M12 12h10v10H12z" />
                </svg>
                Sign up with Microsoft
            </a>
            <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                Already have an account?
                <router-link to="/login" class="text-blue-600 dark:text-blue-400 hover:underline"
                    >Login</router-link
                >
            </p>
        </div>

        <form v-else @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Username</label>
                <input
                    v-model="username"
                    type="text"
                    required
                    class="w-full border rounded px-3 py-2"
                />
                <p v-if="errors.username" class="text-red-600 dark:text-red-400 text-sm mt-1">
                    {{ errors.username[0] }}
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Password</label>
                <input
                    v-model="password"
                    type="password"
                    required
                    class="w-full border rounded px-3 py-2"
                />
                <p v-if="errors.password" class="text-red-600 dark:text-red-400 text-sm mt-1">
                    {{ errors.password[0] }}
                </p>
            </div>
            <button
                type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700"
            >
                Register
            </button>
        </form>
        <p v-if="!ssoEnabled" class="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">
            Already have an account?
            <router-link to="/login" class="text-blue-600 dark:text-blue-400 hover:underline"
                >Login</router-link
            >
        </p>
    </div>
</template>
