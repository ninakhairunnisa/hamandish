<script setup>
import { useRouter } from 'vue-router';
import { useCartStore } from '../stores/cart';
import { useAuthStore } from '../stores/auth';
import { toman } from '../money';

defineOptions({ name: 'Cart' });

const router = useRouter();
const cart   = useCartStore();
const auth   = useAuthStore();

function checkout() {
    if (!auth.isAuthenticated) {
        auth.status = 'web_login';
        return;
    }
    router.push({ name: 'checkout' });
}
</script>

<template>
    <div class="min-h-dvh bg-slate-50 pb-24">
        <header class="sticky top-0 z-10 flex items-center gap-3 bg-white/95 px-5 py-4 backdrop-blur shadow-sm">
            <button @click="router.back()" class="text-xl text-slate-500">←</button>
            <h1 class="font-bold text-slate-800">🛒 سبد خرید</h1>
        </header>

        <div v-if="cart.isEmpty" class="flex flex-col items-center gap-3 py-24 text-slate-400">
            <span class="text-5xl">🛒</span>
            <p class="text-sm">سبد خرید شما خالی است.</p>
            <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="router.push({ name: 'shop' })">رفتن به فروشگاه</button>
        </div>

        <div v-else class="space-y-3 px-4 py-4">
            <div v-for="item in cart.items" :key="item.id" class="flex gap-3 rounded-3xl bg-white p-3 shadow-sm">
                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-slate-100">
                    <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full items-center justify-center text-2xl text-slate-300">📦</div>
                </div>
                <div class="flex flex-1 flex-col justify-between">
                    <div class="flex items-start justify-between">
                        <p class="line-clamp-1 text-sm font-bold text-slate-800">{{ item.name }}</p>
                        <button class="text-xs text-rose-500" @click="cart.remove(item.id)">حذف</button>
                    </div>
                    <p class="text-sm font-extrabold text-blue-600">{{ toman(item.price) }}</p>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center rounded-lg border border-slate-200">
                            <button class="px-2 py-1 text-slate-500" @click="cart.setQuantity(item.id, item.quantity - 1)">−</button>
                            <span class="w-8 text-center text-sm font-bold">{{ item.quantity }}</span>
                            <button class="px-2 py-1 text-slate-500" @click="cart.setQuantity(item.id, item.quantity + 1)">+</button>
                        </div>
                        <span class="text-xs text-slate-500">{{ toman(item.price * item.quantity) }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">جمع کل</span>
                    <span class="text-lg font-extrabold text-slate-800">{{ toman(cart.total) }}</span>
                </div>
                <button class="mt-4 w-full rounded-xl bg-blue-600 py-3 text-sm font-semibold text-white active:scale-95" @click="checkout">
                    ادامه و ثبت سفارش
                </button>
            </div>
        </div>
    </div>
</template>
