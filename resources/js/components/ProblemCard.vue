<script setup>
import { RouterLink } from 'vue-router';

defineProps({ problem: Object });

const statusLabels = {
    pending: 'در حال بررسی',
    approved: 'تأیید شده',
    rejected: 'رد شده',
};
</script>

<template>
    <RouterLink
        :to="{ name: 'problem', params: { id: problem.id } }"
        class="block rounded-3xl bg-white p-4 shadow-sm"
    >
        <div class="flex items-start gap-3">
            <div
                class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl text-2xl"
                :style="{ background: (problem.category?.color || '#3b82f6') + '22' }"
            >
                {{ problem.category?.icon ? '📌' : '📌' }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <h3 class="truncate font-bold text-slate-800">{{ problem.title }}</h3>
                    <span
                        v-if="problem.is_featured"
                        class="shrink-0 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] text-blue-600"
                    >منتخب</span>
                    <span
                        v-else
                        class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] text-amber-600"
                    >{{ statusLabels[problem.status] }}</span>
                </div>
                <p class="line-clamp-2 text-sm text-slate-500">{{ problem.description }}</p>
                <div class="mt-3 flex items-center gap-4 text-xs text-slate-400">
                    <span>💬 {{ problem.comments_count ?? 0 }}</span>
                    <span>👥 {{ problem.supports_count ?? 0 }}</span>
                    <span>💡 {{ problem.solutions_count ?? 0 }} راه‌حل</span>
                </div>
            </div>
        </div>
    </RouterLink>
</template>
