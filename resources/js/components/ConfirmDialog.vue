<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from "vue";

withDefaults(
    defineProps<{
        title?: string;
        message: string;
        confirmLabel?: string;
        danger?: boolean;
    }>(),
    { title: "", confirmLabel: "Confirm", danger: false },
);
const emit = defineEmits<{ confirm: []; cancel: [] }>();

const uid = useId();
const dialog = ref<HTMLElement | null>(null);
const cancelButton = ref<HTMLButtonElement | null>(null);
let previouslyFocused: HTMLElement | null = null;

function focusableElements(): HTMLElement[] {
    return Array.from(dialog.value?.querySelectorAll<HTMLElement>("button:not([disabled])") ?? []);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === "Escape") {
        event.preventDefault();
        emit("cancel");
        return;
    }

    if (event.key !== "Tab") return;

    const elements = focusableElements();
    if (elements.length === 0) return;

    const first = elements[0];
    const last = elements[elements.length - 1];
    const active = document.activeElement;

    if (event.shiftKey && (active === first || !dialog.value?.contains(active))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && (active === last || !dialog.value?.contains(active))) {
        event.preventDefault();
        first.focus();
    }
}

onMounted(async () => {
    previouslyFocused =
        document.activeElement instanceof HTMLElement ? document.activeElement : null;
    document.addEventListener("keydown", onKeydown);
    await nextTick();
    cancelButton.value?.focus();
});

onBeforeUnmount(() => {
    document.removeEventListener("keydown", onKeydown);
    previouslyFocused?.focus();
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div
            ref="dialog"
            role="alertdialog"
            aria-modal="true"
            :aria-labelledby="title ? `${uid}-title` : undefined"
            :aria-label="title ? undefined : 'Confirm'"
            :aria-describedby="`${uid}-message`"
            class="bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 max-w-sm w-full mx-4 border border-gray-200 dark:border-gray-700"
        >
            <h3
                v-if="title"
                :id="`${uid}-title`"
                class="font-semibold text-gray-900 dark:text-gray-100 mb-2"
            >
                {{ title }}
            </h3>
            <p
                :id="`${uid}-message`"
                class="text-sm text-gray-600 dark:text-gray-400"
                :class="title ? 'mb-5' : 'mb-5 mt-1'"
            >
                {{ message }}
            </p>
            <div class="flex gap-3 justify-end">
                <button
                    ref="cancelButton"
                    type="button"
                    class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                    @click="emit('cancel')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="px-4 py-2 text-sm font-medium text-white rounded-lg"
                    :class="
                        danger ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'
                    "
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
