import { createRouter, createWebHistory } from "vue-router";
import AuctionList from "./pages/AuctionList.vue";
import AuctionDetail from "./pages/AuctionDetail.vue";
import CreateAuction from "./pages/CreateAuction.vue";
import EditAuction from "./pages/EditAuction.vue";
import AdminResults from "./pages/AdminResults.vue";
import AdminQuestions from "./pages/AdminQuestions.vue";
import AdminAuditLog from "./pages/AdminAuditLog.vue";
import AdminCategories from "./pages/AdminCategories.vue";
import Login from "./pages/Login.vue";
import Register from "./pages/Register.vue";
import MyDashboard from "./pages/MyDashboard.vue";

const routes = [
    { path: "/", component: AuctionList },
    { path: "/dashboard", component: MyDashboard },
    { path: "/admin/results", component: AdminResults },
    { path: "/admin/questions", component: AdminQuestions },
    { path: "/admin/categories", component: AdminCategories },
    { path: "/admin/audit-log", component: AdminAuditLog },
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
