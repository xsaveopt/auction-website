<script setup>
import { ref, inject, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();

const props = defineProps({ id: String });
const user = inject("user");
const schedule = inject("schedule");
const currencySymbol = inject("currencySymbol");
const heartbeatData = inject("heartbeatData");
const auction = ref(null);
const bidAmount = ref("");
const bidQuantity = ref(1);
const error = ref("");
const questionError = ref("");
const loading = ref(true);
const activeImage = ref(0);
const questionText = ref("");
const answerDrafts = ref({});
const editingQuestionId = ref(null);
const askingQuestion = ref(false);
const savingAnswerId = ref(null);
const deletingQuestionId = ref(null);
const highlightedBids = ref(new Set());

const isSeller = computed(() => {
    if (!user.value || !auction.value) return false;
    return user.value.id === auction.value.seller.id;
});

const canModerateQuestions = computed(() => {
    if (!user.value || !auction.value) return false;
    return user.value.id === auction.value.seller.id || user.value.is_admin;
});

const canAskQuestion = computed(() => {
    if (!user.value || !auction.value) return false;
    return user.value.id !== auction.value.seller.id;
});

const myBid = computed(() => {
    if (!user.value || !auction.value) return null;
    return auction.value.bids.find((b) => b.user.id === user.value.id);
});

const selectedBidTotal = computed(
    () => Number(bidAmount.value || 0) * Number(bidQuantity.value || 0),
);
const answeredQuestions = computed(
    () => auction.value?.questions?.filter((question) => question.answer) ?? [],
);
const openQuestions = computed(
    () => auction.value?.questions?.filter((question) => !question.answer) ?? [],
);

function bidKey(bid) {
    return `${bid.id}:${bid.amount}:${bid.quantity}`;
}

function updateAuction(newAuction) {
    if (!newAuction) return;

    // Detect new or changed bids for highlight animation
    if (auction.value?.bids) {
        const oldKeys = new Set(auction.value.bids.map(bidKey));
        const newHighlights = newAuction.bids
            .filter((b) => !oldKeys.has(bidKey(b)))
            .map((b) => b.id);
        if (newHighlights.length) {
            highlightedBids.value = new Set(newHighlights);
            setTimeout(() => {
                highlightedBids.value = new Set();
            }, 1500);
        }
    }

    auction.value = newAuction;
    activeImage.value = Math.min(activeImage.value, Math.max(newAuction.images.length - 1, 0));
    const my = newAuction.bids.find((b) => b.user.id === user.value?.id);
    bidAmount.value = my
        ? (Number(my.amount) + 1).toFixed(2)
        : Number(newAuction.starting_price).toFixed(2);
    bidQuantity.value = my ? my.quantity : 1;
}

async function load(showLoading = false) {
    if (showLoading) loading.value = true;
    const data = await api(`/auctions/${props.id}`);
    updateAuction(data.auction);
    loading.value = false;
}

async function deleteAuction() {
    if (!confirm("Are you sure you want to delete this auction?")) return;
    try {
        await api(`/auctions/${props.id}`, { method: "DELETE" });
        router.push("/");
    } catch (e) {
        error.value = e.data?.message || "Failed to delete auction.";
    }
}

async function placeBid() {
    error.value = "";
    try {
        await api(`/auctions/${props.id}/bids`, {
            method: "POST",
            body: JSON.stringify({
                amount: Number(bidAmount.value),
                quantity: Number(bidQuantity.value),
            }),
        });
        await load();
    } catch (e) {
        error.value =
            e.data?.message ||
            e.data?.errors?.amount?.[0] ||
            e.data?.errors?.quantity?.[0] ||
            "Failed to place bid.";
    }
}

function startAnswer(question) {
    editingQuestionId.value = question.id;
    answerDrafts.value = {
        ...answerDrafts.value,
        [question.id]: question.answer ?? "",
    };
    questionError.value = "";
}

function cancelAnswer(questionId) {
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
        await api(`/auctions/${props.id}/questions`, {
            method: "POST",
            body: JSON.stringify({ question }),
        });
        questionText.value = "";
        await load();
    } catch (e) {
        questionError.value =
            e.data?.message || e.data?.errors?.question?.[0] || "Failed to ask question.";
    } finally {
        askingQuestion.value = false;
    }
}

