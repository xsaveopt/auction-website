<script setup lang="ts">
import { ref, computed } from "vue";
import { api, ApiError } from "../../api";
import type { AuctionQuestion, ConfirmDialogState } from "../../types";
import ConfirmDialog from "../../ConfirmDialog.vue";

const props = defineProps<{
    auctionId?: string;
    questions: AuctionQuestion[];
    canModerate: boolean;
    canAsk: boolean;
    isSeller: boolean;
}>();

const emit = defineEmits<{ refresh: [] }>();

const questionText = ref("");
const questionError = ref("");
const askingQuestion = ref(false);
const answerDrafts = ref<Record<number, string>>({});
const editingQuestionId = ref<number | null>(null);
const savingAnswerId = ref<number | null>(null);
const deletingQuestionId = ref<number | null>(null);
const confirmDialog = ref<ConfirmDialogState | null>(null);

const answeredQuestions = computed(() => props.questions.filter((question) => question.answer));
const openQuestions = computed(() => props.questions.filter((question) => !question.answer));

function apiError(e: unknown, ...fields: string[]): string | undefined {
    if (!(e instanceof ApiError)) return undefined;
    if (e.data.message) return e.data.message;
    for (const field of fields) {
        const value = e.data.errors?.[field]?.[0];
        if (value) return value;
    }
    return undefined;
}

function formatDate(d: string | null | undefined) {
    if (!d) return "";
    return d.slice(0, 16).replace("T", " ");
}

function startAnswer(question: AuctionQuestion) {
    editingQuestionId.value = question.id;
    answerDrafts.value = {
        ...answerDrafts.value,
        [question.id]: question.answer ?? "",
    };
    questionError.value = "";
}

function cancelAnswer(questionId: number) {
    if (editingQuestionId.value === questionId) {
        editingQuestionId.value = null;
    }
}

async function submitQuestion() {
    questionError.value = "";

    const question = questionText.value.trim();

    if (!question) {
        questionError.value = "Question is required.";
        return;
    }

    try {
        askingQuestion.value = true;
        await api(`/auctions/${props.auctionId}/questions`, {
            method: "POST",
            body: JSON.stringify({ question }),
        });
        questionText.value = "";
        emit("refresh");
    } catch (e) {
        questionError.value = apiError(e, "question") || "Failed to ask question.";
    } finally {
        askingQuestion.value = false;
    }
}

async function submitAnswer(question: AuctionQuestion) {
    questionError.value = "";

    const answer = (answerDrafts.value[question.id] ?? "").trim();

    if (!answer) {
        questionError.value = "Answer is required.";
        return;
    }

    try {
        savingAnswerId.value = question.id;
        await api(`/questions/${question.id}`, {
            method: "PUT",
            body: JSON.stringify({ answer }),
        });
        editingQuestionId.value = null;
        emit("refresh");
    } catch (e) {
        questionError.value = apiError(e, "answer") || "Failed to save answer.";
    } finally {
        savingAnswerId.value = null;
    }
}

function deleteQuestion(question: AuctionQuestion) {
    confirmDialog.value = {
        message: "Delete this question?",
        confirmLabel: "Delete",
        danger: true,
        onConfirm: async () => {
            questionError.value = "";
            try {
                deletingQuestionId.value = question.id;
                await api(`/questions/${question.id}`, { method: "DELETE" });
                if (editingQuestionId.value === question.id) {
                    editingQuestionId.value = null;
                }
                const nextDrafts = { ...answerDrafts.value };
                delete nextDrafts[question.id];
                answerDrafts.value = nextDrafts;
                emit("refresh");
            } catch (e) {
                questionError.value = apiError(e) || "Failed to delete question.";
            } finally {
                deletingQuestionId.value = null;
            }
        },
    };
}
</script>

