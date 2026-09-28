<script setup lang="ts">
import { ref, computed, useId } from "vue";
import { useRouter } from "vue-router";
import { api, ApiError } from "../lib/api";
import { apiError } from "../lib/apiError";
import { injectUser, injectCurrencySymbol, injectNow } from "../lib/injection";
import type { Auction, Category } from "../lib/types";

const router = useRouter();
const user = injectUser();
const currencySymbol = injectCurrencySymbol();
const now = injectNow();
const priceLabel = computed(() => `Starting Price (${currencySymbol.value})`);
const categories = ref<Category[]>([]);
const title = ref("");
const description = ref("");
const startingPrice = ref("1.00");
const quantity = ref(1);
const maxPerBidder = ref(1);
const categoryId = ref<string | number>("");
const location = ref("");
const endsAt = ref("");
const imageFiles = ref<File[]>([]);
const imagePreviews = ref<string[]>([]);
const errors = ref<Record<string, string[]>>({});
const submitting = ref(false);

if (!user.value?.is_admin) {
    router.push("/");
}

api<{ categories: Category[] }>("/categories")
    .then((data) => {
        categories.value = data.categories;
    })
    .catch(() => {});

const d = new Date(now.value.getTime() + 7 * 86400000);
const pad = (n: number) => String(n).padStart(2, "0");
endsAt.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

function onFilesSelected(e: Event) {
    const input = e.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    for (const file of files) {
        imageFiles.value.push(file);
        imagePreviews.value.push(URL.createObjectURL(file));
    }
    input.value = "";
}

function removeImage(index: number) {
    URL.revokeObjectURL(imagePreviews.value[index]);
    imageFiles.value.splice(index, 1);
    imagePreviews.value.splice(index, 1);
}

async function submit() {
    errors.value = {};
    submitting.value = true;
    try {
        const data = await api<{ auction: Auction }>("/auctions", {
            method: "POST",
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

        if (imageFiles.value.length > 0) {
            const formData = new FormData();
            for (const file of imageFiles.value) {
                formData.append("images[]", file);
            }
            await api(`/auctions/${data.auction.id}/images`, {
                method: "POST",
                body: formData,
            });
        }

        router.push(`/auctions/${data.auction.id}`);
    } catch (e) {
        if (e instanceof ApiError && e.data.errors) {
            errors.value = e.data.errors;
        } else {
            errors.value = {
                general: [apiError(e) || "Failed to create auction."],
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
        <h1 class="text-2xl font-bold mb-4">Sell an Item</h1>
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
                <label :for="`${uid}-2`" class="block text-sm font-medium mb-1">Description</label>
                <textarea
                    :id="`${uid}-2`"
                    v-model="description"
                    required
                    rows="4"
                    class="w-full border rounded px-3 py-2"
                ></textarea>
                <p v-if="errors.description" class="text-red-600 dark:text-red-400 text-sm mt-1">
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
                <p v-if="errors.category_id" class="text-red-600 dark:text-red-400 text-sm mt-1">
                    {{ errors.category_id[0] }}
                </p>
            </div>
            <div>
                <label :for="`${uid}-5`" class="block text-sm font-medium mb-1">Images</label>
                <input
                    :id="`${uid}-5`"
                    type="file"
                    accept="image/*"
                    multiple
                    @change="onFilesSelected"
                    class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 dark:file:bg-blue-900/30 file:text-blue-700 dark:file:text-blue-400 hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50"
                />
                <p
                    v-if="errors['images'] || errors['images.0']"
                    class="text-red-600 dark:text-red-400 text-sm mt-1"
                >
                    {{ (errors["images"] || errors["images.0"])[0] }}
                </p>
                <div v-if="imagePreviews.length" class="mt-2 flex flex-wrap gap-2">
                    <div v-for="(src, i) in imagePreviews" :key="i" class="relative w-20 h-20">
                        <img
                            :src="src"
                            :alt="`Selected image ${i + 1}`"
                            class="w-full h-full object-cover rounded"
                        />
                        <button
                            type="button"
                            @click="removeImage(i)"
                            class="absolute -top-1 -right-1 bg-red-600 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center"
                        >
                            x
                        </button>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label :for="`${uid}-6`" class="block text-sm font-medium mb-1">{{
                        priceLabel
                    }}</label>
                    <input
                        :id="`${uid}-6`"
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
                    <label :for="`${uid}-7`" class="block text-sm font-medium mb-1">Ends At</label>
                    <input
                        :id="`${uid}-7`"
                        v-model="endsAt"
                        type="datetime-local"
                        required
                        class="w-full border rounded px-3 py-2"
                    />
                    <p v-if="errors.ends_at" class="text-red-600 dark:text-red-400 text-sm mt-1">
                        {{ errors.ends_at[0] }}
                    </p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label :for="`${uid}-8`" class="block text-sm font-medium mb-1"
                        >Total Quantity</label
                    >
                    <input
                        :id="`${uid}-8`"
                        v-model="quantity"
                        type="number"
                        min="1"
                        required
                        class="w-full border rounded px-3 py-2"
                    />
                    <p v-if="errors.quantity" class="text-red-600 dark:text-red-400 text-sm mt-1">
                        {{ errors.quantity[0] }}
                    </p>
                </div>
                <div>
                    <label :for="`${uid}-9`" class="block text-sm font-medium mb-1"
                        >Max per Bidder</label
                    >
                    <input
                        :id="`${uid}-9`"
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
            <button
                type="submit"
                :disabled="submitting"
                class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 disabled:opacity-50"
            >
                {{ submitting ? "Creating..." : "Create Auction" }}
            </button>
        </form>
    </div>
</template>
