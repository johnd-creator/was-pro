<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/WasteManagement/MasterData/FabaMastersController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
interface Master {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    movements_count: number;
}
const props = defineProps<{
    kind: string;
    items: Master[];
    canCreate: boolean;
    canEdit: boolean;
}>();
const { scopedUrl } = useOrganizationContext();
const selected = ref<string | null>(null);
const form = useForm({ name: '', slug: '', description: '', is_active: true });
const title =
    props.kind === 'destinations' ? 'Tujuan Internal FABA' : 'Peruntukan FABA';
function edit(item: Master): void {
    selected.value = item.id;
    form.name = item.name;
    form.slug = item.slug;
    form.description = item.description ?? '';
    form.is_active = item.is_active;
    form.clearErrors();
}
function reset(): void {
    selected.value = null;
    form.reset();
    form.clearErrors();
}
function save(): void {
    const options = { preserveScroll: true, onSuccess: reset };
    if (selected.value)
        form.put(
            scopedUrl(update.url({ kind: props.kind, record: selected.value })),
            options,
        );
    else form.post(scopedUrl(store.url(props.kind)), options);
}
</script>
<template>
    <WasteManagementLayout
        :title="title"
        :breadcrumbs="[{ title, href: index.url(kind) }]"
    >
        <Head :title="title" />
        <div class="space-y-4 p-4">
            <Heading :title="title" />
            <form
                v-if="canCreate || (selected && canEdit)"
                class="grid max-w-2xl gap-3 rounded border p-4"
                @submit.prevent="save"
            >
                <Label for="master-name">Nama</Label
                ><Input id="master-name" v-model="form.name" required />
                <Label for="master-slug">Kode permanen</Label
                ><Input
                    id="master-slug"
                    v-model="form.slug"
                    :disabled="!!selected"
                    required
                />
                <Label for="master-description">Keterangan</Label
                ><Input id="master-description" v-model="form.description" />
                <label class="flex gap-2"
                    ><input v-model="form.is_active" type="checkbox" />
                    Aktif</label
                >
                <p
                    v-for="(error, field) in form.errors"
                    :key="field"
                    class="text-sm text-destructive"
                >
                    {{ error }}
                </p>
                <div class="flex gap-2">
                    <Button :disabled="form.processing">{{
                        selected ? 'Simpan perubahan' : 'Tambah'
                    }}</Button
                    ><Button type="button" variant="outline" @click="reset"
                        >Batal</Button
                    >
                </div>
            </form>
            <div class="overflow-x-auto rounded border">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th class="p-3">Nama / Kode</th>
                            <th>Status</th>
                            <th>Transaksi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in items"
                            :key="item.id"
                            class="border-t"
                        >
                            <td class="p-3">
                                {{ item.name }}
                                <p class="text-xs text-muted-foreground">
                                    {{ item.slug }}
                                </p>
                            </td>
                            <td>{{ item.is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>{{ item.movements_count }}</td>
                            <td>
                                <Button
                                    v-if="canEdit"
                                    variant="outline"
                                    @click="edit(item)"
                                    >Ubah</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="items.length === 0">
                            <td colspan="4" class="p-4">
                                Belum ada data master.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </WasteManagementLayout>
</template>
