<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';

const tab = ref('pending'); // pending | problems | users | stats
const loading = ref(false);

const stats = ref(null);
const pending = ref([]);
const problems = ref([]);
const users = ref([]);
const statusFilter = ref('');
const search = ref('');
const message = ref(null);

function flash(text) {
    message.value = text;
    setTimeout(() => (message.value = null), 2500);
}

async function loadStats() {
    const { data } = await api.get('/admin/stats');
    stats.value = data;
}

async function loadPending() {
    const { data } = await api.get('/admin/problems/pending');
    pending.value = data.data;
}

async function loadProblems() {
    const params = {};
    if (statusFilter.value) params.status = statusFilter.value;
    if (search.value) params.search = search.value;
    const { data } = await api.get('/admin/problems', { params });
    problems.value = data.data;
}

async function loadUsers() {
    const params = search.value ? { search: search.value } : {};
    const { data } = await api.get('/admin/users', { params });
    users.value = data.data;
}

async function switchTab(name) {
    tab.value = name;
    search.value = '';
    loading.value = true;
    try {
        if (name === 'stats') await loadStats();
        if (name === 'pending') await loadPending();
        if (name === 'problems') await loadProblems();
        if (name === 'users') await loadUsers();
    } finally {
        loading.value = false;
    }
}

async function setStatus(problem, status) {
    await api.patch(`/admin/problems/${problem.id}/status`, { status });
    pending.value = pending.value.filter((p) => p.id !== problem.id);
    flash(status === 'approved' ? 'تأیید شد ✅' : 'رد شد ❌');
}

async function toggleFeatured(problem) {
    const { data } = await api.patch(`/admin/problems/${problem.id}/featured`, {
        is_featured: !problem.is_featured,
    });
    problem.is_featured = data.is_featured;
    flash(data.is_featured ? 'برگزیده شد ⭐' : 'از برگزیده‌ها خارج شد');
}

async function removeProblem(problem) {
    if (!confirm(`«${problem.title}» حذف شود؟`)) return;
    await api.delete(`/admin/problems/${problem.id}`);
    problems.value = problems.value.filter((p) => p.id !== problem.id);
    pending.value = pending.value.filter((p) => p.id !== problem.id);
    flash('حذف شد 🗑️');
}

async function toggleRole(user) {
    const role = user.role === 'admin' ? 'user' : 'admin';
    try {
        const { data } = await api.patch(`/admin/users/${user.id}/role`, { role });
        user.role = data.role;
        flash(role === 'admin' ? 'ادمین شد 👑' : 'ادمین حذف شد');
    } catch (err) {
        flash(err.response?.data?.message || 'خطا');
    }
}

const statusLabel = { pending: 'در انتظار', approved: 'تأییدشده', rejected: 'ردشده' };
const statusColor = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
};

onMounted(() => switchTab('pending'));
</script>

