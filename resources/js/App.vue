<script setup>
import { ref, onMounted, provide } from 'vue';
import { useAuthStore } from './stores/auth';
import LoginGate from './views/LoginGate.vue';
import BottomNav from './components/BottomNav.vue';
import api from './api';

const auth = useAuthStore();

const globalSettings = ref({
    guest_can_view:        true,
    assembly_nav_label:    'مشارکت',
    assembly_section_title:'مشارکت در مجمع مردم مبعوث شده',
    comments_enabled:      true,
    shop_enabled:          true,
    shop_nav_label:        'فروشگاه',
    shop_bank_card:        '',
    shop_bank_holder:      '',
});
provide('globalSettings', globalSettings);

onMounted(async () => {
    try {
        const { data } = await api.get('/settings');
        Object.assign(globalSettings.value, data);
    } catch (_) { /* use defaults */ }
    await auth.bootstrap();
});

// Show app shell for guest mode (settings may still be loading — show loading until settings fetched)
</script>

<template>
    <div class="app-shell">
        <!-- Authenticated -->
        <template v-if="auth.status === 'authenticated'">
            <router-view v-slot="{ Component }">
                <keep-alive include="Feed">
                    <component :is="Component" />
                </keep-alive>
            </router-view>
            <BottomNav />
        </template>

        <!-- Guest mode: show public content without login -->
        <template v-else-if="globalSettings.guest_can_view && ['web_login', 'need_contact', 'error'].includes(auth.status)">
            <router-view v-slot="{ Component }">
                <component :is="Component" />
            </router-view>
            <BottomNav :guest="true" />
        </template>

        <!-- Loading -->
        <div v-else-if="auth.status === 'loading' || auth.status === 'idle'" class="flex h-dvh items-center justify-center">
            <div class="h-10 w-10 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
        </div>

        <!-- Login gate (non-guest mode) -->
        <LoginGate v-else :status="auth.status" />
    </div>
</template>
