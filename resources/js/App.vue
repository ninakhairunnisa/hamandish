<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from './stores/auth';
import LoginGate from './views/LoginGate.vue';
import BottomNav from './components/BottomNav.vue';

const auth = useAuthStore();
onMounted(() => auth.bootstrap());
</script>

<template>
    <div class="app-shell">
        <template v-if="auth.status === 'authenticated'">
            <router-view v-slot="{ Component }">
                <keep-alive include="Feed">
                    <component :is="Component" />
                </keep-alive>
            </router-view>
            <BottomNav />
        </template>

        <div v-else-if="auth.status === 'loading'" class="flex h-dvh items-center justify-center">
            <div class="h-10 w-10 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
        </div>

        <LoginGate v-else :status="auth.status" />
    </div>
</template>
