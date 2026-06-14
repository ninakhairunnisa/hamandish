<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import { toman } from '../money';
import { fullDate } from '../time';
import { compressImage } from '../image';

defineOptions({ name: 'MyOrders' });

const router = useRouter();
const orders = ref([]);
const loading = ref(true);

const statusLabel = {
    pending:   'در انتظار بررسی',
    confirmed: 'تأیید شده',
    shipping:  'در حال ارسال',
    delivered: 'تحویل شده',
    canceled:  'لغو شده',
};
const statusColor = {
    pending:   'bg-amber-100 text-amber-700',
    confirmed: 'bg-blue-100 text-blue-700',
    shipping:  'bg-indigo-100 text-indigo-700',
    delivered: 'bg-emerald-100 text-emerald-700',
    canceled:  'bg-rose-100 text-rose-700',
};

async function load() {
    try {
        const { data } = await api.get('/orders');
        orders.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function uploadReceipt(order, e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const compressed = await compressImage(file);
    const fd = new FormData();
    fd.append('receipt', compressed);
    try {
        const { data } = await api.post(`/orders/${order.id}/receipt`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
        Object.assign(order, data);
    } catch (_) { /* ignore */ }
}

onMounted(load);
</script>

<template>
    <div class="min-h-dvh bg-slate-50 pb-24">
        <header class="sticky top-0 z-10 flex items-center gap-3 bg-white/95 px-5 py-4 backdrop-blur shadow-sm">
            <button @click="router.back()" class="text-xl text-slate-500">←</button>
            <h1 class="font-bold text-slate-800">📦 سفارش‌های من</h1>
        </header>

        <div v-if="loading" class="flex justify-center py-20">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
        </div>

        <div v-else class="space-y-3 px-4 py-4">
            <p v-if="!orders.length" class="py-16 text-center text-sm text-slate-400">هنوز سفارشی ثبت نکرده‌اید.</p>
            <div v-for="o in orders" :key="o.id" class="rounded-3xl bg-white p-4 shadow-sm space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-800">سفارش #{{ o.id }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="statusColor[o.status]">{{ statusLabel[o.status] }}</span>
                </div>
                <div v-for="it in o.items" :key="it.id" class="flex justify-between text-xs text-slate-500">
                    <span>{{ it.product_name }} × {{ it.quantity }}</span>
                    <span>{{ toman(it.unit_price * it.quantity) }}</span>
                </div>
                <div class="flex justify-between border-t border-slate-100 pt-2 text-sm font-bold text-slate-800">
                    <span>جمع کل</span><span>{{ toman(o.total_amount) }}</span>
                </div>
                <p class="text-xs text-slate-400">{{ o.payment_method === 'cod' ? '💵 پرداخت در محل' : '🏦 واریز به حساب' }} · {{ fullDate(o.created_at) }}</p>
                <p v-if="o.admin_note" class="text-xs text-rose-600">یادداشت فروشگاه: {{ o.admin_note }}</p>

                <!-- Receipt for transfer orders -->
                <div v-if="o.payment_method === 'transfer'" class="pt-1">
                    <a v-if="o.receipt_url" :href="o.receipt_url" target="_blank" class="text-xs text-blue-600 underline">مشاهده رسید ارسالی</a>
                    <label v-if="['pending','confirmed'].includes(o.status)" class="block w-full cursor-pointer rounded-xl border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-center text-xs text-amber-700">
                        {{ o.receipt_url ? '🔄 تغییر عکس رسید' : '📎 ارسال عکس رسید' }}
                        <input type="file" accept="image/*" class="hidden" @change="(e) => uploadReceipt(o, e)" />
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>
