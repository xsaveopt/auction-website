import { createRouter, createWebHistory } from 'vue-router';
import AuctionList from './pages/AuctionList.vue';
import AuctionDetail from './pages/AuctionDetail.vue';
import CreateAuction from './pages/CreateAuction.vue';
import AdminResults from './pages/AdminResults.vue';
import Login from './pages/Login.vue';
import Register from './pages/Register.vue';

const routes = [
    { path: '/', component: AuctionList },
    { path: '/admin/results', component: AdminResults, meta: { requiresAuth: true } },
    { path: '/auctions/new', component: CreateAuction, meta: { requiresAuth: true } },
    { path: '/auctions/:id', component: AuctionDetail, props: true },
    { path: '/login', component: Login },
    { path: '/register', component: Register },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
