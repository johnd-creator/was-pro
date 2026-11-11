<script setup lang="ts">
import { vendorExpiryStatus } from '@/lib/vendor-license';
defineProps<{
    vendor?: {
        license_number?: string | null;
        license_expiry_date?: string | null;
    };
    referenceDate: string;
}>();
</script>
<template>
    <div v-if="vendor" class="rounded border p-3 text-sm" role="status">
        <p>
            {{
                vendorExpiryStatus(vendor.license_expiry_date, referenceDate)
                    .text
            }}<span v-if="vendor.license_expiry_date">
                ({{ vendor.license_expiry_date.slice(0, 10) }})</span
            >.
        </p>
        <p v-if="!vendor.license_number">Nomor lisensi belum dicatat.</p>
        <p class="text-xs text-muted-foreground">
            Informasi tanggal tercatat terhadap
            {{ referenceDate || 'tanggal transaksi yang belum dipilih' }}. Bukan
            verifikasi legalitas dan tidak menghalangi penyimpanan.
        </p>
    </div>
</template>
