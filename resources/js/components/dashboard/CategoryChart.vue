<script setup lang="ts">
import { computed } from 'vue';
import EmptyState from '@/components/dashboard/EmptyState.vue';
import wasteManagementRoutes from '@/routes/waste-management';

interface CategoryData {
    category: string;
    count: number;
    percentage: number;
}

interface Props {
    data: CategoryData[];
}

const props = defineProps<Props>();

const isEmpty = computed(() => props.data.length === 0);

const chartColors = [
    '#2563eb',
    '#10b981',
    '#f97316',
    '#8b5cf6',
    '#eab308',
    '#ef4444',
];

const segments = computed(() => {
    const total = totalCount.value;
    let start = 0;

    return props.data.map((item, index) => {
        const color = chartColors[index % chartColors.length];
        const value = total > 0 ? (item.count / total) * 100 : 0;
        const end = start + value;
        const segment = {
            ...item,
            color,
            normalizedPercentage: value,
            start,
            end,
        };

        start = end;

        return segment;
    });
});

const donutStyle = computed(() => {
    if (isEmpty.value) {
        return {
            background: 'conic-gradient(#e5e7eb 0 100%)',
        };
    }

    const gradient = segments.value
        .map((segment) => {
            return `${segment.color} ${segment.start}% ${segment.end}%`;
        })
        .join(', ');

    return {
        background: `conic-gradient(${gradient})`,
    };
});

const totalCount = computed(() =>
    props.data.reduce((sum, item) => sum + item.count, 0),
);

// Generate CSV data for export
const csvData = computed(() => {
    if (isEmpty.value) return '';

    const headers = ['Kategori', 'Jumlah', 'Persentase'];
    const rows = props.data.map((item) => [
        item.category,
        item.count,
        `${item.percentage.toFixed(1)}%`,
    ]);

    return [headers, ...rows].map((row) => row.join(',')).join('\n');
});

// Download CSV function
function downloadCSV() {
    if (isEmpty.value) return;

    const blob = new Blob([csvData.value], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute(
        'download',
        `kategori-limbah-${new Date().toISOString().split('T')[0]}.csv`,
    );
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<template>
    <div v-if="isEmpty" class="space-y-4">
        <EmptyState
            type="no-data"
            title="Tidak ada data kategori"
            description="Belum ada catatan limbah yang tercatat. Mulai dengan membuat catatan limbah baru."
            action-label="Buat catatan limbah"
            :action-href="wasteManagementRoutes.records.create().url"
            size="sm"
        />
    </div>
    <div v-else class="space-y-4">
        <div class="grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-center">
            <div class="flex justify-center">
                <div
                    class="relative flex h-48 w-48 items-center justify-center rounded-full"
                    :style="donutStyle"
                >
                    <div class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-background text-center shadow-sm">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                            Total
                        </span>
                        <span class="mt-1 text-3xl font-semibold text-foreground tabular-nums">
                            {{ totalCount }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            catatan
                        </span>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-for="item in segments"
                    :key="item.category"
                    class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5"
                >
                    <div
                        class="h-3.5 w-3.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: item.color }"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-foreground">
                            {{ item.category }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ item.count }} catatan
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold tabular-nums text-foreground">
                            {{ item.normalizedPercentage.toFixed(1) }}%
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accessible data table for screen readers -->
        <table class="sr-only" aria-label="Data kategori limbah">
            <caption>
                Distribusi limbah berdasarkan kategori. Total
                {{
                    data.length
                }}
                kategori.
            </caption>
            <thead>
                <tr>
                    <th scope="col">Kategori</th>
                    <th scope="col">Jumlah</th>
                    <th scope="col">Persentase</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in data" :key="item.category">
                    <td>{{ item.category }}</td>
                    <td>{{ item.count }}</td>
                    <td>{{ ((item.count / totalCount) * 100).toFixed(1) }}%</td>
                </tr>
            </tbody>
        </table>

        <!-- CSV Export button -->
        <button
            v-if="!isEmpty"
            type="button"
            class="text-xs text-muted-foreground underline hover:text-foreground"
            @click="downloadCSV"
        >
            Download data sebagai CSV
        </button>
    </div>
</template>
