<script setup>
import { useNotifications } from "./useNotifications.js";

const { notifications, dismiss } = useNotifications();
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 w-80 max-w-[calc(100vw-2rem)] pointer-events-none"
        >
            <TransitionGroup name="toast">
                <div
                    v-for="n in notifications"
                    :key="n.id"
                    class="pointer-events-auto flex items-start gap-3 border rounded-lg px-4 py-3 shadow-lg"
                    :class="{
                        'bg-green-50 dark:bg-green-900/50 border-green-300 dark:border-green-700 text-green-800 dark:text-green-200':
                            n.type === 'success',
                        'bg-orange-50 dark:bg-orange-900/50 border-orange-300 dark:border-orange-700 text-orange-800 dark:text-orange-200':
                            n.type === 'warning',
                        'bg-red-50 dark:bg-red-900/50 border-red-300 dark:border-red-700 text-red-800 dark:text-red-200':
                            n.type === 'error',
                        'bg-blue-50 dark:bg-blue-900/50 border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-200':
                            !['success', 'warning', 'error'].includes(n.type),
                    }"
                >
                    <span class="flex-shrink-0 mt-0.5">
                        <svg
                            v-if="n.type === 'success'"
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        <svg
                            v-else-if="n.type === 'warning'"
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                            />
                        </svg>
                        <svg
                            v-else-if="n.type === 'error'"
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                        <svg
                            v-else
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </span>
                    <p class="flex-1 text-sm leading-snug">{{ n.message }}</p>
                    <button
                        @click="dismiss(n.id)"
                        class="flex-shrink-0 opacity-50 hover:opacity-100 transition-opacity"
                        aria-label="Dismiss"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-from {
    opacity: 0;
    transform: translateY(16px);
}
.toast-enter-active {
    transition: all 0.3s ease-out;
}
.toast-leave-active {
    transition: all 0.25s ease-in;
    position: absolute;
    right: 0;
    width: 100%;
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(40px);
}
.toast-move {
    transition: transform 0.3s ease;
}
</style>
