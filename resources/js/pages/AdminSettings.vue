<script setup lang="ts">
import { ref, onMounted, useId } from "vue";
import { api } from "../lib/api";
import ConfirmDialog from "../components/ConfirmDialog.vue";
import type { ApiKeyStatus, ConfirmDialogState } from "../lib/types";

const loading = ref(true);
const saving = ref(false);
const saved = ref(false);
const error = ref<string | null>(null);

const apiKey = ref<ApiKeyStatus>({ exists: false, created_at: null });
const newApiKey = ref<string | null>(null);
const apiKeyBusy = ref(false);
const apiKeyError = ref<string | null>(null);
const copied = ref(false);
const confirmDialog = ref<ConfirmDialogState | null>(null);

const form = ref({
    is_locked: false,
    lock_message: "",
    bidding_schedule_enabled: true,
    bidding_closed_start: "09:00",
    bidding_closed_end: "18:00",
    bidding_weekends_open: true,
    currency_symbol: "$",
    anti_sniping_enabled: true,
    anti_sniping_window: 60,
    anti_sniping_extension: 300,
    leftover_sales_enabled: false,
    leftover_price_factor: 0.75,
    company_name: "",
    company_street: "",
    company_postal_code: "",
    company_city: "",
    company_kvk: "",
    company_btw: "",
    company_iban_1: "",
    company_iban_2: "",
    invoice_btw_percentage: 21,
    invoice_payment_days: 30,
});

onMounted(async () => {
    const data = await api<{ settings?: Record<string, unknown> }>("/admin/settings").catch(
        () => null,
    );
    if (data?.settings) {
        Object.assign(form.value, data.settings);
    }
    loading.value = false;

    const keyData = await api<{ api_key?: ApiKeyStatus }>("/admin/api-key").catch(() => null);
    if (keyData?.api_key) {
        apiKey.value = keyData.api_key;
    }
});

async function generateApiKey() {
    apiKeyBusy.value = true;
    apiKeyError.value = null;
    copied.value = false;

    try {
        const data = await api<{ key: string; api_key: ApiKeyStatus }>("/admin/api-key", {
            method: "POST",
        });
        newApiKey.value = data.key;
        apiKey.value = data.api_key;
    } catch (e) {
        apiKeyError.value = (e instanceof Error && e.message) || "Failed to generate api key.";
    } finally {
        apiKeyBusy.value = false;
    }
}

async function revokeApiKey() {
    apiKeyBusy.value = true;
    apiKeyError.value = null;

    try {
        const data = await api<{ api_key: ApiKeyStatus }>("/admin/api-key", {
            method: "DELETE",
        });
        apiKey.value = data.api_key;
        newApiKey.value = null;
    } catch (e) {
        apiKeyError.value = (e instanceof Error && e.message) || "Failed to revoke api key.";
    } finally {
        apiKeyBusy.value = false;
    }
}

function confirmRegenerate() {
    if (!apiKey.value.exists) {
        generateApiKey();
        return;
    }
    confirmDialog.value = {
        title: "Regenerate api key",
        message: "Your current api key stops working immediately.",
        confirmLabel: "Regenerate",
        danger: true,
        onConfirm: generateApiKey,
    };
}

function confirmRevoke() {
    confirmDialog.value = {
        title: "Revoke api key",
        message: "Requests using your current api key will be rejected.",
        confirmLabel: "Revoke",
        danger: true,
        onConfirm: revokeApiKey,
    };
}

async function copyApiKey() {
    if (!newApiKey.value) return;
    await navigator.clipboard.writeText(newApiKey.value).catch(() => null);
    copied.value = true;
}

async function save() {
    saving.value = true;
    saved.value = false;
    error.value = null;

    try {
        await api("/admin/settings", {
            method: "PUT",
            body: JSON.stringify(form.value),
        });
        saved.value = true;
        setTimeout(() => (saved.value = false), 3000);
    } catch (e) {
        error.value = (e instanceof Error && e.message) || "Failed to save settings.";
    } finally {
        saving.value = false;
    }
}

const uid = useId();
</script>

