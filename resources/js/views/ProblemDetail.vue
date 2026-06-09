<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api';
import { messenger } from '../messenger';
import { useAuthStore } from '../stores/auth';
import { timeAgo, fullDate } from '../time';

const props = defineProps({ id: String });
const route = useRoute();
const auth = useAuthStore();

const problem = ref(null);
const solutions = ref([]);
const commentsEnabled = ref(true);
const newSolution = ref('');
const loading = ref(true);
const loadError = ref(null);
const submitting = ref(false);
const solutionError = ref(null);

// edit state (solutions and replies share it)
const editingId = ref(null);
const editingKind = ref(null); // 'solution' | 'reply'
const editText = ref('');
// reply state
const replyingTo = ref(null);
const replyText = ref('');

const statusLabel = { pending: 'در حال بررسی', approved: 'تأییدشده', rejected: 'ردشده' };

function isMine(item) {
    return item.user?.id === auth.user?.id;
}

function canEdit(item) {
    if (!isMine(item)) return false;
    return Date.now() - new Date(item.created_at).getTime() < 7 * 24 * 3600 * 1000;
}

async function load() {
    loading.value = true;
    loadError.value = null;
    try {
        const p = await api.get(`/problems/${props.id}`);
        problem.value = p.data.data ?? p.data;
    } catch (e) {
        loadError.value = e.response?.status === 404 ? 'مشکل یافت نشد.' : 'خطا در بارگذاری.';
        loading.value = false;
        return;
    }

    // Solutions may fail independently (e.g. pending problem) — never
    // leave the page stuck on the spinner.
    try {
        const s = await api.get(`/problems/${props.id}/solutions`);
        solutions.value = s.data.data;
    } catch (_) { solutions.value = []; }
    try {
        const st = await api.get('/settings');
        commentsEnabled.value = !!st.data.comments_enabled;
    } catch (_) { /* default on */ }

    loading.value = false;

    // Deep link from profile: scroll to the user's own solution/reply.
    const target = route.query.comment || route.query.solution;
    if (target) {
        await nextTick();
        document.getElementById(`item-${target}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

async function vote(solution, type) {
    messenger.haptic('light');
    try {
        const { data } = await api.post(`/solutions/${solution.id}/vote`, { type });
        solution.votes_count = data.votes_count;
    } catch (e) {
        alert(e.response?.data?.message || 'خطا در ثبت رأی.');
    }
}

async function toggleSupport() {
    const { data } = await api.post(`/problems/${problem.value.id}/support`);
    problem.value.supports_count = data.supports_count;
    problem.value.is_supported = data.supported;
}

const mySolutionExists = () => solutions.value.some((s) => isMine(s));

async function addSolution() {
    if (!newSolution.value.trim()) return;
    solutionError.value = null;
    submitting.value = true;
    try {
        const { data } = await api.post(`/problems/${problem.value.id}/solutions`, {
            content: newSolution.value,
        });
        solutions.value.unshift(data.data ?? data);
        newSolution.value = '';
    } catch (e) {
        solutionError.value = e.response?.data?.message || 'خطا در ثبت راه‌حل.';
    } finally {
        submitting.value = false;
    }
}

function startEdit(item, kind) {
    editingId.value = item.id;
    editingKind.value = kind;
    editText.value = item.content;
}

async function saveEdit(item) {
    const url = editingKind.value === 'solution' ? `/solutions/${item.id}` : `/comments/${item.id}`;
    try {
        const { data } = await api.patch(url, { content: editText.value });
        const updated = data.data ?? data;
        item.content = updated.content;
        item.edited_at = updated.edited_at;
        editingId.value = null;
    } catch (e) {
        alert(e.response?.data?.message || 'خطا در ویرایش.');
    }
}

async function sendReply(solution) {
    if (!replyText.value.trim()) return;
    try {
        const { data } = await api.post(`/solutions/${solution.id}/comments`, { content: replyText.value });
        solution.replies = solution.replies || [];
        solution.replies.push(data.data ?? data);
        replyingTo.value = null;
        replyText.value = '';
    } catch (e) {
        alert(e.response?.data?.message || 'خطا در ثبت پاسخ.');
    }
}

async function togglePin(solution) {
    try {
        const { data } = await api.patch(`/admin/solutions/${solution.id}/pin`, { is_pinned: !solution.is_pinned });
        solution.is_pinned = (data.data ?? data).is_pinned;
        solutions.value.sort((a, b) => (b.is_pinned - a.is_pinned) || (b.votes_count - a.votes_count));
    } catch (e) {
        alert(e.response?.data?.message || 'خطا');
    }
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="flex justify-center py-20">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
    </div>

    <div v-else-if="loadError" class="px-6 py-16 text-center">
        <p class="text-slate-500">{{ loadError }}</p>
        <button class="mt-4 text-sm text-blue-600" @click="$router.back()">← بازگشت</button>
    </div>

    <div v-else class="px-4 py-4 pb-28">
        <button class="mb-3 text-sm text-slate-500" @click="$router.back()">← بازگشت</button>

        <div class="rounded-3xl bg-white p-5 shadow-sm">
            <div class="mb-2 flex items-start justify-between gap-2">
                <h1 class="text-lg font-bold text-slate-800">{{ problem.title }}</h1>
                <span
                    v-if="problem.status !== 'approved'"
                    class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700"
                >{{ statusLabel[problem.status] }}</span>
            </div>
            <p class="text-sm leading-7 text-slate-600">{{ problem.description }}</p>
            <!-- name right, time left: no RTL/LTR collision for Latin names -->
            <div class="mt-3 flex items-center justify-between text-xs text-slate-400">
                <span dir="auto" class="font-medium">{{ problem.user?.display_name }}</span>
                <span>{{ fullDate(problem.created_at) }}</span>
            </div>
            <div class="mt-4 flex items-center gap-3">
                <button
                    class="flex items-center gap-1 rounded-full px-4 py-2 text-sm font-semibold active:scale-95"
                    :class="problem.is_supported ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'"
                    @click="toggleSupport"
                >
                    👥 حمایت ({{ problem.supports_count }})
                </button>
            </div>
        </div>

        <!-- راه‌حل‌ها -->
        <h2 class="mb-3 mt-6 font-bold text-slate-800">راه‌حل‌ها</h2>
        <div class="space-y-3">
            <div
                v-for="s in solutions"
                :key="s.id"
                :id="`item-${s.id}`"
                class="rounded-2xl p-4 shadow-sm"
                :class="s.user?.label ? 'bg-indigo-50 ring-1 ring-indigo-100' : 'bg-white'"
            >
                <!-- name (right) + badges | time (left) -->
                <div class="flex items-center justify-between gap-2 text-xs">
                    <span class="flex flex-wrap items-center gap-2">
                        <span v-if="s.is_pinned" title="سنجاق‌شده">📌</span>
                        <span dir="auto" class="font-bold text-slate-700">{{ s.user?.display_name }}</span>
                        <span
                            v-if="s.user?.label"
                            class="rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                        >{{ s.user.label }}</span>
                    </span>
                    <span class="shrink-0 text-slate-400">
                        {{ timeAgo(s.created_at) }}
                        <span v-if="s.edited_at" class="text-[10px]">(ویرایش‌شده {{ timeAgo(s.edited_at) }})</span>
                    </span>
                </div>

                <template v-if="editingId === s.id && editingKind === 'solution'">
                    <textarea
                        v-model="editText"
                        rows="3"
                        class="mt-2 w-full resize-none rounded-xl border border-slate-200 p-2 text-sm outline-none focus:border-blue-400"
                    ></textarea>
                    <div class="mt-1 flex gap-2 text-xs">
                        <button class="font-semibold text-blue-600" @click="saveEdit(s)">ذخیره</button>
                        <button class="text-slate-400" @click="editingId = null">انصراف</button>
                    </div>
                </template>
                <p v-else class="mt-2 text-sm leading-7 text-slate-700">{{ s.content }}</p>

                <div class="mt-3 flex items-center gap-2">
                    <button class="rounded-lg bg-emerald-50 px-3 py-1 text-emerald-600 active:scale-95" @click="vote(s, 1)">▲</button>
                    <span class="min-w-8 text-center font-bold text-slate-700">{{ s.votes_count }}</span>
                    <button class="rounded-lg bg-rose-50 px-3 py-1 text-rose-600 active:scale-95" @click="vote(s, -1)">▼</button>

                    <span class="mr-auto flex gap-3 text-xs">
                        <button
                            v-if="canEdit(s) && editingId !== s.id"
                            class="text-blue-600"
                            @click="startEdit(s, 'solution')"
                        >ویرایش</button>
                        <button
                            v-if="commentsEnabled && !isMine(s) && !(s.replies || []).some(isMine)"
                            class="text-slate-500"
                            @click="replyingTo = replyingTo === s.id ? null : s.id"
                        >پاسخ</button>
                        <button
                            v-if="auth.user?.role === 'admin'"
                            class="text-indigo-600"
                            @click="togglePin(s)"
                        >{{ s.is_pinned ? 'حذف سنجاق' : '📌 سنجاق' }}</button>
                    </span>
                </div>

                <!-- پاسخ‌ها به راه‌حل -->
                <div v-if="(s.replies || []).length" class="mt-3 space-y-2 border-r-2 border-slate-100 pr-3">
                    <div
                        v-for="r in s.replies"
                        :key="r.id"
                        :id="`item-${r.id}`"
                        class="rounded-xl p-3"
                        :class="r.user?.label ? 'bg-indigo-50 ring-1 ring-indigo-100' : 'bg-slate-50'"
                    >
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="flex flex-wrap items-center gap-2">
                                <span dir="auto" class="font-bold text-slate-700">{{ r.user?.display_name }}</span>
                                <span
                                    v-if="r.user?.label"
                                    class="rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                                >{{ r.user.label }}</span>
                            </span>
                            <span class="shrink-0 text-slate-400">
                                {{ timeAgo(r.created_at) }}
                                <span v-if="r.edited_at" class="text-[10px]">(ویرایش‌شده {{ timeAgo(r.edited_at) }})</span>
                            </span>
                        </div>
                        <template v-if="editingId === r.id && editingKind === 'reply'">
                            <textarea
                                v-model="editText"
                                rows="2"
                                class="mt-1 w-full resize-none rounded-xl border border-slate-200 p-2 text-sm outline-none focus:border-blue-400"
                            ></textarea>
                            <div class="mt-1 flex gap-2 text-xs">
                                <button class="font-semibold text-blue-600" @click="saveEdit(r)">ذخیره</button>
                                <button class="text-slate-400" @click="editingId = null">انصراف</button>
                            </div>
                        </template>
                        <p v-else class="mt-1 text-sm leading-6 text-slate-700">{{ r.content }}</p>
                        <button
                            v-if="canEdit(r) && editingId !== r.id"
                            class="mt-1 text-xs text-blue-600"
                            @click="startEdit(r, 'reply')"
                        >ویرایش</button>
                    </div>
                </div>

                <!-- فرم پاسخ -->
                <div v-if="replyingTo === s.id" class="mt-3">
                    <textarea
                        v-model="replyText"
                        rows="2"
                        placeholder="پاسخ شما به این راه‌حل…"
                        class="w-full resize-none rounded-xl border border-slate-200 p-2 text-sm outline-none focus:border-blue-400"
                    ></textarea>
                    <button class="mt-1 rounded-lg bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white" @click="sendReply(s)">ارسال پاسخ</button>
                </div>
            </div>
            <p v-if="!solutions.length" class="text-sm text-slate-400">هنوز راه‌حلی ثبت نشده. اولین نفر باشید!</p>
        </div>

        <!-- ثبت راه‌حل (هر کاربر یک راه‌حل) -->
        <div v-if="!mySolutionExists()" class="mt-4 rounded-2xl bg-white p-4 shadow-sm">
            <textarea
                v-model="newSolution"
                rows="3"
                placeholder="راه‌حل خود را بنویسید… (هر کاربر یک راه‌حل)"
                class="w-full resize-none rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-blue-400"
            ></textarea>
            <p v-if="solutionError" class="mt-1 text-xs text-rose-600">{{ solutionError }}</p>
            <button
                class="mt-2 w-full rounded-xl bg-blue-600 py-2.5 font-semibold text-white active:scale-95 disabled:opacity-50"
                :disabled="submitting"
                @click="addSolution"
            >ثبت راه‌حل</button>
        </div>
        <p v-else class="mt-4 text-center text-xs text-slate-400">
            شما راه‌حل خود را ثبت کرده‌اید — تا ۷ روز می‌توانید آن را ویرایش کنید.
        </p>
    </div>
</template>
