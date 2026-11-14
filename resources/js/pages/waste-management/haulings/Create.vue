<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingsController';
import Link from '@/components/OrganizationLink.vue';
import { Button } from '@/components/ui/button';
import Heading from '@/components/waste-management/CompactPageHeading.vue';
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
        <div class="min-w-0 space-y-4 p-4">
            <Heading title="Catat realisasi angkut" />
            <CreateWasteHaulingForm v-bind="props" />
            <Button as-child variant="outline"
                ><Link :href="index.url()">Kembali</Link></Button
            >
        </div>
    </WasteManagementLayout>
</template>
