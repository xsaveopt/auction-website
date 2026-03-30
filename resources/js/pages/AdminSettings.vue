<script setup>
import { ref, onMounted } from "vue";
import { api } from "../api.js";

const loading = ref(true);
const saving = ref(false);
const saved = ref(false);
const error = ref(null);

const form = ref({
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
    const data = await api("/admin/settings").catch(() => null);
    if (data?.settings) {
        Object.assign(form.value, data.settings);
    }
    loading.value = false;
});

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
        error.value = e.message || "Failed to save settings.";
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="max-w-2xl">
        <h1 class="text-2xl font-bold mb-1">Settings</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            Runtime configuration for bidding, currency, and invoices. Changes take effect
            immediately.
        </p>

        <div v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</div>
        <form v-else @submit.prevent="save" class="space-y-8">
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
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Closed from</label
                            >
                            <input
                                v-model="form.bidding_closed_start"
                                type="time"
                                class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                >Closed until</label
                            >
                            <input
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
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                        >Symbol</label
                    >
                    <input
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
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                                Window (seconds before end)
                            </label>
                            <input
                                v-model.number="form.anti_sniping_window"
                                type="number"
                                min="0"
                                class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                                Extension (seconds added)
                            </label>
                            <input
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
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                            Price factor
                            <span class="ml-1 text-gray-400"
                                >(e.g. 0.75 = 75% of starting price)</span
                            >
                        </label>
                        <input
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
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Name</label
                        >
                        <input
                            v-model="form.company_name"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Street</label
                        >
                        <input
                            v-model="form.company_street"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Postal code</label
                        >
                        <input
                            v-model="form.company_postal_code"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >City</label
                        >
                        <input
                            v-model="form.company_city"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >KvK</label
                        >
                        <input
                            v-model="form.company_kvk"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >BTW</label
                        >
                        <input
                            v-model="form.company_btw"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >IBAN 1</label
                        >
                        <input
                            v-model="form.company_iban_1"
                            type="text"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >IBAN 2</label
                        >
                        <input
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
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >BTW %</label
                        >
                        <input
                            v-model.number="form.invoice_btw_percentage"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >Payment days</label
                        >
                        <input
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
    </div>
</template>
