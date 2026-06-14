<script setup>
import { computed, inject } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const props = defineProps({ guest: { type: Boolean, default: false } });

const auth    = useAuthStore();
const router  = useRouter();
const gs      = inject('globalSettings', { assembly_nav_label: 'مشارکت', shop_enabled: true, shop_nav_label: 'فروشگاه' });

const items = computed(() => {
    const role = auth.user?.role;

    // A dedicated shop admin gets a focused, shop-centric nav.
    if (role === 'shop_admin') {
        return [
            { name: 'profile',    label: 'پروفایل', icon: '👤' },
            { name: 'orders',     label: 'سفارش‌ها', icon: '📦' },
            { name: 'shop',       label: gs.shop_nav_label ?? 'فروشگاه', icon: '🛍️' },
            { name: 'shop-admin', label: 'مدیریت', icon: '🛠️' },
        ];
    }

    const base = [
        { name: 'assembly', label: gs.assembly_nav_label ?? 'مشارکت', icon: '🏛️' },
        { name: 'profile',  label: 'پروفایل', icon: '👤' },
        { name: 'submit',   label: 'ثبت مشکل', icon: '➕' },
        { name: 'feed',     label: 'خانه', icon: '🏠' },
    ];
    if (gs.shop_enabled !== false) {
        base.splice(2, 0, { name: 'shop', label: gs.shop_nav_label ?? 'فروشگاه', icon: '🛍️' });
    }
    if (role === 'admin' || role === 'super_admin') {
        base.unshift({ name: 'admin', label: 'مدیریت', icon: '⚙️' });
    }
    return base;
});

function navigate(item) {
    // Guest clicking protected items → show login
    if (props.guest && ['submit', 'profile', 'assembly', 'orders'].includes(item.name)) {
        auth.status = 'web_login';
        return;
    }
    router.push({ name: item.name });
}
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-[480px] border-t border-slate-100 bg-white/95 backdrop-blur">
        <div class="grid px-2 py-2" :style="{ gridTemplateColumns: `repeat(${items.length}, 1fr)` }">
            <button
                v-for="item in items"
                :key="item.name"
                class="flex flex-col items-center gap-1 py-1 text-xs"
                :class="$route?.name === item.name ? 'text-blue-600' : 'text-slate-400'"
                @click="navigate(item)"
            >
                <span class="text-lg">{{ item.icon }}</span>
                <span>{{ item.label }}</span>
            </button>
        </div>
    </nav>
</template>
