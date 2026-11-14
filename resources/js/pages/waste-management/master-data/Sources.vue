<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
type Source = { id: string; name: string; is_active: boolean };
defineProps<{
    sources: Source[];
    available: boolean;
    abilities: { create: boolean; edit: boolean };
}>();
const { scopedUrl } = useOrganizationContext();
const editing = ref<string | null>(null);
const form = useForm({ name: '', is_active: true });
function edit(source: Source) {
    editing.value = source.id;
    form.name = source.name;
    form.is_active = source.is_active;
    form.clearErrors();
}
function cancel() {
    editing.value = null;
    form.reset();
    form.clearErrors();
}
function save() {
    const options = { preserveScroll: true, onSuccess: cancel };
    if (editing.value)
        form.put(
            scopedUrl(`/waste-management/master-data/sources/${editing.value}`),
            options,
        );
    else form.post(scopedUrl('/waste-management/master-data/sources'), options);
}
</script>
<template>
    <Head title="Sumber Limbah" />
    <WasteManagementLayout title="Sumber Limbah">
        <div class="space-y-4 p-4">
            <h1 class="text-xl font-semibold">Sumber Limbah</h1>
            <p class="text-sm text-muted-foreground">
                Master sumber untuk organisasi aktif. Menonaktifkan sumber tidak
                menghapus riwayat catatan.
            </p>
            <p v-if="!available" role="status" class="rounded border p-3">
                Master sumber belum diaktifkan untuk organisasi ini.
            </p>
            <template v-else>
                <form
                    v-if="abilities.create || (editing && abilities.edit)"
                    class="flex flex-wrap items-end gap-3 rounded-lg border p-4"
                    @submit.prevent="save"
                >
                    <div class="space-y-1">
                        <Label for="name">Nama sumber</Label
                        ><Input
                            id="name"
                            v-model="form.name"
                            maxlength="100"
                            required
                        />
                        <p
                            v-if="form.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <label class="flex items-center gap-2 pb-2"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                        />Aktif</label
                    >
                    <Button :disabled="form.processing" type="submit">{{
                        editing ? 'Simpan perubahan' : 'Tambah sumber'
                    }}</Button>
                    <Button
                        v-if="editing"
                        variant="outline"
                        type="button"
                        @click="cancel"
                        >Batal</Button
                    >
                </form>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="p-2">Sumber</th>
                            <th class="p-2">Status</th>
                            <th class="p-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="source in sources"
                            :key="source.id"
                            class="border-b"
                        >
                            <td class="p-2">{{ source.name }}</td>
                            <td class="p-2">
                                {{ source.is_active ? 'Aktif' : 'Nonaktif' }}
                            </td>
                            <td class="p-2">
                                <Button
                                    v-if="abilities.edit"
                                    variant="outline"
                                    size="sm"
                                    @click="edit(source)"
                                    >Edit</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!sources.length">
                            <td colspan="3" class="p-4 text-muted-foreground">
                                Belum ada sumber. Tambahkan nama yang telah
                                disepakati organisasi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>
        </div>
    </WasteManagementLayout>
</template>
