import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    { path: '/', name: 'feed', component: () => import('./views/Feed.vue') },
    { path: '/problems/:id', name: 'problem', component: () => import('./views/ProblemDetail.vue'), props: true },
    { path: '/submit', name: 'submit', component: () => import('./views/SubmitProblem.vue') },
    { path: '/popular', name: 'popular', component: () => import('./views/Popular.vue') },
    { path: '/profile', name: 'profile', component: () => import('./views/Profile.vue') },
];

export default createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});
