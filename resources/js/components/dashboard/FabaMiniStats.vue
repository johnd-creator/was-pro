<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

interface Props {
    totalProduction: number;
    totalUtilization: number;
    balance: number;
    negativePeriods: number;
}

const props = defineProps<Props>();

const balanceStatus = computed(() => {
    if (props.balance < 0) return 'text-red-600 dark:text-red-400';
    if (props.negativePeriods > 0) return 'text-orange-600 dark:text-orange-400';
    return 'text-emerald-600 dark:text-emerald-400';
});
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Production -->
        <Card>
            <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground uppercase">
                    Produksi FABA
                </p>
                <p class="mt-1 text-2xl font-bold tabular-nums">
                    {{ totalProduction }}
                </p>
                <p class="text-xs text-muted-foreground">Total tahun ini</p>
            </CardContent>
        </Card>

        <!-- Total Utilization -->
        <Card>
            <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground uppercase">
                    Pemanfaatan FABA
                </p>
                <p class="mt-1 text-2xl font-bold tabular-nums">
                    {{ totalUtilization }}
                </p>
                <p class="text-xs text-muted-foreground">Total tahun ini</p>
            </CardContent>
        </Card>

        <!-- Current Balance -->
        <Card>
            <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground uppercase">
                    Saldo TPS
                </p>
                <p
                    class="mt-1 text-2xl font-bold tabular-nums"
                    :class="balanceStatus"
                >
                    {{ balance }}
                </p>
                <p class="text-xs text-muted-foreground">ton</p>
            </CardContent>
        </Card>

        <!-- Negative Periods -->
        <Card>
            <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground uppercase">
                    Periode Negatif
                </p>
                <p
                    class="mt-1 text-2xl font-bold tabular-nums"
                    :class="negativePeriods > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'"
                >
                    {{ negativePeriods }}
                </p>
                <p class="text-xs text-muted-foreground">periode bermasalah</p>
            </CardContent>
        </Card>
    </div>
</template>
