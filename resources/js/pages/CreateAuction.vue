<script setup>
import { ref, inject, computed } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const user = inject("user");
const currencySymbol = inject("currencySymbol");
const priceLabel = computed(() => `Starting Price (${currencySymbol.value})`);
const title = ref("");
const description = ref("");
const startingPrice = ref("1.00");
const quantity = ref(1);
const maxPerBidder = ref(1);
const endsAt = ref("");
const imageFiles = ref([]);
const imagePreviews = ref([]);
const errors = ref({});
const submitting = ref(false);

if (!user.value) {
    router.push("/login");
}

const d = new Date(Date.now() + 7 * 86400000);
endsAt.value = d.toISOString().slice(0, 16);

function onFilesSelected(e) {
    const files = Array.from(e.target.files);
    for (const file of files) {
        imageFiles.value.push(file);
        imagePreviews.value.push(URL.createObjectURL(file));
    }
    e.target.value = "";
}

function removeImage(index) {
    URL.revokeObjectURL(imagePreviews.value[index]);
    imageFiles.value.splice(index, 1);
    imagePreviews.value.splice(index, 1);
}

async function submit() {
    errors.value = {};
    submitting.value = true;
    try {
        const data = await api("/auctions", {
            method: "POST",
            body: JSON.stringify({
                title: title.value,
                description: description.value,
                starting_price: Number(startingPrice.value),
                quantity: Number(quantity.value),
                max_per_bidder: Number(maxPerBidder.value),
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
        if (e.data?.errors) {
            errors.value = e.data.errors;
        } else {
            errors.value = {
                general: [e.data?.message || "Failed to create auction."],
            };
        }
    } finally {
        submitting.value = false;
    }
}
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
                <p v-if="errors.description" class="text-red-600 dark:text-red-400 text-sm mt-1">
                    {{ errors.description[0] }}
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Images</label>
                <input
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
                        <img :src="src" class="w-full h-full object-cover rounded" />
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
                    <p v-if="errors.ends_at" class="text-red-600 dark:text-red-400 text-sm mt-1">
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
                    <p v-if="errors.quantity" class="text-red-600 dark:text-red-400 text-sm mt-1">
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
