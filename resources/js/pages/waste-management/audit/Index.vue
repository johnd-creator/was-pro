<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { index } from '@/actions/App/Http/Controllers/WasteManagement/OperationalAuditController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
interface Entry {
    id: string;
    actor_id: string | null;
    module: string;
    action: string;
    reference_id: string;
    occurred_at: string;
    before: Record<string, string | number | null>;
    after: Record<string, string | number | null>;
}
const props = defineProps<{
    entries: Entry[];
    page: number;
    hasMore: boolean;
    total: number;
    filters: Record<string, string>;
}>();
const { scopedUrl } = useOrganizationContext();
const filters = reactive({
    module: props.filters.module ?? '',
    action: props.filters.action ?? '',
    reference_id: props.filters.reference_id ?? '',
    actor_id: props.filters.actor_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
function load(page = 1): void {
    router.get(
        scopedUrl(index.url()),
        { ...filters, page },
        { preserveState: true },
    );
}
function fields(entry: Entry): string[] {
    return [
        ...new Set([...Object.keys(entry.before), ...Object.keys(entry.after)]),
    ].filter((key) => entry.before[key] !== entry.after[key]);
}
</script>
<template>
    <WasteManagementLayout
        title="Riwayat Audit B3"
        :breadcrumbs="[{ title: 'Riwayat Audit B3', href: index.url() }]"
    >
        <Head title="Riwayat Audit B3" />
        <div class="space-y-5 p-4">
            <Heading
                title="Riwayat Audit B3"
                description="Riwayat baca-saja untuk catatan yang masih dapat Anda akses. Catatan terhapus atau tidak tersedia tidak ditampilkan. Kolom yang belum direkam pada riwayat lama tidak dapat dipulihkan."
            />
            <form
                class="grid gap-3 rounded border p-4 sm:grid-cols-2"
                @submit.prevent="load()"
            >
                <label
                    >Modul<select
                        v-model="filters.module"
                        class="block w-full rounded border bg-background p-2"
                    >
                        <option value="">Semua yang diizinkan</option>
                        <option value="waste_records">Catatan limbah</option>
                        <option value="waste_haulings">Pengangkutan</option>
                    </select></label
                >
                <label
                    >Aksi<Input
                        v-model="filters.action"
                        placeholder="Contoh: updated"
                /></label>
                <label
                    >ID catatan<Input v-model="filters.reference_id"
                /></label>
                <label>ID pelaku<Input v-model="filters.actor_id" /></label>
                <label>Dari<Input v-model="filters.from" type="date" /></label>
                <label>Sampai<Input v-model="filters.to" type="date" /></label>
                <Button>Terapkan filter</Button>
            </form>
            <p class="text-sm text-muted-foreground">
                {{ total }} peristiwa yang dapat diakses.
            </p>
            <article
                v-for="entry in entries"
                :key="entry.id"
                class="space-y-3 rounded border p-4"
            >
                <h2 class="font-semibold">
                    {{
                        entry.module === 'waste_records'
                            ? 'Catatan limbah'
                            : 'Pengangkutan'
                    }}
                    · {{ entry.action }}
                </h2>
                <p class="text-xs break-all text-muted-foreground">
                    {{ entry.occurred_at }} · Pelaku:
                    {{ entry.actor_id ?? 'Tidak tercatat' }} · Catatan:
                    {{ entry.reference_id }}
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Kolom</th>
                                <th>Sebelum</th>
                                <th>Sesudah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="field in fields(entry)"
                                :key="field"
                                class="border-t"
                            >
                                <td class="p-2">{{ field }}</td>
                                <td
                                    class="max-w-lg break-words whitespace-pre-wrap"
                                >
                                    {{ entry.before[field] ?? '—' }}
                                </td>
                                <td
                                    class="max-w-lg break-words whitespace-pre-wrap"
                                >
                                    {{ entry.after[field] ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-if="fields(entry).length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Tidak ada perubahan kolom yang ditampilkan.
                </p>
            </article>
            <p v-if="entries.length === 0">
                Tidak ada riwayat sesuai filter dan izin Anda.
            </p>
            <div class="flex items-center gap-3">
                <Button
                    :disabled="page <= 1"
                    variant="outline"
                    @click="load(page - 1)"
                    >Sebelumnya</Button
                ><span>Halaman {{ page }}</span
                ><Button
                    :disabled="!hasMore"
                    variant="outline"
                    @click="load(page + 1)"
                    >Berikutnya</Button
                >
            </div>
        </div>
    </WasteManagementLayout>
</template>
