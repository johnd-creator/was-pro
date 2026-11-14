<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import { usePermissions } from '@/composables/usePermissions';
interface Party {
    id: string;
    name: string;
    is_active: boolean;
    is_transporter?: boolean | null;
    is_processor?: boolean | null;
    is_utilizer?: boolean | null;
}
interface Agreement {
    id: string;
    transporter_vendor_id: string;
    receiver_vendor_id: string;
    document_number: string;
    valid_from: string | null;
    valid_until: string | null;
    waste_classification: string;
    document_status: string;
    notes: string | null;
    period_status: string;
    transporter: { name: string };
    receiver: { name: string };
}
defineProps<{ vendors: Party[]; agreements: Agreement[]; ready: boolean }>();
const { scopedUrl } = useOrganizationContext();
const { hasPermission } = usePermissions();
const open = ref(false);
const editing = ref<string | null>(null);
const form = useForm({
    transporter_vendor_id: '',
    receiver_vendor_id: '',
    document_number: '',
    valid_from: '',
    valid_until: '',
    waste_classification: 'b3',
    document_status: 'draft',
    notes: '',
});
const statuses: Record<string, string> = {
    draft: 'Draf',
    recorded: 'Tercatat',
    revoked: 'Dicabut',
};
function show(agreement?: Agreement) {
    editing.value = agreement?.id ?? null;
    form.reset();
    form.clearErrors();
    if (agreement)
        Object.assign(form, {
            transporter_vendor_id: agreement.transporter_vendor_id,
            receiver_vendor_id: agreement.receiver_vendor_id,
            document_number: agreement.document_number,
            valid_from: agreement.valid_from ?? '',
            valid_until: agreement.valid_until ?? '',
            waste_classification: agreement.waste_classification,
            document_status: agreement.document_status,
            notes: agreement.notes ?? '',
        });
    open.value = true;
}
function submit() {
    const url = scopedUrl({
        url:
            '/waste-management/master-data/vendor-agreements' +
            (editing.value ? '/' + editing.value : ''),
    });
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };
    if (editing.value) form.put(url, options);
    else form.post(url, options);
}
</script>
<template>
    <section class="rounded-xl border bg-card p-5 text-card-foreground">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold">MoU Vendor</h2>
            <Button
                v-if="ready && hasPermission('vendors.create')"
                @click="show()"
                >Tambah MoU</Button
            >
        </div>
        <p class="mt-2 text-sm text-muted-foreground">
            Satu pengangkut dapat bekerja sama dengan beberapa pengelola atau
            pemanfaat. Masa berlaku dihitung dari tanggal dokumen, bukan
            verifikasi izin.
        </p>
        <p v-if="!ready" class="mt-4 text-sm">
            Fitur MoU menunggu penyiapan database. Data vendor tetap tersedia.
        </p>
        <p
            v-else-if="!agreements.length"
            class="mt-4 text-sm text-muted-foreground"
        >
            MoU belum tercatat.
        </p>
        <div v-else class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="p-2">Dokumen / jenis</th>
                        <th class="p-2">Pengangkut</th>
                        <th class="p-2">Pengelola / pemanfaat</th>
                        <th class="p-2">Masa berlaku</th>
                        <th class="p-2">Status dokumen</th>
                        <th class="p-2"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in agreements" :key="a.id" class="border-b">
                        <td class="p-2">
                            {{ a.document_number }}
                            <div class="text-muted-foreground">
                                {{
                                    a.waste_classification === 'b3'
                                        ? 'B3'
                                        : 'Non B3'
                                }}
                            </div>
                        </td>
                        <td class="p-2">{{ a.transporter.name }}</td>
                        <td class="p-2">{{ a.receiver.name }}</td>
                        <td class="p-2">
                            {{ a.valid_from || '-' }} /
                            {{ a.valid_until || '-' }}
                            <div class="text-muted-foreground">
                                {{ a.period_status }}
                            </div>
                        </td>
                        <td class="p-2">{{ statuses[a.document_status] }}</td>
                        <td class="p-2">
                            <Button
                                v-if="hasPermission('vendors.edit')"
                                variant="ghost"
                                @click="show(a)"
                                >Ubah</Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Dialog v-model:open="open"
            ><DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl"
                ><DialogHeader
                    ><DialogTitle>{{
                        editing ? 'Ubah MoU' : 'Tambah MoU'
                    }}</DialogTitle
                    ><DialogDescription
                        >Catat dokumen yang tersedia. Perusahaan boleh
                        menjalankan lebih dari satu peran.</DialogDescription
                    ></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <Label for="mou-transporter">Pengangkut</Label
                        ><select
                            id="mou-transporter"
                            v-model="form.transporter_vendor_id"
                            required
                            class="mt-1 w-full rounded-md border bg-background p-2"
                        >
                            <option value="">Pilih vendor</option>
                            <option
                                v-for="v in vendors.filter(
                                    (v) =>
                                        (v.is_active && v.is_transporter) ||
                                        v.id === form.transporter_vendor_id,
                                )"
                                :key="v.id"
                                :value="v.id"
                            >
                                {{ v.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label for="mou-receiver">Pengelola / pemanfaat</Label
                        ><select
                            id="mou-receiver"
                            v-model="form.receiver_vendor_id"
                            required
                            class="mt-1 w-full rounded-md border bg-background p-2"
                        >
                            <option value="">Pilih vendor</option>
                            <option
                                v-for="v in vendors.filter(
                                    (v) =>
                                        (v.is_active &&
                                            (v.is_processor ||
                                                v.is_utilizer)) ||
                                        v.id === form.receiver_vendor_id,
                                )"
                                :key="v.id"
                                :value="v.id"
                            >
                                {{ v.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label for="mou-number">Nomor dokumen</Label
                        ><Input
                            id="mou-number"
                            v-model="form.document_number"
                            required
                            maxlength="150"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label for="mou-start">Mulai berlaku</Label
                            ><Input
                                id="mou-start"
                                v-model="form.valid_from"
                                type="date"
                            />
                        </div>
                        <div>
                            <Label for="mou-end">Akhir berlaku</Label
                            ><Input
                                id="mou-end"
                                v-model="form.valid_until"
                                type="date"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label for="mou-class">Klasifikasi</Label
                            ><select
                                id="mou-class"
                                v-model="form.waste_classification"
                                class="w-full rounded-md border bg-background p-2"
                            >
                                <option value="b3">B3</option>
                                <option value="non_b3">Non B3</option>
                            </select>
                        </div>
                        <div>
                            <Label for="mou-status">Status dokumen</Label
                            ><select
                                id="mou-status"
                                v-model="form.document_status"
                                class="w-full rounded-md border bg-background p-2"
                            >
                                <option value="draft">Draf</option>
                                <option value="recorded">Tercatat</option>
                                <option value="revoked">Dicabut</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <Label for="mou-notes">Catatan</Label
                        ><textarea
                            id="mou-notes"
                            v-model="form.notes"
                            maxlength="3000"
                            class="w-full rounded-md border bg-background p-2"
                        />
                    </div>
                    <ul
                        v-if="Object.keys(form.errors).length"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        <li v-for="(error, key) in form.errors" :key="key">
                            {{ error }}
                        </li>
                    </ul>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >Batal</Button
                        ><Button :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </DialogContent></Dialog
        >
    </section>
</template>
