<script setup lang="ts">
import {
    Activity,
    ArrowUpRight,
    CircleAlert,
    ClipboardList,
    ShieldAlert,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Link from '@/components/OrganizationLink.vue';

interface Props {
    title: string;
    value: string | number;
    unit?: string;
    href?: string;
    contextLabel?: string;
    hint?: string;
    color?: 'blue' | 'emerald' | 'orange' | 'red';
}
const props = withDefaults(defineProps<Props>(), { unit: '', color: 'blue' });
const icon = computed(
    () =>
        ({
            'Menunggu Ditinjau': ClipboardList,
            'Belum Terangkut': CircleAlert,
            'Melewati Batas Simpan': ShieldAlert,
        })[props.title] ?? Activity,
);
const tone = computed(
    () =>
        ({
            blue: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
            emerald:
                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
            orange: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
            red: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
        })[props.color],
);
const formattedValue = computed(() =>
    typeof props.value === 'number'
        ? props.value.toLocaleString('id-ID')
        : props.value,
);
</script>

<template>
    <component
        :is="href ? Link : 'article'"
        :href="href"
        :aria-label="
            href ? `${title}: ${formattedValue}. Lihat catatan` : undefined
        "
        :class="
            href
                ? 'group transition-colors hover:border-blue-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                : undefined
        "
        class="flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 dark:border-slate-800 dark:bg-slate-900"
    >
        <div class="flex items-start justify-between gap-3">
            <p
                class="text-xs font-medium text-slate-600 sm:text-sm dark:text-slate-300"
            >
                {{ title }}
            </p>
            <span
                class="flex size-7 shrink-0 items-center justify-center rounded-xl sm:size-9"
                :class="tone"
                ><component :is="icon" class="size-4" aria-hidden="true"
            /></span>
        </div>
        <p
            class="mt-2 text-3xl font-semibold tracking-tight break-words text-slate-950 tabular-nums dark:text-white"
        >
            {{ formattedValue
            }}<span
                v-if="unit"
                class="ml-1 text-sm font-normal text-slate-500"
                >{{ unit }}</span
            >
        </p>
        <p
            v-if="contextLabel"
            class="mt-2 text-xs font-medium text-slate-600 dark:text-slate-300"
        >
            {{ contextLabel }}
        </p>
        <p
            v-if="hint"
            class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
        >
            {{ hint }}
        </p>
        <span
            v-if="href"
            class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-blue-700 dark:text-blue-300"
            >Lihat catatan <ArrowUpRight class="size-3.5" aria-hidden="true"
        /></span>
    </component>
</template>
