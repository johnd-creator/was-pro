<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import CharacteristicsController from '@/actions/App/Http/Controllers/WasteManagement/MasterData/CharacteristicsController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import type { BreadcrumbItem } from '@/types';

interface Characteristic {
    id: string;
    name: string;
    code: string;
    description: string | null;
    is_hazardous: boolean;
    is_active: boolean;
    waste_types_count: number;
    created_at: string;
    updated_at: string;
}

type Props = {
    characteristics: Characteristic[];
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Master Data',
        href: '/waste-management/master-data',
    },
    {
        title: 'Characteristics',
        href: '/waste-management/master-data/characteristics',
    },
];

const dialogOpen = ref(false);
const editingCharacteristic = ref<Characteristic | null>(null);
const formData = ref({
    name: '',
    code: '',
    description: '',
    is_hazardous: false,
    is_active: true,
});

function openCreateDialog() {
    editingCharacteristic.value = null;
    formData.value = {
        name: '',
        code: '',
        description: '',
        is_hazardous: false,
        is_active: true,
    };
    dialogOpen.value = true;
}

function openEditDialog(characteristic: Characteristic) {
    editingCharacteristic.value = characteristic;
    formData.value = {
        name: characteristic.name,
        code: characteristic.code,
        description: characteristic.description || '',
        is_hazardous: characteristic.is_hazardous,
        is_active: characteristic.is_active,
    };
    dialogOpen.value = true;
}

function submit() {
    if (editingCharacteristic.value) {
        router.put(
            CharacteristicsController.update(editingCharacteristic.value.id),
            formData.value,
            {
                onSuccess: () => {
                    dialogOpen.value = false;
                },
            },
        );
    } else {
        router.post(CharacteristicsController.store(), formData.value, {
            onSuccess: () => {
                dialogOpen.value = false;
            },
        });
    }
}

function deleteCharacteristic(characteristic: Characteristic) {
    if (confirm(`Are you sure you want to delete ${characteristic.name}?`)) {
        router.delete(CharacteristicsController.destroy(characteristic.id));
    }
}

function getHazardousBadgeClass(isHazardous: boolean) {
    return isHazardous
        ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Characteristics"
    >
        <Head title="Characteristics - Waste Management" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[320px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/4 -z-10 h-56 w-56 rounded-full bg-rose-200/18 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] to-rose-50/20 dark:to-rose-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_300px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-rose-700/70 uppercase"
                                >
                                    Risk Tags
                                </p>
                                <Heading
                                    title="Karakteristik Limbah"
                                    description="Kelola karakteristik limbah berbahaya dan non-berbahaya untuk klasifikasi dan kontrol risiko."
                                />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Karakteristik
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ props.characteristics.length }}
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-rose"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-rose-700/80 uppercase"
                                    >
                                        B3
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            props.characteristics.filter(
                                                (item) => item.is_hazardous,
                                            ).length
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="wm-surface-panel space-y-3 rounded-[28px] p-5"
                        >
                            <Button
                                class="w-full justify-between"
                                @click="openCreateDialog"
                            >
                                Tambah karakteristik
                                <Plus class="h-4 w-4" />
                            </Button>
                            <div
                                class="wm-surface-subtle rounded-[22px] p-4 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Tandai atribut risiko untuk membantu klasifikasi
                                tipe limbah dan kontrol penyimpanan.
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    class="wm-surface-elevated overflow-hidden rounded-[28px]"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200/80 bg-slate-50/90 px-5 py-4 dark:bg-slate-900/80"
                    >
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Daftar Karakteristik
                            </p>
                            <p
                                class="text-sm text-slate-600 dark:text-slate-300"
                            >
                                Tag risiko, relasi tipe limbah, dan status
                                karakteristik.
                            </p>
                        </div>
                        <div
                            class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                        >
                            {{ props.characteristics.length }} item
                        </div>
                    </div>
                    <Dialog v-model:open="dialogOpen">
                        <DialogTrigger as-child>
                            <span class="hidden" />
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[500px]">
                            <DialogHeader>
                                <DialogTitle>
                                    {{
                                        editingCharacteristic
                                            ? 'Ubah karakteristik'
                                            : 'Buat karakteristik'
                                    }}
                                </DialogTitle>
                                <DialogDescription>
                                    {{
                                        editingCharacteristic
                                            ? 'Perbarui detail karakteristik.'
                                            : 'Tambahkan karakteristik limbah baru ke sistem.'
                                    }}
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submit" class="space-y-4">
                                <div class="grid gap-2">
                                    <Label for="name">Nama *</Label>
                                    <Input
                                        id="name"
                                        v-model="formData.name"
                                        required
                                        placeholder="Characteristic name"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="code">Kode *</Label>
                                    <Input
                                        id="code"
                                        v-model="formData.code"
                                        required
                                        placeholder="CHAR_CODE"
                                        :disabled="!!editingCharacteristic"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="description">Deskripsi</Label>
                                    <Textarea
                                        id="description"
                                        v-model="formData.description"
                                        placeholder="Characteristic description"
                                        rows="3"
                                    />
                                </div>

                                <div class="space-y-4">
                                    <div class="flex items-center space-x-2">
                                        <Switch
                                            id="is_hazardous"
                                            v-model:checked="
                                                formData.is_hazardous
                                            "
                                        />
                                        <Label
                                            for="is_hazardous"
                                            class="cursor-pointer"
                                        >
                                            Material Berbahaya
                                            <span
                                                class="block text-xs text-muted-foreground"
                                            >
                                                Tandai sebagai limbah berbahaya
                                            </span>
                                        </Label>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <Switch
                                            id="is_active"
                                            v-model:checked="formData.is_active"
                                        />
                                        <Label for="is_active">Aktif</Label>
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button type="submit">
                                        {{
                                            editingCharacteristic
                                                ? 'Simpan perubahan'
                                                : 'Buat karakteristik'
                                        }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                    <Table>
                        <TableHeader
                            class="bg-slate-50/90 dark:bg-slate-900/80"
                        >
                            <TableRow
                                class="border-slate-200/80 dark:border-slate-800/80"
                            >
                                <TableHead>Nama</TableHead>
                                <TableHead>Kode</TableHead>
                                <TableHead>Deskripsi</TableHead>
                                <TableHead>Tipe</TableHead>
                                <TableHead>Jenis Limbah</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="characteristics.length === 0">
                                <TableCell
                                    :colspan="7"
                                    class="text-center text-muted-foreground"
                                >
                                    Belum ada karakteristik.
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="characteristic in characteristics"
                                :key="characteristic.id"
                                class="border-slate-200/70 dark:border-slate-800/70"
                            >
                                <TableCell class="font-medium">{{
                                    characteristic.name
                                }}</TableCell>
                                <TableCell class="font-mono text-xs">{{
                                    characteristic.code
                                }}</TableCell>
                                <TableCell class="max-w-xs truncate">{{
                                    characteristic.description || '-'
                                }}</TableCell>
                                <TableCell>
                                    <Badge
                                        :class="
                                            getHazardousBadgeClass(
                                                characteristic.is_hazardous,
                                            )
                                        "
                                    >
                                        {{
                                            characteristic.is_hazardous
                                                ? 'B3'
                                                : 'Non-B3'
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{
                                    characteristic.waste_types_count
                                }}</TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                        :class="
                                            characteristic.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        "
                                    >
                                        {{
                                            characteristic.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditDialog(characteristic)"
                                    >
                                        Ubah
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="
                                            deleteCharacteristic(characteristic)
                                        "
                                    >
                                        Hapus
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
