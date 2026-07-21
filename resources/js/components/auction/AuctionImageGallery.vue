<script setup lang="ts">
import type { AuctionImage } from "../../types";

defineProps<{ images: AuctionImage[]; title: string }>();
const activeImage = defineModel<number>("activeImage", { required: true });
</script>

<template>
    <div v-if="images.length" class="mt-4">
        <img
            :src="images[activeImage].url"
            :alt="title"
            class="w-full max-h-96 object-contain rounded bg-gray-50 dark:bg-gray-700"
        />
        <div v-if="images.length > 1" class="flex gap-2 mt-2">
            <button
                v-for="(img, i) in images"
                :key="img.id"
                @click="activeImage = i"
                class="w-16 h-16 rounded overflow-hidden border-2"
                :class="
                    i === activeImage
                        ? 'border-blue-500'
                        : 'border-transparent opacity-60 hover:opacity-100'
                "
            >
                <img :src="img.url" class="w-full h-full object-cover" />
            </button>
        </div>
    </div>
</template>
