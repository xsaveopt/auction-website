<script setup lang="ts">
defineProps<{ status: string; size: "sm" | "md" }>();
</script>

<template>
    <span
        class="inline-flex rounded-full font-semibold capitalize"
        :class="[
            size === 'md' ? 'px-3 py-1 text-xs' : 'px-2 py-0.5 text-[11px]',
            {
                'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300':
                    status === 'pending',
                'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300':
                    status === 'accepted',
                'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300':
                    status === 'rejected',
            },
        ]"
    >
        {{ status }}
    </span>
</template>
