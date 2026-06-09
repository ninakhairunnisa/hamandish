<script setup>
import { RouterLink } from 'vue-router';

const items = [
    { name: 'profile', label: 'پروفایل', icon: '👤' },
    { name: 'popular', label: 'مشکلات مردمی', icon: '📊' },
    { name: 'submit', label: 'ثبت مشکل', icon: '➕' },
    { name: 'feed', label: 'خانه', icon: '🏠' },
];
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-[480px] border-t border-slate-100 bg-white/95 backdrop-blur">
        <div class="grid grid-cols-4 px-2 py-2">
            <RouterLink
                v-for="item in items"
                :key="item.name"
                :to="{ name: item.name }"
                class="flex flex-col items-center gap-1 py-1 text-xs text-slate-400"
                active-class="text-blue-600"
            >
                <span class="text-lg">{{ item.icon }}</span>
                <span>{{ item.label }}</span>
            </RouterLink>
        </div>
    </nav>
</template>
