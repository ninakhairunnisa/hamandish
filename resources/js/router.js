import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

const routes = [
    { path: '/', name: 'feed', component: () => import('./views/Feed.vue') },
    { path: '/problems/:id', name: 'problem', component: () => import('./views/ProblemDetail.vue'), props: true },
    { path: '/submit', name: 'submit', component: () => import('./views/SubmitProblem.vue') },
    { path: '/popular', name: 'popular', component: () => import('./views/Popular.vue') },
    { path: '/profile', name: 'profile', component: () => import('./views/Profile.vue') },
    { path: '/assembly', name: 'assembly', component: () => import('./views/Assembly.vue') },
    { path: '/admin', name: 'admin', component: () => import('./views/Admin.vue'), meta: { admin: true } },
    // Shop
    { path: '/shop', name: 'shop', component: () => import('./views/Shop.vue') },
    { path: '/shop/products/:id', name: 'product', component: () => import('./views/ProductDetail.vue'), props: true },
    { path: '/cart', name: 'cart', component: () => import('./views/Cart.vue') },
    { path: '/checkout', name: 'checkout', component: () => import('./views/Checkout.vue') },
    { path: '/orders', name: 'orders', component: () => import('./views/MyOrders.vue') },
    { path: '/shop-admin', name: 'shop-admin', component: () => import('./views/ShopAdmin.vue'), meta: { shop: true } },
];

// VITE_APP_BASE sets the router base path.
// On shared hosting at /hamandish/ set: VITE_APP_BASE=/hamandish/
const router = createRouter({
    history: createWebHistory(import.meta.env.VITE_APP_BASE ?? '/'),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

// Route guards (backend enforces too).
router.beforeEach((to) => {
    const role = useAuthStore().user?.role;
    // Admin panel: admin or super_admin only.
    if (to.meta.admin && !['admin', 'super_admin'].includes(role)) {
        return { name: 'feed' };
    }
    // Shop admin panel: shop_admin, admin or super_admin.
    if (to.meta.shop && !['shop_admin', 'admin', 'super_admin'].includes(role)) {
        return { name: 'feed' };
    }
});

export default router;
