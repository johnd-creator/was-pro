<script setup lang="ts">
import {
    Activity,
    CheckCircle2,
    CircleAlert,
    Factory,
    PackageCheck,
    Scale,
    ShieldAlert,
    ClipboardList,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

interface Props {
    title: string;
    value: string | number;
    unit?: string;
    href?: string;
    contextLabel?: string;
    hint?: string;
    color?: 'blue' | 'emerald' | 'orange' | 'red';
}

const props = withDefaults(defineProps<Props>(), {
    unit: '',
    color: 'blue',
});

const accentClasses = computed(() => {
    const colors = {
        blue: {
            shell: 'wm-panel border',
            title: 'wm-text-secondary',
            value: 'wm-text-primary',
            unit: 'wm-text-muted',
            icon: 'text-blue-600 dark:text-blue-300',
        },
        emerald: {
            shell: 'wm-panel border',
            title: 'wm-text-secondary',
            value: 'wm-text-primary',
            unit: 'wm-text-muted',
            icon: 'text-emerald-600 dark:text-emerald-300',
        },
        orange: {
            shell: 'wm-panel border',
            title: 'wm-text-secondary',
            value: 'wm-text-primary',
            unit: 'wm-text-muted',
            icon: 'text-amber-600 dark:text-amber-300',
        },
        red: {
            shell: 'wm-panel border',
            title: 'wm-text-secondary',
            value: 'wm-text-primary',
            unit: 'wm-text-muted',
            icon: 'text-red-600 dark:text-red-300',
        },
    };

    return colors[props.color];
});

const icon = computed(() => {
    const map: Record<string, typeof Activity> = {
        'Total Limbah': Activity,
        'Total Catatan Limbah': Activity,
        'Limbah Terangkut': PackageCheck,
        'Belum Terangkut': CircleAlert,
        'Menunggu Ditinjau': ClipboardList,
        'Melewati Batas Simpan': ShieldAlert,
        'FABA Produksi': Factory,
        'FABA Pemanfaatan': CheckCircle2,
        'Saldo FABA': Scale,
    };

    return map[props.title] ?? Activity;
});
</script>

<template>
    <Card
        :class="[
            'group relative overflow-hidden rounded-2xl border shadow-sm transition-colors duration-200 hover:border-slate-300 dark:hover:border-slate-700',
            accentClasses.shell,
        ]"
    >
        <div
            class="absolute inset-x-0 top-0 h-0.5"
            :class="{
                'bg-blue-500/80 dark:bg-blue-400/80': color === 'blue',
                'bg-emerald-500/80 dark:bg-emerald-400/80': color === 'emerald',
                'bg-amber-500/80 dark:bg-amber-400/80': color === 'orange',
                'bg-red-500/85 dark:bg-red-400/85': color === 'red',
            }"
        />
        <CardContent
            class="relative flex h-full min-h-[126px] flex-col gap-3 p-4"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-2.5">
                    <p
                        :class="[
                            'text-sm font-medium tracking-tight',
                            accentClasses.title,
                        ]"
                    >
                        {{ title }}
                    </p>
                    <p
                        :class="[
                            'text-3xl font-bold tracking-tight tabular-nums',
                            accentClasses.value,
                        ]"
                    >
                        {{ value }}
                        <span
                            v-if="unit"
                            :class="[
                                'ml-1 text-sm font-medium',
                                accentClasses.unit,
                            ]"
                        >
                            {{ unit }}
                        </span>
                    </p>
                </div>

                <div
                    :class="[
                        'wm-surface-subtle flex size-9 shrink-0 items-center justify-center rounded-xl border',
                    ]"
                >
                    <component
                        :is="icon"
                        class="size-4 shrink-0"
                        :class="accentClasses.icon"
                    />
                </div>
            </div>

            <div
                v-if="contextLabel || hint"
                class="mt-auto flex flex-wrap items-center gap-x-2 gap-y-1 text-xs"
            >
                <span v-if="contextLabel" class="wm-text-secondary font-medium">
                    {{ contextLabel }}
                </span>
                <span v-if="hint" class="wm-text-muted">
                    {{ hint }}
                </span>
            </div>
        </CardContent>
    </Card>
</template>
