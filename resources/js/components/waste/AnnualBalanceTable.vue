<script setup lang="ts">
export interface AnnualRow {
    waste_type: string;
    code: string;
    unit: string;
    sources: string;
    opening: number;
    incoming: number;
    outgoing: number;
    closing: number;
    months: Array<{ incoming: number; outgoing: number }>;
    balance_percent: number | null;
    anomaly_count: number;
}
defineProps<{ rows: AnnualRow[]; year: number }>();
const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];
const activities = ['incoming', 'utilized', 'outgoing'] as const;
const labels = {
    incoming: 'Masuk',
    utilized: 'Manfaatkan',
    outgoing: 'Keluar',
};
const number = (value: number) =>
    value.toLocaleString('id-ID', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    });
</script>
<template>
    <div>
        <div
            class="overflow-x-auto rounded-lg border"
            tabindex="0"
            role="region"
            :aria-label="`Neraca Limbah B3 tahun ${year}, geser untuk melihat semua bulan`"
        >
            <table class="w-full border-collapse text-xs whitespace-nowrap">
                <caption class="sr-only">
                    Neraca tahunan berdasarkan transaksi disetujui. Massa dalam
                    ton, satuan lain dipisahkan.
                </caption>
                <thead class="bg-muted">
                    <tr>
                        <th rowspan="2">Kode / Jenis limbah</th>
                        <th rowspan="2">Sumber</th>
                        <th rowspan="2">Satuan</th>
                        <th rowspan="2">Sisa {{ year - 1 }}</th>
                        <th rowspan="2">Aktivitas</th>
                        <th colspan="12">Jumlah per bulan</th>
                        <th colspan="4">Total tahun {{ year }}</th>
                        <th rowspan="2">Balancing</th>
                        <th rowspan="2">Temuan</th>
                    </tr>
                    <tr>
                        <th v-for="month in months" :key="month">
                            {{ month }}
                        </th>
                        <th>Masuk</th>
                        <th>Manfaatkan</th>
                        <th>Keluar</th>
                        <th>Simpan</th>
                    </tr>
                </thead>
                <tbody>
                    <template
                        v-for="row in rows"
                        :key="`${row.code}-${row.unit}`"
                    >
                        <tr
                            v-for="(activity, index) in activities"
                            :key="activity"
                            :class="
                                activity === 'incoming'
                                    ? 'bg-amber-50/60 dark:bg-amber-950/20'
                                    : activity === 'outgoing'
                                      ? 'bg-orange-50/60 dark:bg-orange-950/20'
                                      : 'bg-emerald-50/60 dark:bg-emerald-950/20'
                            "
                        >
                            <th
                                v-if="index === 0"
                                rowspan="3"
                                class="text-left"
                            >
                                <span class="block">{{ row.code }}</span
                                ><span class="font-normal">{{
                                    row.waste_type
                                }}</span>
                            </th>
                            <td
                                v-if="index === 0"
                                rowspan="3"
                                class="max-w-48 whitespace-normal"
                            >
                                {{ row.sources }}
                            </td>
                            <td v-if="index === 0" rowspan="3">
                                {{ row.unit }}
                            </td>
                            <td v-if="index === 0" rowspan="3">
                                {{ number(row.opening) }}
                            </td>
                            <th class="text-left">{{ labels[activity] }}</th>
                            <td
                                v-for="(month, monthIndex) in row.months"
                                :key="monthIndex"
                                class="text-right tabular-nums"
                            >
                                {{
                                    activity === 'utilized'
                                        ? '-'
                                        : number(month[activity])
                                }}
                            </td>
                            <template v-if="index === 0"
                                ><td rowspan="3">{{ number(row.incoming) }}</td>
                                <td
                                    rowspan="3"
                                    title="Pemanfaatan belum dipilah, bukan nol"
                                >
                                    -
                                </td>
                                <td rowspan="3">{{ number(row.outgoing) }}</td>
                                <td rowspan="3">{{ number(row.closing) }}</td>
                                <td rowspan="3">
                                    {{
                                        row.balance_percent === null
                                            ? '-'
                                            : `${row.balance_percent.toLocaleString('id-ID')}%`
                                    }}
                                </td>
                                <td rowspan="3">
                                    {{ row.anomaly_count || '-' }}
                                </td></template
                            >
                        </tr>
                    </template>
                    <tr v-if="!rows.length">
                        <td
                            colspan="23"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Tidak ada transaksi disetujui atau saldo awal untuk
                            tahun ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-muted-foreground">
            Manfaatkan: - = belum dipilah, bukan nol. Kg dikonversi ke ton;
            satuan lain tetap terpisah.
        </p>
    </div>
</template>
<style scoped>
th,
td {
    padding: 0.6rem;
    border: 1px solid var(--border);
}
td {
    font-variant-numeric: tabular-nums;
}
</style>
