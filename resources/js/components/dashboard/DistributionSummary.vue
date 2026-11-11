<script setup lang="ts">
defineProps<{
    data: Array<{ label: string; value: number; percentage: number }>;
    unit: string;
    emptyLabel?: string;
    prominent?: boolean;
}>();

function format(value: number): string {
    return value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}
</script>

<template>
    <div v-if="prominent && data.length">
        <p
            class="text-3xl font-semibold tracking-tight text-slate-950 tabular-nums dark:text-white"
        >
            {{ format(data[0].value) }}
            <span class="text-sm font-normal text-slate-500">{{ unit }}</span>
        </p>
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
            {{ format(data[0].percentage) }}% dari saldo FABA
        </p>
    </div>
    <dl
        v-else-if="data.length"
        class="divide-y divide-slate-100 dark:divide-slate-800"
    >
        <div
            v-for="item in data"
            :key="item.label"
            class="flex items-baseline justify-between gap-4 py-3 first:pt-0 last:pb-0"
        >
            <dt class="min-w-0 text-sm text-slate-600 dark:text-slate-300">
                {{
                    item.label === 'Expired' ? 'Lewat batas simpan' : item.label
                }}
            </dt>
            <dd class="shrink-0 text-right">
                <span
                    class="text-lg font-semibold text-slate-950 tabular-nums dark:text-white"
                    >{{ format(item.value) }}</span
                >
                <span class="ml-1 text-xs text-slate-500">{{ unit }}</span>
                <span class="mt-0.5 block text-xs text-slate-500"
                    >{{ format(item.percentage) }}% dari total</span
                >
            </dd>
        </div>
    </dl>
    <p
        v-else
        class="rounded-xl bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-500 dark:bg-slate-950 dark:text-slate-400"
    >
        {{ emptyLabel ?? 'Belum ada data pada snapshot ini.' }}
    </p>
</template>
