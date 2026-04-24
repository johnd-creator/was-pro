<script setup lang="ts">
import { computed } from 'vue';
import EmptyState from '@/components/dashboard/EmptyState.vue';
import wasteManagementRoutes from '@/routes/waste-management';

interface StatusData {
    status: string;
    count: number;
    color: string;
    percentage?: number;
}

interface Props {
    data: StatusData[];
}

const props = defineProps<Props>();

const isEmpty = computed(() => props.data.length === 0);

// Generate CSV data for export
const csvData = computed(() => {
    if (isEmpty.value) return '';

    const headers = ['Status', 'Jumlah', 'Persentase'];
    const rows = props.data.map((item) => [
        item.status,
        item.count,
        `${(item.percentage || 0).toFixed(1)}%`,
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
        `status-transportasi-${new Date().toISOString().split('T')[0]}.csv`,
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
            title="Tidak ada data transportasi"
            description="Belum ada pengiriman limbah yang tercatat. Jadwalkan transportasi pertama Anda."
            action-label="Jadwalkan transportasi"
            :action-href="wasteManagementRoutes.haulings.create().url"
            size="sm"
        />
    </div>
    <div v-else class="space-y-4">
        <!-- Visual chart representation -->
        <div
            v-for="item in data"
            :key="item.status"
            class="flex items-center gap-4"
        >
            <div class="flex-1">
                <div class="mb-1 flex items-center justify-between">
                    <span class="text-sm font-medium capitalize">{{
                        item.status
                    }}</span>
                    <span class="text-sm text-muted-foreground tabular-nums">{{
                        item.count
                    }}</span>
                </div>
                <div
                    class="h-2 w-full overflow-hidden rounded-full bg-secondary"
                >
                    <div
                        class="h-full transition-all duration-500"
                        :class="item.color"
                        :style="{ width: `${item.percentage || 0}%` }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Accessible data table for screen readers -->
        <table class="sr-only" aria-label="Data status transportasi">
            <caption>
                Distribusi status transportasi limbah. Total
                {{
                    data.length
                }}
                status.
            </caption>
            <thead>
                <tr>
                    <th scope="col">Status</th>
                    <th scope="col">Jumlah</th>
                    <th scope="col">Persentase</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in data" :key="item.status">
                    <td>{{ item.status }}</td>
                    <td>{{ item.count }}</td>
                    <td>{{ (item.percentage || 0).toFixed(1) }}%</td>
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
