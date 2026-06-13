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
];

// VITE_APP_BASE sets the router base path.
// On shared hosting at /hamandish/ set: VITE_APP_BASE=/hamandish/
const router = createRouter({
    history: createWebHistory(import.meta.env.VITE_APP_BASE ?? '/'),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

// Admin routes are only reachable by admin users (backend enforces too).
router.beforeEach((to) => {
    if (to.meta.admin && useAuthStore().user?.role !== 'admin') {
        return { name: 'feed' };
    }
});

export default router;