<template>
    <div class="mt-6 xl:mt-0 xl:sticky xl:top-6">
        <ConfirmDialog
            v-if="confirmDialog"
            :title="confirmDialog.title"
            :message="confirmDialog.message"
            :confirm-label="confirmDialog.confirmLabel"
            :danger="confirmDialog.danger"
            @confirm="
                confirmDialog.onConfirm();
                confirmDialog = null;
            "
            @cancel="confirmDialog = null"
        />

        <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Frequently Asked Questions</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Answered responses stay here for future buyers.
                    </p>
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400"
                    >{{ answeredQuestions.length }} answered</span
                >
            </div>

            <div
                v-if="questionError"
                class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-3 rounded mt-4"
            >
                {{ questionError }}
            </div>

            <div v-if="answeredQuestions.length" class="mt-4 space-y-4">
                <div
                    v-for="question in answeredQuestions"
                    :key="question.id"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 p-4"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                    >
                        Question
                    </p>
                    <p class="mt-2 font-medium whitespace-pre-line">
                        {{ question.question }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Asked by {{ question.user?.username }} ·
                        {{ formatDate(question.created_at) }}
                    </p>

                    <div v-if="editingQuestionId === question.id" class="mt-4">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Answer</label
                        >
                        <textarea
                            v-model="answerDrafts[question.id]"
                            rows="4"
                            class="w-full border rounded px-3 py-2"
                        ></textarea>
                        <div class="mt-3 flex gap-3">
                            <button
                                @click="submitAnswer(question)"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60"
                                :disabled="savingAnswerId === question.id"
                            >
                                {{ savingAnswerId === question.id ? "Saving..." : "Save answer" }}
                            </button>
                            <button
                                @click="cancelAnswer(question.id)"
                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                    <div v-else class="mt-4 rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                        >
                            Answer
                        </p>
                        <p class="mt-2 text-gray-700 dark:text-gray-300 whitespace-pre-line">
                            {{ question.answer }}
                        </p>
                        <p
                            v-if="question.answered_at"
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Answered {{ formatDate(question.answered_at) }}
                        </p>
                    </div>

                    <div v-if="canModerate" class="mt-4 flex gap-3">
                        <button
                            v-if="editingQuestionId !== question.id"
                            @click="startAnswer(question)"
                            class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                        >
                            Update answer
                        </button>
                        <button
                            @click="deleteQuestion(question)"
                            class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 disabled:opacity-60"
                            :disabled="deletingQuestionId === question.id"
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
            <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                No answered questions yet.
            </p>

            <div class="mt-6 border-t dark:border-gray-700 pt-6">
                <div class="flex items-center justify-between gap-4">
                    <h3 class="font-semibold">Open questions</h3>
                    <span class="text-sm text-gray-500 dark:text-gray-400"
                        >{{ openQuestions.length }} awaiting an answer</span
                    >
                </div>

                <div v-if="openQuestions.length" class="mt-4 space-y-4">
                    <div
                        v-for="question in openQuestions"
                        :key="question.id"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 p-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-medium">
                                    {{ question.user?.username }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ formatDate(question.created_at) }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-amber-50 dark:bg-amber-900/30 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-400"
                            >
                                Awaiting answer
                            </span>
                        </div>

                        <p class="mt-3 text-gray-700 dark:text-gray-300 whitespace-pre-line">
                            {{ question.question }}
                        </p>

                        <div v-if="editingQuestionId === question.id" class="mt-4">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Answer</label
                            >
                            <textarea
                                v-model="answerDrafts[question.id]"
                                rows="4"
                                class="w-full border rounded px-3 py-2"
                            ></textarea>
                            <div class="mt-3 flex gap-3">
                                <button
                                    @click="submitAnswer(question)"
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60"
                                    :disabled="savingAnswerId === question.id"
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
                        <div v-else-if="canModerate" class="mt-4 flex gap-3">
                            <button
                                @click="startAnswer(question)"
                                class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                            >
                                Answer question
                            </button>
                            <button
                                @click="deleteQuestion(question)"
                                class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 disabled:opacity-60"
                                :disabled="deletingQuestionId === question.id"
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
                <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    No open questions right now.
                </p>
            </div>

            <div class="mt-6 border-t dark:border-gray-700 pt-6">
                <h3 class="font-semibold">Ask a question</h3>

                <form v-if="canAsk" @submit.prevent="submitQuestion" class="mt-4">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                        >Your question</label
                    >
                    <textarea
                        v-model="questionText"
                        rows="3"
                        maxlength="2000"
                        class="w-full border rounded px-3 py-2"
                        placeholder="Ask about condition, pickup, included accessories, or anything else buyers should know."
                    ></textarea>
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Answered questions move into the FAQ above.
                        </p>
                        <button
                            type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-60"
                            :disabled="askingQuestion"
                        >
                            {{ askingQuestion ? "Sending..." : "Send question" }}
                        </button>
                    </div>
                </form>
                <p v-else-if="isSeller" class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    You can answer or remove questions from the lists above.
                </p>
                <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    <router-link
                        to="/login"
                        class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                        >Log in</router-link
                    >
                    to ask a question.
                </p>
            </div>
        </div>
    </div>
</template>
