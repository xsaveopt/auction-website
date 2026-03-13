import { createRouter, createWebHistory } from "vue-router";
import AuctionList from "./pages/AuctionList.vue";
import AuctionDetail from "./pages/AuctionDetail.vue";
import CreateAuction from "./pages/CreateAuction.vue";
import EditAuction from "./pages/EditAuction.vue";
import AdminResults from "./pages/AdminResults.vue";
import AdminQuestions from "./pages/AdminQuestions.vue";
import Login from "./pages/Login.vue";
import Register from "./pages/Register.vue";
import MyDashboard from "./pages/MyDashboard.vue";

const routes = [
    { path: "/", component: AuctionList },
    {
        path: "/dashboard",
        component: MyDashboard,
        meta: { requiresAuth: true },
    },
    {
        path: "/admin/results",
        component: AdminResults,
        meta: { requiresAuth: true },
    },
    {
        path: "/admin/questions",
        component: AdminQuestions,
        meta: { requiresAuth: true },
    },
    {
        path: "/auctions/new",
        component: CreateAuction,
        meta: { requiresAuth: true },
    },
    {
        path: "/auctions/:id/edit",
        component: EditAuction,
        meta: { requiresAuth: true },
        props: true,
    },
    { path: "/auctions/:id", component: AuctionDetail, props: true },
    { path: "/login", component: Login },
    { path: "/register", component: Register },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