async function submitAnswer(question) {
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
        await load();
    } catch (e) {
        questionError.value =
            e.data?.message || e.data?.errors?.answer?.[0] || "Failed to save answer.";
    } finally {
        savingAnswerId.value = null;
    }
}

async function deleteQuestion(question) {
    if (!confirm("Delete this question?")) return;

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
        await load();
    } catch (e) {
        questionError.value = e.data?.message || "Failed to delete question.";
    } finally {
        deletingQuestionId.value = null;
    }
}

function formatDate(d) {
    return new Date(d).toLocaleString();
}

function formatMoney(value) {
    return Number(value).toFixed(2);
}

function watchingText(count) {
    return `${count} currently watching`;
}

onMounted(async () => {
    await load(true);
});

watch(heartbeatData, (data) => {
    if (data?.auction && String(data.auction.id) === String(props.id)) {
        updateAuction(data.auction);
    }
});

watch(
    () => props.id,
    async () => {
        activeImage.value = 0;
        await load(true);
    },
);
</script>

<template>
    <div v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</div>
    <div
        v-else-if="auction"
        class="xl:grid xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)] xl:items-start xl:gap-6"
    >
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">{{ auction.title }}</h1>
                        <p
                            class="mt-2 inline-flex rounded-full bg-amber-50 dark:bg-amber-900/30 px-3 py-1 text-sm font-medium text-amber-700 dark:text-amber-400"
                        >
                            {{ watchingText(auction.watcher_count) }}
                        </p>
                    </div>
                    <div v-if="user?.is_admin" class="flex gap-2">
                        <router-link
                            :to="`/auctions/${auction.id}/edit`"
                            class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 border border-blue-200 dark:border-blue-700 rounded px-3 py-1"
                        >
                            Edit
                        </router-link>
                        <button
                            @click="deleteAuction"
                            class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 border border-red-200 dark:border-red-700 rounded px-3 py-1"
                        >
                            Delete
                        </button>
                    </div>
                </div>

                <div v-if="auction.images.length" class="mt-4">
                    <img
                        :src="auction.images[activeImage].url"
                        :alt="auction.title"
                        class="w-full max-h-96 object-contain rounded bg-gray-50 dark:bg-gray-700"
                    />
                    <div v-if="auction.images.length > 1" class="flex gap-2 mt-2">
                        <button
                            v-for="(img, i) in auction.images"
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

                <p class="mt-4 text-gray-700 dark:text-gray-300 whitespace-pre-line">
                    {{ auction.description }}
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-6 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Starting price:</span>
                        <span class="ml-1"
                            >{{ currencySymbol }}{{ Number(auction.starting_price).toFixed(2) }} /
                            item</span
                        >
                    </div>
                    <div v-if="auction.quantity > 1">
                        <span class="text-gray-500 dark:text-gray-400">Available:</span>
                        <span class="ml-1 font-semibold text-purple-700 dark:text-purple-400"
                            >{{ auction.quantity }} items</span
                        >
                        <span class="text-gray-400 dark:text-gray-500 ml-1"
                            >(max {{ auction.max_per_bidder }} per person)</span
                        >
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Ends:</span>
                        <span class="ml-1">{{ formatDate(auction.ends_at) }}</span>
                    </div>
                </div>
                <div
                    v-if="auction.quantity > 1"
                    class="mt-3 text-sm text-gray-500 dark:text-gray-400"
                >
                    <p>
                        Items allocated top-down by bid price. All winners pay the same clearing
                        price (the lowest winning bid).
                    </p>
                    <p class="mt-1">
                        Your bid amount is per item. Entering
                        {{ currencySymbol }}10.00 for 4 items means a total commitment of
                        {{ currencySymbol }}40.00.
                    </p>
                    <p v-if="auction.max_per_bidder > 1" class="mt-1">
                        You can bid for up to
                        {{ auction.max_per_bidder }} items. Bids may be partially filled if stock
                        runs out.
                    </p>
                    <p
                        v-if="auction.bid_count > 0"
                        class="mt-2 font-medium text-green-700 dark:text-green-400"
                    >
                        Clearing price: {{ currencySymbol
                        }}{{ formatMoney(auction.current_price) }} / item ·
                        {{ auction.items_allocated }} / {{ auction.quantity }} allocated
                    </p>
                </div>
            </div>

            <div
                v-if="auction.is_active && user && user.id !== auction.seller.id"
                class="bg-white dark:bg-gray-800 rounded shadow p-6"
            >
                <h2 class="text-lg font-semibold mb-3">
                    {{ myBid ? "Update Your Bid" : "Place a Bid" }}
                </h2>
                <div
                    v-if="schedule && !schedule.is_open"
                    class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-700 rounded p-3 mb-3 text-orange-700 dark:text-orange-400 text-sm"
                >
                    Bidding is closed during office hours ({{ schedule.closed_start }} –
                    {{ schedule.closed_end }}). You can bid again after {{ schedule.closed_end }}.
                </div>
                <template v-else>
                    <div v-if="myBid" class="mb-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Your current bid:
                            <span class="font-bold text-green-700 dark:text-green-400"
                                >{{ currencySymbol }}{{ formatMoney(myBid.amount) }}</span
                            >
                            <span v-if="auction.max_per_bidder > 1">
                                for {{ myBid.quantity }} item{{
                                    myBid.quantity !== 1 ? "s" : ""
                                }}</span
                            >
                            <span v-if="auction.max_per_bidder > 1" class="ml-1"
                                >(up to {{ currencySymbol
                                }}{{ formatMoney(myBid.amount * myBid.quantity) }} total)</span
                            >
                            <span
                                v-if="myBid.won_quantity > 0"
                                class="text-green-600 dark:text-green-400 ml-1"
                                >(winning {{ myBid.won_quantity }})</span
                            >
                        </p>
                    </div>
                    <div
                        v-if="error"
                        class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-3 rounded mb-3"
                    >
                        {{ error }}
                    </div>
                    <form @submit.prevent="placeBid" class="flex flex-wrap gap-3 items-end">
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Price per item</label
                            >
                            <div class="flex items-center">
                                <span class="text-gray-500 dark:text-gray-400 mr-1">{{
                                    currencySymbol
                                }}</span>
                                <input
                                    v-model="bidAmount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    class="border rounded px-3 py-2 w-32"
                                />
                            </div>
                        </div>
                        <div v-if="auction.max_per_bidder > 1">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Quantity</label
                            >
                            <input
                                v-model="bidQuantity"
                                type="number"
                                min="1"
                                :max="auction.max_per_bidder"
                                required
                                class="border rounded px-3 py-2 w-20"
                            />
                        </div>
                        <div
                            v-if="auction.max_per_bidder > 1"
                            class="basis-full text-sm text-gray-500 dark:text-gray-400"
                        >
                            Your price is per item, so bidding
                            {{ currencySymbol }}{{ formatMoney(bidAmount) }} for
                            {{ bidQuantity }} item{{ Number(bidQuantity) !== 1 ? "s" : "" }} means a
                            maximum total of {{ currencySymbol
                            }}{{ formatMoney(selectedBidTotal) }}.
                        </div>
                        <button
                            type="submit"
                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
                        >
                            {{ myBid ? "Update Bid" : "Bid" }}
                        </button>
                    </form>
                </template>
            </div>
            <div
                v-else-if="!auction.is_active"
                class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded p-4 text-yellow-800 dark:text-yellow-300"
            >
                This auction has ended.
            </div>

            <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
                <h2 class="text-lg font-semibold mb-3">
                    Bids ({{ auction.bid_count }})
                    <span
                        v-if="auction.quantity > 1"
                        class="text-sm font-normal text-gray-500 dark:text-gray-400"
                    >
                        — {{ auction.items_allocated }} / {{ auction.quantity }} allocated
                    </span>
                </h2>
                <p v-if="auction.bids.length === 0" class="text-gray-500 dark:text-gray-400">
                    No bids yet.
                </p>
                <TransitionGroup
                    v-else
                    name="bid-list"
                    tag="ul"
                    class="divide-y dark:divide-gray-700 relative"
                >
                    <li
                        v-for="bid in auction.bids"
                        :key="bid.id"
                        class="py-2 flex items-center justify-between transition-all duration-500"
                        :class="[
                            bid.won_quantity > 0
                                ? 'bg-green-50 dark:bg-green-900/20 -mx-2 px-2 rounded'
                                : '',
                            highlightedBids.has(bid.id) ? 'bid-flash' : '',
                        ]"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ bid.user.username }}</span>
                            <span
                                v-if="bid.user.id === user?.id"
                                class="text-xs text-blue-600 dark:text-blue-400"
                                >(you)</span
                            >
                            <span
                                v-if="auction.max_per_bidder > 1"
                                class="text-gray-400 dark:text-gray-500 text-xs"
                                >wants {{ bid.quantity }}</span
                            >
                            <span class="text-gray-400 dark:text-gray-500 text-xs">{{
                                formatDate(bid.created_at)
                            }}</span>
                        </div>
                        <div class="text-right">
                            <span
                                class="font-bold"
                                :class="
                                    bid.won_quantity > 0
                                        ? 'text-green-700 dark:text-green-400'
                                        : 'text-gray-500 dark:text-gray-400'
                                "
                            >
                                {{ currencySymbol }}{{ Number(bid.amount).toFixed(2) }}
                            </span>
                            <span
                                v-if="bid.won_quantity > 0 && auction.quantity > 1"
                                class="block text-xs text-green-600 dark:text-green-400"
                            >
                                wins {{ bid.won_quantity }} @ {{ currencySymbol
                                }}{{ Number(auction.current_price).toFixed(2) }}
                            </span>
                        </div>
                    </li>
                </TransitionGroup>
            </div>
        </div>

        <div class="mt-6 xl:mt-0 xl:sticky xl:top-6">
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
                            Asked by {{ question.user.username }} ·
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
                                    {{
                                        savingAnswerId === question.id ? "Saving..." : "Save answer"
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

                        <div v-if="canModerateQuestions" class="mt-4 flex gap-3">
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
                                        {{ question.user.username }}
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
                            <div v-else-if="canModerateQuestions" class="mt-4 flex gap-3">
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

                    <form v-if="canAskQuestion" @submit.prevent="submitQuestion" class="mt-4">
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
    </div>
</template>

<style scoped>
/* New bids slide in and fade */
.bid-list-enter-from {
    opacity: 0;
    transform: translateY(-20px);
}
.bid-list-enter-active {
    transition: all 0.4s ease-out;
}
/* Removed bids fade out */
.bid-list-leave-active {
    transition: all 0.3s ease-in;
    position: absolute;
    width: 100%;
}
.bid-list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
/* Reorder animation */
.bid-list-move {
    transition: transform 0.4s ease;
}
/* Flash highlight for new or updated bids */
@keyframes bid-highlight {
    0% {
        background-color: rgb(254 243 199);
    }
    100% {
        background-color: transparent;
    }
}
@keyframes bid-highlight-dark {
    0% {
        background-color: rgb(120 53 15 / 0.3);
    }
    100% {
        background-color: transparent;
    }
}
.bid-flash {
    animation: bid-highlight 1.5s ease-out;
}
:where(.dark) .bid-flash {
    animation: bid-highlight-dark 1.5s ease-out;
}
</style>
