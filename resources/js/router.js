import { createRouter, createWebHistory } from "vue-router";
import AuctionList from "./pages/AuctionList.vue";
import AuctionDetail from "./pages/AuctionDetail.vue";
import EditAuction from "./pages/EditAuction.vue";
import AdminPanel from "./pages/AdminPanel.vue";
import Login from "./pages/Login.vue";
import Register from "./pages/Register.vue";
import MyDashboard from "./pages/MyDashboard.vue";

const adminTabPaths = {
    results: "/admin/results",
    questions: "/admin/questions",
    priceOffers: "/admin/price-offers",
    categories: "/admin/categories",
    auditLog: "/admin/audit-log",
    sell: "/admin/sell",
    settings: "/admin/settings",
};

const adminTabQueryKeys = {
    results: ["view"],
    questions: [],
    priceOffers: [],
    categories: [],
    auditLog: ["page"],
    sell: [],
};

function adminPanelRedirect(query = {}) {
    const requestedTab =
        typeof query.tab === "string" && query.tab in adminTabPaths ? query.tab : "results";
    const preservedQuery = Object.fromEntries(
        adminTabQueryKeys[requestedTab]
            .map((key) => [key, query[key]])
            .filter(([, value]) => value != null),
    );

    return {
        path: adminTabPaths[requestedTab],
        query: preservedQuery,
    };
}

const routes = [
    { path: "/", component: AuctionList },
    { path: "/dashboard", component: MyDashboard },
    { path: "/admin", redirect: (to) => adminPanelRedirect(to.query) },
    { path: "/admin/results", name: "admin-results", component: AdminPanel },
    { path: "/admin/questions", name: "admin-questions", component: AdminPanel },
    { path: "/admin/price-offers", name: "admin-price-offers", component: AdminPanel },
    { path: "/admin/categories", name: "admin-categories", component: AdminPanel },
    { path: "/admin/audit-log", name: "admin-audit-log", component: AdminPanel },
    { path: "/admin/sell", name: "admin-sell", component: AdminPanel },
    { path: "/admin/settings", name: "admin-settings", component: AdminPanel },
    { path: "/auctions/new", redirect: "/admin/sell" },
    { path: "/auctions/:id/edit", component: EditAuction, props: true },
    { path: "/auctions/:id", component: AuctionDetail, props: true },
    { path: "/login", component: Login },
    { path: "/register", component: Register },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