<template>
    <div class="max-w-2xl">
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
        <h1 class="text-2xl font-bold mb-1">Settings</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            Runtime configuration for bidding, currency, and invoices. Changes take effect
            immediately.
        </p>

        <div v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</div>
        <form v-else @submit.prevent="save" class="space-y-8">
            <!-- Maintenance Mode -->
            <section>
                <h2
                    class="text-base font-semibold mb-3 border-b border-gray-200 dark:border-gray-700 pb-1"
                >
                    Maintenance Mode
                </h2>
                <div class="space-y-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            v-model="form.is_locked"
                            type="checkbox"
                            class="w-4 h-4 rounded border-gray-300"
                        />
                        <span class="text-sm font-medium">Lock site for non-admin users</span>
                    </label>
                    <div :class="{ 'opacity-50 pointer-events-none': !form.is_locked }">
                        <label
                            :for="`${uid}-1`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Message shown to users
                            <span class="text-gray-400">(optional)</span></label
                        >
                        <input
                            :id="`${uid}-1`"
                            v-model="form.lock_message"
                            type="text"
                            maxlength="500"
                            placeholder="Site is temporarily unavailable."
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                </div>
            </section>

            <!-- Bidding Schedule -->
            <section>
                <h2
                    class="text-base font-semibold mb-3 border-b border-gray-200 dark:border-gray-700 pb-1"
                >
                    Bidding Schedule
                </h2>
                <div class="space-y-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            v-model="form.bidding_schedule_enabled"
                            type="checkbox"
                            class="w-4 h-4 rounded border-gray-300"
                        />
                        <span class="text-sm font-medium"
                            >Enable bidding schedule (office hours restriction)</span
                        >
                    </label>
                    <div
                        class="grid grid-cols-2 gap-4"
                        :class="{
                            'opacity-50 pointer-events-none': !form.bidding_schedule_enabled,
                        }"
                    >
                        <div>
                            <label
                                :for="`${uid}-2`"
                                class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Closed from</label
                            >
                            <input
                                :id="`${uid}-2`"
                                v-model="form.bidding_closed_start"
                                type="time"
                                class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                            />
                        </div>
                        <div>
                            <label
                                :for="`${uid}-3`"
                                class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Closed until</label
                            >
                            <input
                                :id="`${uid}-3`"
                                v-model="form.bidding_closed_end"
                                type="time"
                                class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                            />
                        </div>
                    </div>
                    <label
                        class="flex items-center gap-3 cursor-pointer"
                        :class="{
                            'opacity-50 pointer-events-none': !form.bidding_schedule_enabled,
                        }"
                    >
                        <input
                            v-model="form.bidding_weekends_open"
                            type="checkbox"
                            class="w-4 h-4 rounded border-gray-300"
                        />
                        <span class="text-sm font-medium">Allow bidding on weekends</span>
                    </label>
                </div>
            </section>

            <!-- Currency -->
            <section>
                <h2
                    class="text-base font-semibold mb-3 border-b border-gray-200 dark:border-gray-700 pb-1"
                >
                    Currency
                </h2>
                <div class="w-32">
                    <label
                        :for="`${uid}-4`"
                        class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                        >Symbol</label
                    >
                    <input
                        :id="`${uid}-4`"
                        v-model="form.currency_symbol"
                        type="text"
                        maxlength="10"
                        class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                    />
                </div>
            </section>

            <!-- Anti-Sniping -->
            <section>
                <h2
                    class="text-base font-semibold mb-3 border-b border-gray-200 dark:border-gray-700 pb-1"
                >
                    Anti-Sniping
                </h2>
                <div class="space-y-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            v-model="form.anti_sniping_enabled"
                            type="checkbox"
                            class="w-4 h-4 rounded border-gray-300"
                        />
                        <span class="text-sm font-medium"
                            >Enable anti-sniping (extend auction on late bids)</span
                        >
                    </label>
                    <div
                        class="grid grid-cols-2 gap-4"
                        :class="{ 'opacity-50 pointer-events-none': !form.anti_sniping_enabled }"
                    >
                        <div>
                            <label
                                :for="`${uid}-5`"
                                class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >
                                Window (seconds before end)
                            </label>
                            <input
                                :id="`${uid}-5`"
                                v-model.number="form.anti_sniping_window"
                                type="number"
                                min="0"
                                class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                            />
                        </div>
                        <div>
                            <label
                                :for="`${uid}-6`"
                                class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >
                                Extension (seconds added)
                            </label>
                            <input
                                :id="`${uid}-6`"
                                v-model.number="form.anti_sniping_extension"
                                type="number"
                                min="0"
                                class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Leftover Sales -->
            <section>
                <h2
                    class="text-base font-semibold mb-3 border-b border-gray-200 dark:border-gray-700 pb-1"
                >
                    Leftover Sales
                </h2>
                <div class="space-y-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            v-model="form.leftover_sales_enabled"
                            type="checkbox"
                            class="w-4 h-4 rounded border-gray-300"
                        />
                        <span class="text-sm font-medium"
                            >Enable leftover sales after auction ends</span
                        >
                    </label>
                    <div
                        class="w-40"
                        :class="{ 'opacity-50 pointer-events-none': !form.leftover_sales_enabled }"
                    >
                        <label
                            :for="`${uid}-7`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                        >
                            Price factor
                            <span class="ml-1 text-gray-400"
                                >(e.g. 0.75 = 75% of starting price)</span
                            >
                        </label>
                        <input
                            :id="`${uid}-7`"
                            v-model.number="form.leftover_price_factor"
                            type="number"
                            min="0"
                            max="10"
                            step="0.01"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                </div>
            </section>

            <!-- Company Info -->
            <section>
                <h2
                    class="text-base font-semibold mb-3 border-b border-gray-200 dark:border-gray-700 pb-1"
                >
                    Company Info
                    <span class="text-xs font-normal text-gray-400 ml-2">Used on quote PDFs</span>
                </h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label
                            :for="`${uid}-8`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Name</label
                        >
                        <input
                            :id="`${uid}-8`"
                            v-model="form.company_name"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div class="col-span-2">
                        <label
                            :for="`${uid}-9`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Street</label
                        >
                        <input
                            :id="`${uid}-9`"
                            v-model="form.company_street"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label
                            :for="`${uid}-10`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Postal code</label
                        >
                        <input
                            :id="`${uid}-10`"
                            v-model="form.company_postal_code"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label
                            :for="`${uid}-11`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >City</label
                        >
                        <input
                            :id="`${uid}-11`"
                            v-model="form.company_city"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label
                            :for="`${uid}-12`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >KvK</label
                        >
                        <input
                            :id="`${uid}-12`"
                            v-model="form.company_kvk"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label
                            :for="`${uid}-13`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >BTW</label
                        >
                        <input
                            :id="`${uid}-13`"
                            v-model="form.company_btw"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label
                            :for="`${uid}-14`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >IBAN 1</label
                        >
                        <input
                            :id="`${uid}-14`"
                            v-model="form.company_iban_1"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label
                            :for="`${uid}-15`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >IBAN 2</label
                        >
                        <input
                            :id="`${uid}-15`"
                            v-model="form.company_iban_2"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                </div>
            </section>

            <!-- Invoice -->
            <section>
                <h2
                    class="text-base font-semibold mb-3 border-b border-gray-200 dark:border-gray-700 pb-1"
                >
                    Invoice
                </h2>
                <div class="grid grid-cols-2 gap-4 max-w-xs">
                    <div>
                        <label
                            :for="`${uid}-16`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >BTW %</label
                        >
                        <input
                            :id="`${uid}-16`"
                            v-model.number="form.invoice_btw_percentage"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label
                            :for="`${uid}-17`"
                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Payment days</label
                        >
                        <input
                            :id="`${uid}-17`"
                            v-model.number="form.invoice_payment_days"
                            type="number"
                            min="1"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                </div>
            </section>

            <!-- Save -->
            <div class="flex items-center gap-4 pt-2">
                <button
                    type="submit"
                    :disabled="saving"
                    class="px-5 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                >
                    {{ saving ? "Saving..." : "Save settings" }}
                </button>
                <span v-if="saved" class="text-sm text-green-600 dark:text-green-400">Saved.</span>
                <span v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</span>
            </div>
        </form>

        <section v-if="!loading" class="mt-10" data-testid="api-key">
            <h2
                class="text-base font-semibold mb-3 border-b border-gray-200 dark:border-gray-700 pb-1"
            >
                API Key
                <span class="text-xs font-normal text-gray-400 ml-2">Personal to your account</span>
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                Send it as <code>Authorization: Bearer &lt;key&gt;</code>. Requests made with it act
                as you and show up in the audit log under your name.
            </p>
            <p class="text-sm mb-3">
                <span v-if="apiKey.exists">
                    Active, created
                    {{ apiKey.created_at ? new Date(apiKey.created_at).toLocaleString() : "" }}
                </span>
                <span v-else class="text-gray-500 dark:text-gray-400">No api key yet.</span>
            </p>
            <div v-if="newApiKey" class="mb-3">
                <label
                    :for="`${uid}-18`"
                    class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                    >Copy it now, it will not be shown again</label
                >
                <div class="flex gap-2">
                    <input
                        :id="`${uid}-18`"
                        :value="newApiKey"
                        readonly
                        class="w-full border rounded px-3 py-2 text-sm font-mono dark:bg-gray-800 dark:border-gray-600"
                        data-testid="new-api-key"
                    />
                    <button
                        type="button"
                        @click="copyApiKey"
                        class="px-3 py-2 border rounded text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800"
                    >
                        {{ copied ? "Copied" : "Copy" }}
                    </button>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    :disabled="apiKeyBusy"
                    @click="confirmRegenerate"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                    data-testid="generate-api-key"
                >
                    {{ apiKey.exists ? "Regenerate key" : "Generate key" }}
                </button>
                <button
                    v-if="apiKey.exists"
                    type="button"
                    :disabled="apiKeyBusy"
                    @click="confirmRevoke"
                    class="px-4 py-2 border border-red-300 text-red-600 rounded hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 disabled:opacity-50 text-sm font-medium"
                    data-testid="revoke-api-key"
                >
                    Revoke
                </button>
                <span v-if="apiKeyError" class="text-sm text-red-600 dark:text-red-400">{{
                    apiKeyError
                }}</span>
            </div>
        </section>
    </div>
</template>
