import { createRouter, createWebHistory } from "vue-router";
import AuctionList from "./pages/AuctionList.vue";
import AuctionDetail from "./pages/AuctionDetail.vue";
import CreateAuction from "./pages/CreateAuction.vue";
import EditAuction from "./pages/EditAuction.vue";
import AdminPanel from "./pages/AdminPanel.vue";
import Login from "./pages/Login.vue";
import Register from "./pages/Register.vue";
import MyDashboard from "./pages/MyDashboard.vue";

const routes = [
    { path: "/", component: AuctionList },
    { path: "/dashboard", component: MyDashboard },
    { path: "/admin", component: AdminPanel },
    { path: "/admin/results", redirect: "/admin" },
    { path: "/admin/questions", redirect: { path: "/admin", query: { tab: "questions" } } },
    { path: "/admin/categories", redirect: { path: "/admin", query: { tab: "categories" } } },
    { path: "/admin/audit-log", redirect: { path: "/admin", query: { tab: "auditLog" } } },
    { path: "/admin/price-offers", redirect: { path: "/admin", query: { tab: "priceOffers" } } },
    { path: "/auctions/new", component: CreateAuction },
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
