<script setup lang="ts">
import { computed } from 'vue';
import { formatFabaMovementType } from '@/lib/faba';
import type { FabaBalanceReconciliation } from '@/types/faba';

const props = defineProps<{
    summary: FabaBalanceReconciliation;
    periodLabel: string;
    yearly?: boolean;
}>();

const numberFormatter = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const rows = computed(() => {
    const result = [
        {
            key: 'opening',
            label: props.yearly ? 'Saldo awal Januari' : 'Saldo awal',
            effect: 'Saldo',
            fly_ash: props.summary.opening_fly_ash,
            bottom_ash: props.summary.opening_bottom_ash,
            total: props.summary.opening_balance,
        },
    ];
    for (const item of props.summary.balance_components ?? []) {
        const sign = item.stock_effect === 'out' ? -1 : 1;
        result.push({
            key: `${item.movement_type}-${item.stock_effect}`,
            label: formatFabaMovementType(item.movement_type),
            effect: item.stock_effect === 'out' ? 'Keluar (−)' : 'Masuk (+)',
            fly_ash: sign * item.fly_ash,
            bottom_ash: sign * item.bottom_ash,
            total: sign * item.total,
        });
    }
    if (props.yearly) {
        result.push({
            key: 'reset',
            label: 'Selisih penetapan ulang saldo awal',
            effect: 'Reset (+/−)',
            fly_ash: props.summary.opening_reset_fly_ash,
            bottom_ash: props.summary.opening_reset_bottom_ash,
            total: props.summary.opening_reset_total,
        });
    }
    result.push({
        key: 'closing',
        label: props.yearly ? 'Saldo akhir Desember' : 'Saldo akhir',
        effect: 'Saldo',
        fly_ash: props.summary.closing_fly_ash,
        bottom_ash: props.summary.closing_bottom_ash,
        total: props.summary.closing_balance,
    });
    return result;
});

function formatQuantity(value: number | undefined): string {
    return value === undefined
        ? '—'
        : numberFormatter.format(value === 0 ? 0 : value);
}
</script>

<template>
    <section
        v-if="summary.balance_components"
        class="wm-surface-subtle rounded-2xl p-4"
    >
        <h3 class="font-semibold text-slate-950 dark:text-slate-100">
            Rekonsiliasi saldo: {{ periodLabel }}
        </h3>
        <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">
            Saldo awal + seluruh arus masuk − seluruh arus keluar<span
                v-if="yearly"
            >
                + selisih penetapan ulang saldo awal</span
            >
            = saldo akhir. Angka dalam ton; arus keluar ditampilkan negatif.
        </p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[520px] text-left text-xs">
                <caption class="sr-only">
                    Komponen saldo FABA
                    {{
                        periodLabel
                    }}
                    dalam ton
                </caption>
                <thead class="border-b text-slate-600 dark:text-slate-300">
                    <tr>
                        <th scope="col" class="px-2 py-2">Komponen</th>
                        <th scope="col" class="px-2 py-2">Efek stok</th>
                        <th scope="col" class="px-2 py-2 text-right">
                            Fly Ash
                        </th>
                        <th scope="col" class="px-2 py-2 text-right">
                            Bottom Ash
                        </th>
                        <th scope="col" class="px-2 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-slate-700 dark:text-slate-200">
                    <tr
                        v-for="row in rows"
                        :key="row.key"
                        :class="{
                            'font-semibold':
                                row.key === 'closing' || row.key === 'opening',
                        }"
                    >
                        <th scope="row" class="px-2 py-2 font-medium">
                            {{ row.label }}
                        </th>
                        <td class="px-2 py-2">{{ row.effect }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">
                            {{ formatQuantity(row.fly_ash) }}
                        </td>
                        <td class="px-2 py-2 text-right tabular-nums">
                            {{ formatQuantity(row.bottom_ash) }}
                        </td>
                        <td class="px-2 py-2 text-right tabular-nums">
                            {{ formatQuantity(row.total) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p
            v-if="yearly"
            class="mt-3 text-xs leading-5 text-slate-600 dark:text-slate-300"
        >
            Selisih penetapan ulang adalah jumlah saldo awal Februari–Desember
            dikurangi saldo akhir bulan sebelumnya. Ini bukan produksi atau arus
            material.
        </p>
    </section>
</template>
