<script setup lang="ts">
import { ref, computed, onMounted, useId } from "vue";
import { useRouter } from "vue-router";
import { api, ApiError } from "../lib/api";
import { apiError } from "../lib/apiError";
import { injectUser, injectCurrencySymbol } from "../lib/injection";
import type { Auction, Category } from "../lib/types";

const router = useRouter();
const user = injectUser();
const currencySymbol = injectCurrencySymbol();
const priceLabel = computed(() => `Starting Price (${currencySymbol.value})`);
const props = defineProps<{ id?: string }>();

const categories = ref<Category[]>([]);
const title = ref("");
const description = ref("");
const startingPrice = ref("");
const quantity = ref(1);
const maxPerBidder = ref(1);
const categoryId = ref<string | number>("");
const location = ref("");
const endsAt = ref("");
const errors = ref<Record<string, string[]>>({});
const submitting = ref(false);
const loading = ref(true);

onMounted(async () => {
    if (!user.value?.is_admin) {
        router.push("/");
        return;
    }
    try {
        const [data, catData] = await Promise.all([
            api<{ auction: Auction }>(`/auctions/${props.id}`),
            api<{ categories: Category[] }>("/categories").catch(() => ({ categories: [] })),
        ]);
        categories.value = catData.categories;
        const a = data.auction;
        title.value = a.title;
        description.value = a.description ?? "";
        startingPrice.value = Number(a.starting_price).toFixed(2);
        quantity.value = a.quantity;
        maxPerBidder.value = a.max_per_bidder ?? 1;
        categoryId.value = a.category_id || "";
        location.value = a.location || "";
        endsAt.value = a.ends_at?.slice(0, 16) ?? "";
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
                category_id: categoryId.value ? Number(categoryId.value) : null,
                location: location.value || null,
                ends_at: endsAt.value,
            }),
        });
        router.push(`/auctions/${props.id}`);
    } catch (e) {
        if (e instanceof ApiError && e.data.errors) {
            errors.value = e.data.errors;
        } else {
            errors.value = {
                general: [apiError(e) || "Failed to update auction."],
            };
        }
    } finally {
        submitting.value = false;
    }
}

const uid = useId();
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
                    <label :for="`${uid}-1`" class="block text-sm font-medium mb-1">Title</label>
                    <input
                        :id="`${uid}-1`"
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
                    <label :for="`${uid}-2`" class="block text-sm font-medium mb-1"
                        >Description</label
                    >
                    <textarea
                        :id="`${uid}-2`"
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
                <div>
                    <label :for="`${uid}-3`" class="block text-sm font-medium mb-1"
                        >Pickup Location</label
                    >
                    <input
                        :id="`${uid}-3`"
                        v-model="location"
                        type="text"
                        placeholder="e.g. Warehouse A, 123 Main St"
                        class="w-full border rounded px-3 py-2"
                    />
                    <p v-if="errors.location" class="text-red-600 dark:text-red-400 text-sm mt-1">
                        {{ errors.location[0] }}
                    </p>
                </div>
                <div v-if="categories.length > 0">
                    <label :for="`${uid}-4`" class="block text-sm font-medium mb-1">Category</label>
                    <select
                        :id="`${uid}-4`"
                        v-model="categoryId"
                        class="w-full border rounded px-3 py-2"
                    >
                        <option value="">No category</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                    <p
                        v-if="errors.category_id"
                        class="text-red-600 dark:text-red-400 text-sm mt-1"
                    >
                        {{ errors.category_id[0] }}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label :for="`${uid}-5`" class="block text-sm font-medium mb-1">{{
                            priceLabel
                        }}</label>
                        <input
                            :id="`${uid}-5`"
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
                        <label :for="`${uid}-6`" class="block text-sm font-medium mb-1"
                            >Ends At</label
                        >
                        <input
                            :id="`${uid}-6`"
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
                        <label :for="`${uid}-7`" class="block text-sm font-medium mb-1"
                            >Total Quantity</label
                        >
                        <input
                            :id="`${uid}-7`"
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
                        <label :for="`${uid}-8`" class="block text-sm font-medium mb-1"
                            >Max per Bidder</label
                        >
                        <input
                            :id="`${uid}-8`"
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
