<script setup>
defineProps({
    title: { type: String, default: "" },
    message: { type: String, required: true },
    confirmLabel: { type: String, default: "Confirm" },
    danger: { type: Boolean, default: false },
});
defineEmits(["confirm", "cancel"]);
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div
            class="bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 max-w-sm w-full mx-4 border border-gray-200 dark:border-gray-700"
        >
            <h3 v-if="title" class="font-semibold text-gray-900 dark:text-gray-100 mb-2">
                {{ title }}
            </h3>
            <p
                class="text-sm text-gray-600 dark:text-gray-400"
                :class="title ? 'mb-5' : 'mb-5 mt-1'"
            >
                {{ message }}
            </p>
            <div class="flex gap-3 justify-end">
                <button
                    class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                    @click="$emit('cancel')"
                >
                    Cancel
                </button>
                <button
                    class="px-4 py-2 text-sm font-medium text-white rounded-lg"
                    :class="
                        danger ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'
                    "
                    @click="$emit('confirm')"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
