<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingsController';
import Heading from '@/components/Heading.vue';
import Link from '@/components/OrganizationLink.vue';
import { Button } from '@/components/ui/button';
import CreateWasteHaulingForm from '@/components/waste-management/CreateWasteHaulingForm.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import type { HaulingActualFormProps } from '@/types/hauling-plan';
const props = defineProps<HaulingActualFormProps>();
</script>
<template>
    <WasteManagementLayout
        title="Catat Realisasi Angkut"
        :breadcrumbs="[
            { title: 'Pengangkutan Limbah', href: index.url() },
            {
                title: 'Catat Realisasi',
                href: create.url({ query: { waste_record: props.record.id } }),
            },
        ]"
    >
        <Head title="Catat Realisasi Angkut" />
        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <Heading
                title="Catat realisasi angkut"
                description="Catat tanggal dan jumlah aktual setelah limbah keluar. Supervisor menyetujui realisasi sebelum saldo tercatat berkurang."
            />
            <CreateWasteHaulingForm v-bind="props" />
            <Button as-child variant="outline"
                ><Link :href="index.url()">Kembali</Link></Button
            >
        </div>
    </WasteManagementLayout>
</template>