<template>
    <div class="pb-24">
        <header class="sticky top-0 z-10 bg-white/95 px-5 py-4 backdrop-blur">
            <h1 class="text-center text-lg font-bold text-slate-800">⚙️ مدیریت</h1>
            <div class="mt-3 grid grid-cols-4 gap-1 rounded-2xl bg-slate-100 p-1 text-xs">
                <button
                    v-for="t in [
                        { key: 'pending', label: 'در انتظار' },
                        { key: 'problems', label: 'مشکلات' },
                        { key: 'users', label: 'کاربران' },
                        { key: 'stats', label: 'آمار' },
                    ]"
                    :key="t.key"
                    class="rounded-xl py-2 font-semibold transition"
                    :class="tab === t.key ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500'"
                    @click="switchTab(t.key)"
                >
                    {{ t.label }}
                </button>
            </div>
        </header>

        <p
            v-if="message"
            class="fixed left-1/2 top-4 z-50 -translate-x-1/2 rounded-full bg-slate-800 px-4 py-2 text-sm text-white shadow-lg"
        >
            {{ message }}
        </p>

        <div v-if="loading" class="flex justify-center py-16">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
        </div>

        <div v-else class="px-4 py-4">
            <!-- آمار -->
            <div v-if="tab === 'stats' && stats" class="grid grid-cols-2 gap-3">
                <div
                    v-for="item in [
                        { label: 'کاربران', value: stats.users, icon: '👥' },
                        { label: 'کل مشکلات', value: stats.problems_total, icon: '📋' },
                        { label: 'در انتظار', value: stats.problems_pending, icon: '⏳' },
                        { label: 'تأییدشده', value: stats.problems_approved, icon: '✅' },
                        { label: 'ردشده', value: stats.problems_rejected, icon: '❌' },
                        { label: 'برگزیده', value: stats.problems_featured, icon: '⭐' },
                        { label: 'راه‌حل‌ها', value: stats.solutions, icon: '💡' },
                        { label: 'دیدگاه‌ها', value: stats.comments, icon: '💬' },
                        { label: 'حمایت‌ها', value: stats.supports, icon: '🤝' },
                    ]"
                    :key="item.label"
                    class="rounded-3xl bg-white p-4 text-center shadow-sm"
                >
                    <div class="text-2xl">{{ item.icon }}</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-800">{{ item.value }}</div>
                    <div class="text-xs text-slate-400">{{ item.label }}</div>
                </div>
            </div>

            <!-- در انتظار تأیید -->
            <div v-else-if="tab === 'pending'" class="space-y-3">
                <p v-if="!pending.length" class="py-10 text-center text-sm text-slate-400">
                    مشکلی در انتظار تأیید نیست. 🎉
                </p>
                <div v-for="p in pending" :key="p.id" class="rounded-3xl bg-white p-4 shadow-sm">
                    <p class="font-bold text-slate-800">{{ p.title }}</p>
                    <p class="mt-1 line-clamp-3 text-sm text-slate-500">{{ p.description }}</p>
                    <p class="mt-2 text-xs text-slate-400">
                        {{ p.user?.first_name || 'کاربر' }} · {{ p.category?.title || 'بدون دسته' }}
                    </p>
                    <div class="mt-3 flex gap-2">
                        <button
                            class="flex-1 rounded-xl bg-emerald-600 py-2 text-sm font-semibold text-white active:scale-95"
                            @click="setStatus(p, 'approved')"
                        >
                            تأیید
                        </button>
                        <button
                            class="flex-1 rounded-xl bg-rose-600 py-2 text-sm font-semibold text-white active:scale-95"
                            @click="setStatus(p, 'rejected')"
                        >
                            رد
                        </button>
                    </div>
                </div>
            </div>

            <!-- همه مشکلات -->
            <div v-else-if="tab === 'problems'" class="space-y-3">
                <div class="flex gap-2">
                    <input
                        v-model="search"
                        placeholder="جستجو…"
                        class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
                        @keyup.enter="loadProblems"
                    />
                    <select
                        v-model="statusFilter"
                        class="rounded-xl border border-slate-200 bg-white px-2 py-2 text-sm"
                        @change="loadProblems"
                    >
                        <option value="">همه</option>
                        <option value="pending">در انتظار</option>
                        <option value="approved">تأییدشده</option>
                        <option value="rejected">ردشده</option>
                    </select>
                </div>

                <p v-if="!problems.length" class="py-10 text-center text-sm text-slate-400">موردی یافت نشد.</p>
                <div v-for="p in problems" :key="p.id" class="rounded-3xl bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-bold text-slate-800">{{ p.title }}</p>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="statusColor[p.status]">
                            {{ statusLabel[p.status] }}
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        ⭐ {{ p.is_featured ? 'برگزیده' : '—' }} · 🤝 {{ p.supports_count }} · 💡
                        {{ p.solutions_count }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2 text-sm">
                        <button
                            class="rounded-xl bg-amber-100 px-3 py-1.5 font-semibold text-amber-700 active:scale-95"
                            @click="toggleFeatured(p)"
                        >
                            {{ p.is_featured ? 'حذف از برگزیده' : 'برگزیده کن' }}
                        </button>
                        <button
                            v-if="p.status !== 'approved'"
                            class="rounded-xl bg-emerald-100 px-3 py-1.5 font-semibold text-emerald-700 active:scale-95"
                            @click="setStatus(p, 'approved'); loadProblems()"
                        >
                            تأیید
                        </button>
                        <button
                            v-if="p.status !== 'rejected'"
                            class="rounded-xl bg-orange-100 px-3 py-1.5 font-semibold text-orange-700 active:scale-95"
                            @click="setStatus(p, 'rejected'); loadProblems()"
                        >
                            رد
                        </button>
                        <button
                            class="rounded-xl bg-rose-100 px-3 py-1.5 font-semibold text-rose-700 active:scale-95"
                            @click="removeProblem(p)"
                        >
                            حذف
                        </button>
                    </div>
                </div>
            </div>

            <!-- کاربران -->
            <div v-else-if="tab === 'users'" class="space-y-3">
                <input
                    v-model="search"
                    placeholder="جستجوی شماره یا نام…"
                    dir="rtl"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
                    @keyup.enter="loadUsers"
                />
                <div
                    v-for="u in users"
                    :key="u.id"
                    class="flex items-center justify-between rounded-3xl bg-white p-4 shadow-sm"
                >
                    <div>
                        <p class="font-bold text-slate-800">
                            {{ u.first_name || 'کاربر' }} {{ u.last_name || '' }}
                            <span v-if="u.role === 'admin'" class="text-xs">👑</span>
                        </p>
                        <p class="text-xs text-slate-400" dir="ltr">{{ u.phone }}</p>
                    </div>
                    <button
                        class="rounded-xl px-3 py-1.5 text-sm font-semibold active:scale-95"
                        :class="u.role === 'admin' ? 'bg-rose-100 text-rose-700' : 'bg-blue-100 text-blue-700'"
                        @click="toggleRole(u)"
                    >
                        {{ u.role === 'admin' ? 'حذف ادمین' : 'ادمین کن' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
