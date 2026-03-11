<script setup>
import { ref, inject, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const user = inject("user");
const currencySymbol = inject("currencySymbol");
const priceLabel = computed(() => `Starting Price (${currencySymbol.value})`);
const props = defineProps({ id: String });

const title = ref("");
const description = ref("");
const startingPrice = ref("");
const quantity = ref(1);
const maxPerBidder = ref(1);
const endsAt = ref("");
const errors = ref({});
const submitting = ref(false);
const loading = ref(true);

onMounted(async () => {
    if (!user.value?.is_admin) {
        router.push("/");
        return;
    }
    try {
        const data = await api(`/auctions/${props.id}`);
        const a = data.auction;
        title.value = a.title;
        description.value = a.description;
        startingPrice.value = Number(a.starting_price).toFixed(2);
        quantity.value = a.quantity;
        maxPerBidder.value = a.max_per_bidder;
        endsAt.value = new Date(a.ends_at).toISOString().slice(0, 16);
    } catch {
        errors.value = { general: ["Failed to load auction."] };
    } finally {
        loading.value = false;
    }
});

async function submit() {
    errors.value = {};
    submitting.value = true;
    try {
        await api(`/auctions/${props.id}`, {
            method: "PUT",
            body: JSON.stringify({
                title: title.value,
                description: description.value,
                starting_price: Number(startingPrice.value),
                quantity: Number(quantity.value),
                max_per_bidder: Number(maxPerBidder.value),
                ends_at: new Date(endsAt.value).toISOString(),
            }),
        });
        router.push(`/auctions/${props.id}`);
    } catch (e) {
        if (e.data?.errors) {
            errors.value = e.data.errors;
        } else {
            errors.value = {
                general: [e.data?.message || "Failed to update auction."],
            };
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="max-w-lg mx-auto">
        <h1 class="text-2xl font-bold mb-4">Edit Auction</h1>
        <div v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</div>
        <template v-else>
            <div
                v-if="errors.general"
                class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-3 rounded mb-4"
            >
                {{ errors.general[0] }}
            </div>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Title</label>
                    <input
                        v-model="title"
                        type="text"
                        required
                        class="w-full border rounded px-3 py-2"
                    />
                    <p v-if="errors.title" class="text-red-600 dark:text-red-400 text-sm mt-1">
                        {{ errors.title[0] }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea
                        v-model="description"
                        required
                        rows="4"
                        class="w-full border rounded px-3 py-2"
                    ></textarea>
                    <p
                        v-if="errors.description"
                        class="text-red-600 dark:text-red-400 text-sm mt-1"
                    >
                        {{ errors.description[0] }}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ priceLabel }}</label>
                        <input
                            v-model="startingPrice"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            class="w-full border rounded px-3 py-2"
                        />
                        <p
                            v-if="errors.starting_price"
                            class="text-red-600 dark:text-red-400 text-sm mt-1"
                        >
                            {{ errors.starting_price[0] }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Ends At</label>
                        <input
                            v-model="endsAt"
                            type="datetime-local"
                            required
                            class="w-full border rounded px-3 py-2"
                        />
                        <p
                            v-if="errors.ends_at"
                            class="text-red-600 dark:text-red-400 text-sm mt-1"
                        >
                            {{ errors.ends_at[0] }}
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Total Quantity</label>
                        <input
                            v-model="quantity"
                            type="number"
                            min="1"
                            required
                            class="w-full border rounded px-3 py-2"
                        />
                        <p
                            v-if="errors.quantity"
                            class="text-red-600 dark:text-red-400 text-sm mt-1"
                        >
                            {{ errors.quantity[0] }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Max per Bidder</label>
                        <input
                            v-model="maxPerBidder"
                            type="number"
                            min="1"
                            :max="quantity"
                            required
                            class="w-full border rounded px-3 py-2"
                        />
                        <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">
                            How many one person can win
                        </p>
                        <p
                            v-if="errors.max_per_bidder"
                            class="text-red-600 dark:text-red-400 text-sm mt-1"
                        >
                            {{ errors.max_per_bidder[0] }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="flex-1 bg-blue-600 text-white py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ submitting ? "Saving..." : "Save Changes" }}
                    </button>
                    <router-link
                        :to="`/auctions/${id}`"
                        class="px-4 py-2 border dark:border-gray-600 rounded text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 text-center"
                    >
                        Cancel
                    </router-link>
                </div>
            </form>
        </template>
    </div>
</template>
