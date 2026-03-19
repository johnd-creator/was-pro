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

const maxCount = computed(() => {
    return Math.max(...props.data.map((item: CategoryData) => item.count), 1);
});

const isEmpty = computed(() => props.data.length === 0);

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
        <!-- Visual chart representation -->
        <div v-for="item in data" :key="item.category" class="space-y-2">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium">{{ item.category }}</span>
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground tabular-nums">{{
                        item.count
                    }}</span>
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >({{ item.percentage.toFixed(1) }}%)</span
                    >
                </div>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                <div
                    class="h-full bg-primary transition-all duration-500"
                    :style="{ width: `${(item.count / maxCount) * 100}%` }"
                ></div>
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
                    <td>{{ item.percentage.toFixed(1) }}%</td>
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
