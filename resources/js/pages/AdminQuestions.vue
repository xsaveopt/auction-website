<script setup>
import { ref, computed, inject, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const user = inject("user");
const questions = ref([]);
const loading = ref(true);
const error = ref("");
const editingQuestionId = ref(null);
const answerDrafts = ref({});
const savingAnswerId = ref(null);
const deletingQuestionId = ref(null);

if (!user.value?.is_admin) {
    router.push("/");
}

onMounted(async () => {
    await loadQuestions();
});

async function loadQuestions() {
    try {
        const data = await api("/questions");
        questions.value = data.questions;
    } finally {
        loading.value = false;
    }
}

const unansweredQuestions = computed(() =>
    questions.value.filter((q) => !q.answer),
);
const answeredQuestions = computed(() =>
    questions.value.filter((q) => q.answer),
);

function startAnswer(question) {
    editingQuestionId.value = question.id;
    answerDrafts.value = {
        ...answerDrafts.value,
        [question.id]: question.answer ?? "",
    };
    error.value = "";
}

function cancelAnswer(questionId) {
    if (editingQuestionId.value === questionId) {
        editingQuestionId.value = null;
    }
}

async function submitAnswer(question) {
    error.value = "";
    const answer = (answerDrafts.value[question.id] ?? "").trim();
    if (!answer) {
        error.value = "Answer is required.";
        return;
    }
    try {
        savingAnswerId.value = question.id;
        await api(`/questions/${question.id}`, {
            method: "PUT",
            body: JSON.stringify({ answer }),
        });
        editingQuestionId.value = null;
        await loadQuestions();
    } catch (e) {
        error.value =
            e.data?.message ||
            e.data?.errors?.answer?.[0] ||
            "Failed to save answer.";
    } finally {
        savingAnswerId.value = null;
    }
}

async function deleteQuestion(question) {
    if (!confirm("Delete this question?")) return;
    error.value = "";
    try {
        deletingQuestionId.value = question.id;
        await api(`/questions/${question.id}`, { method: "DELETE" });
        if (editingQuestionId.value === question.id) {
            editingQuestionId.value = null;
        }
        const nextDrafts = { ...answerDrafts.value };
        delete nextDrafts[question.id];
        answerDrafts.value = nextDrafts;
        await loadQuestions();
    } catch (e) {
        error.value = e.data?.message || "Failed to delete question.";
    } finally {
        deletingQuestionId.value = null;
    }
}

function formatDate(d) {
    return new Date(d).toLocaleString();
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold mb-4">All Questions</h1>

        <p v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</p>

        <template v-else>
            <div v-if="error" class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-3 rounded mb-4">
                {{ error }}
            </div>

            <p v-if="questions.length === 0" class="text-gray-500 dark:text-gray-400">
                No questions yet.
            </p>

            <template v-else>
                <div v-if="unansweredQuestions.length" class="mb-8">
                    <h2 class="text-lg font-semibold text-amber-700 dark:text-amber-400 mb-3">
                        Awaiting Answer ({{ unansweredQuestions.length }})
                    </h2>
                    <div class="space-y-3">
                        <div
                            v-for="question in unansweredQuestions"
                            :key="question.id"
                            class="bg-white dark:bg-gray-800 rounded shadow p-4"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <router-link
                                        :to="`/auctions/${question.auction.id}`"
                                        class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        {{ question.auction.title }}
                                    </router-link>
                                    <p
                                        class="mt-2 whitespace-pre-line"
                                    >
                                        {{ question.question }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Asked by {{ question.user.username }} ·
                                        {{ formatDate(question.created_at) }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-amber-50 dark:bg-amber-900/30 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-400 shrink-0"
                                >
                                    Awaiting answer
                                </span>
                            </div>

                            <div
                                v-if="editingQuestionId === question.id"
                                class="mt-4"
                            >
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                    >Answer</label
                                >
                                <textarea
                                    v-model="answerDrafts[question.id]"
                                    rows="3"
                                    class="w-full border rounded px-3 py-2"
                                ></textarea>
                                <div class="mt-3 flex gap-3">
                                    <button
                                        @click="submitAnswer(question)"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60"
                                        :disabled="
                                            savingAnswerId === question.id
                                        "
                                    >
                                        {{
                                            savingAnswerId === question.id
                                                ? "Saving..."
                                                : "Publish answer"
                                        }}
                                    </button>
                                    <button
                                        @click="cancelAnswer(question.id)"
                                        class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            <div v-else class="mt-3 flex gap-3">
                                <button
                                    @click="startAnswer(question)"
                                    class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                >
                                    Answer question
                                </button>
                                <button
                                    @click="deleteQuestion(question)"
                                    class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 disabled:opacity-60"
                                    :disabled="
                                        deletingQuestionId === question.id
                                    "
                                >
                                    {{
                                        deletingQuestionId === question.id
                                            ? "Deleting..."
                                            : "Delete question"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="answeredQuestions.length">
                    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3">
                        Answered ({{ answeredQuestions.length }})
                    </h2>
                    <div class="space-y-3">
                        <div
                            v-for="question in answeredQuestions"
                            :key="question.id"
                            class="bg-white dark:bg-gray-800 rounded shadow p-4"
                        >
                            <div class="min-w-0">
                                <router-link
                                    :to="`/auctions/${question.auction.id}`"
                                    class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    {{ question.auction.title }}
                                </router-link>
                                <p
                                    class="mt-2 font-medium whitespace-pre-line"
                                >
                                    {{ question.question }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Asked by {{ question.user.username }} ·
                                    {{ formatDate(question.created_at) }}
                                </p>
                            </div>

                            <div
                                v-if="editingQuestionId === question.id"
                                class="mt-4"
                            >
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                    >Answer</label
                                >
                                <textarea
                                    v-model="answerDrafts[question.id]"
                                    rows="3"
                                    class="w-full border rounded px-3 py-2"
                                ></textarea>
                                <div class="mt-3 flex gap-3">
                                    <button
                                        @click="submitAnswer(question)"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60"
                                        :disabled="
                                            savingAnswerId === question.id
                                        "
                                    >
                                        {{
                                            savingAnswerId === question.id
                                                ? "Saving..."
                                                : "Save answer"
                                        }}
                                    </button>
                                    <button
                                        @click="cancelAnswer(question.id)"
                                        class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            <template v-else>
                                <div class="mt-3 rounded-lg bg-gray-50 dark:bg-gray-700 p-3">
                                    <p
                                        class="text-gray-700 dark:text-gray-300 whitespace-pre-line"
                                    >
                                        {{ question.answer }}
                                    </p>
                                    <p
                                        v-if="question.answered_at"
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Answered
                                        {{ formatDate(question.answered_at) }}
                                    </p>
                                </div>
                                <div class="mt-3 flex gap-3">
                                    <button
                                        @click="startAnswer(question)"
                                        class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                    >
                                        Update answer
                                    </button>
                                    <button
                                        @click="deleteQuestion(question)"
                                        class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 disabled:opacity-60"
                                        :disabled="
                                            deletingQuestionId === question.id
                                        "
                                    >
                                        {{
                                            deletingQuestionId === question.id
                                                ? "Deleting..."
                                                : "Delete question"
                                        }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>
