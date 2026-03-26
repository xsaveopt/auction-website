<script setup>
import { computed, ref, watch, inject } from "vue";
import { useRoute, useRouter } from "vue-router";
import AdminResults from "./AdminResults.vue";
import AdminQuestions from "./AdminQuestions.vue";
import AdminPriceOffers from "./AdminPriceOffers.vue";
import AdminCategories from "./AdminCategories.vue";
import AdminAuditLog from "./AdminAuditLog.vue";
import CreateAuction from "./CreateAuction.vue";

const router = useRouter();
const route = useRoute();
const user = inject("user");

if (!user.value?.is_admin) {
    router.push("/");
}

const tabDefinitions = [
    {
        key: "results",
        label: "Results",
        to: "/admin/results",
        activeClasses: "bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
        iconPath:
            "M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z",
    },
    {
        key: "questions",
        label: "Questions",
        to: "/admin/questions",
        activeClasses: "bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
        iconPath:
            "M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z",
    },
    {
        key: "priceOffers",
        label: "Price Offers",
        to: "/admin/price-offers",
        activeClasses: "bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
        iconPath:
            "M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z",
    },
    {
        key: "categories",
        label: "Categories",
        to: "/admin/categories",
        activeClasses: "bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
        iconPath:
            "M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10",
    },
    {
        key: "auditLog",
        label: "Audit Log",
        to: "/admin/audit-log",
        activeClasses: "bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
        iconPath:
            "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01",
    },
    {
        key: "sell",
        label: "Sell Item",
        to: "/admin/sell",
        activeClasses: "bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300",
        iconPath: "M12 4v16m8-8H4",
    },
];
const tabByRouteName = {
    "admin-results": "results",
    "admin-questions": "questions",
    "admin-price-offers": "priceOffers",
    "admin-categories": "categories",
    "admin-audit-log": "auditLog",
    "admin-sell": "sell",
};
const inactiveTabClasses =
    "text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-800 dark:hover:text-gray-200";
const activeTab = computed(() => tabByRouteName[route.name] ?? "results");

// Tracks which tabs have been opened at least once (lazy mount, keeps alive)
// Sell is excluded — it always remounts to give a fresh form
const lazyTabs = ["results", "questions", "priceOffers", "categories", "auditLog"];
const tabMounted = ref(Object.fromEntries(lazyTabs.map((tab) => [tab, false])));

watch(
    activeTab,
    (tab) => {
        if (lazyTabs.includes(tab)) {
            tabMounted.value[tab] = true;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex gap-4 py-4 min-h-[60vh]">
        <!-- Sidebar -->
        <aside class="w-44 shrink-0">
            <div class="sticky top-4">
                <p
                    class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 px-3 mb-2"
                >
                    Admin
                </p>
                <nav class="space-y-0.5">
                    <router-link
                        v-for="tab in tabDefinitions"
                        :key="tab.key"
                        :to="tab.to"
                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                        :class="activeTab === tab.key ? tab.activeClasses : inactiveTabClasses"
                    >
                        <svg
                            class="w-4 h-4 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                :d="tab.iconPath"
                            />
                        </svg>
                        {{ tab.label }}
                    </router-link>
                </nav>
            </div>
        </aside>

        <!-- Divider -->
        <div class="w-px self-stretch bg-gray-200 dark:bg-gray-700"></div>

        <!-- Content area -->
        <div class="flex-1 min-w-0 pl-2">
            <AdminResults
                v-if="tabMounted.results"
                v-show="activeTab === 'results'"
                :active="activeTab === 'results'"
            />
            <AdminQuestions v-if="tabMounted.questions" v-show="activeTab === 'questions'" />
            <AdminPriceOffers v-if="tabMounted.priceOffers" v-show="activeTab === 'priceOffers'" />
            <AdminCategories v-if="tabMounted.categories" v-show="activeTab === 'categories'" />
            <AdminAuditLog
                v-if="tabMounted.auditLog"
                v-show="activeTab === 'auditLog'"
                :active="activeTab === 'auditLog'"
            />
            <!-- Sell remounts each time for a fresh form -->
            <CreateAuction v-if="activeTab === 'sell'" />
        </div>
    </div>
</template>
