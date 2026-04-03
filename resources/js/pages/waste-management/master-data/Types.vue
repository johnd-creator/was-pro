<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import TypesController from '@/actions/App/Http/Controllers/WasteManagement/MasterData/TypesController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

interface Category {
    id: string;
    name: string;
    code: string;
}

interface Characteristic {
    id: string;
    name: string;
    code: string;
    is_hazardous: boolean;
}

interface WasteType {
    id: string;
    name: string;
    code: string;
    category_id: string;
    characteristic_id: string;
    description: string | null;
    storage_period_days: number;
    transport_cost: number;
    is_active: boolean;
    category?: Category;
    characteristic?: Characteristic;
    created_at: string;
    updated_at: string;
}

type Props = {
    wasteTypes: WasteType[];
    categories: Category[];
    characteristics: Characteristic[];
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Master Data',
        href: '/waste-management/master-data',
    },
    {
        title: 'Jenis Limbah',
        href: '/waste-management/master-data/types',
    },
];

const dialogOpen = ref(false);
const editingWasteType = ref<WasteType | null>(null);
const formData = ref({
    name: '',
    code: '',
    category_id: '',
    characteristic_id: '',
    description: '',
    storage_period_days: 30,
    transport_cost: 0,
    is_active: true,
});

function openCreateDialog() {
    editingWasteType.value = null;
    formData.value = {
        name: '',
        code: '',
        category_id: '',
        characteristic_id: '',
        description: '',
        storage_period_days: 30,
        transport_cost: 0,
        is_active: true,
    };
    dialogOpen.value = true;
}

function openEditDialog(wasteType: WasteType) {
    editingWasteType.value = wasteType;
    formData.value = {
        name: wasteType.name,
        code: wasteType.code,
        category_id: wasteType.category_id,
        characteristic_id: wasteType.characteristic_id,
        description: wasteType.description || '',
        storage_period_days: wasteType.storage_period_days,
        transport_cost: parseFloat(String(wasteType.transport_cost)),
        is_active: wasteType.is_active,
    };
    dialogOpen.value = true;
}

function submit() {
    if (editingWasteType.value) {
        router.put(
            TypesController.update(editingWasteType.value.id),
            formData.value,
            {
                onSuccess: () => {
                    dialogOpen.value = false;
                },
            },
        );
    } else {
        router.post(TypesController.store(), formData.value, {
            onSuccess: () => {
                dialogOpen.value = false;
            },
        });
    }
}

function deleteWasteType(wasteType: WasteType) {
    if (confirm(`Are you sure you want to delete ${wasteType.name}?`)) {
        router.delete(TypesController.destroy(wasteType.id));
    }
}

function getHazardousBadgeClass(isHazardous: boolean) {
    return isHazardous
        ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
}
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Jenis Limbah">
        <Head title="Jenis Limbah - Waste Management" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[320px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/4 -z-10 h-56 w-56 rounded-full bg-amber-200/18 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] to-amber-50/20 dark:to-amber-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_300px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-amber-700/70 uppercase"
                                >
                                    Operational Catalog
                                </p>
                                <Heading
                                    title="Jenis Limbah"
                                    description="Kelola jenis limbah lengkap dengan kategori, karakteristik, masa simpan, dan biaya standar."
                                />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Jenis
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ props.wasteTypes.length }}
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-blue"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-blue-700/80 uppercase"
                                    >
                                        Kategori
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ props.categories.length }}
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-rose"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-rose-700/80 uppercase"
                                    >
                                        Karakteristik
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ props.characteristics.length }}
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
                                Tambah jenis limbah
                                <Plus class="h-4 w-4" />
                            </Button>
                            <div
                                class="wm-surface-subtle rounded-[22px] p-4 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Master ini mengikat kategori, karakteristik,
                                storage period, dan biaya angkut standar.
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
                                Daftar Jenis Limbah
                            </p>
                            <p
                                class="text-sm text-slate-600 dark:text-slate-300"
                            >
                                Tipe limbah yang dipakai oleh pencatatan,
                                approval, dan transportasi.
                            </p>
                        </div>
                        <div
                            class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                        >
                            {{ props.wasteTypes.length }} tipe
                        </div>
                    </div>
                    <Dialog v-model:open="dialogOpen">
                        <DialogTrigger as-child>
                            <span class="hidden" />
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[600px]">
                            <DialogHeader>
                                <DialogTitle>
                                    {{
                                        editingWasteType
                                            ? 'Ubah jenis limbah'
                                            : 'Buat jenis limbah'
                                    }}
                                </DialogTitle>
                                <DialogDescription>
                                    {{
                                        editingWasteType
                                            ? 'Perbarui detail jenis limbah.'
                                            : 'Tambahkan jenis limbah baru ke sistem.'
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
                                        placeholder="Nama jenis limbah"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="code">Kode *</Label>
                                    <Input
                                        id="code"
                                        v-model="formData.code"
                                        required
                                        placeholder="WASTE_CODE"
                                        :disabled="!!editingWasteType"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label for="category_id"
                                            >Kategori *</Label
                                        >
                                        <Select
                                            v-model="formData.category_id"
                                            required
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder="Pilih kategori"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="category in categories"
                                                    :key="category.id"
                                                    :value="category.id"
                                                >
                                                    {{ category.name }} ({{
                                                        category.code
                                                    }})
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="characteristic_id"
                                            >Karakteristik *</Label
                                        >
                                        <Select
                                            v-model="formData.characteristic_id"
                                            required
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder="Pilih karakteristik"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="characteristic in characteristics"
                                                    :key="characteristic.id"
                                                    :value="characteristic.id"
                                                >
                                                    {{ characteristic.name }}
                                                    ({{ characteristic.code }})
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="description">Deskripsi</Label>
                                    <Textarea
                                        id="description"
                                        v-model="formData.description"
                                        placeholder="Deskripsi jenis limbah"
                                        rows="2"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label for="storage_period_days"
                                            >Masa Simpan (Hari) *</Label
                                        >
                                        <Input
                                            id="storage_period_days"
                                            v-model.number="
                                                formData.storage_period_days
                                            "
                                            type="number"
                                            min="0"
                                            max="36500"
                                            required
                                            placeholder="30"
                                        />
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Batas maksimal penyimpanan dalam
                                            hari
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="transport_cost"
                                            >Biaya Angkut *</Label
                                        >
                                        <Input
                                            id="transport_cost"
                                            v-model.number="
                                                formData.transport_cost
                                            "
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            required
                                            placeholder="0.00"
                                        />
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Biaya standar per unit
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <Switch
                                        id="is_active"
                                        v-model:checked="formData.is_active"
                                    />
                                    <Label for="is_active">Aktif</Label>
                                </div>

                                <DialogFooter>
                                    <Button type="submit">
                                        {{
                                            editingWasteType
                                                ? 'Simpan perubahan'
                                                : 'Buat jenis limbah'
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
                                <TableHead>Kategori</TableHead>
                                <TableHead>Karakteristik</TableHead>
                                <TableHead>Simpan</TableHead>
                                <TableHead>Biaya</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="wasteTypes.length === 0">
                                <TableCell
                                    :colspan="8"
                                    class="text-center text-muted-foreground"
                                >
                                    Belum ada jenis limbah.
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="wasteType in wasteTypes"
                                :key="wasteType.id"
                                class="border-slate-200/70 dark:border-slate-800/70"
                            >
                                <TableCell class="font-medium">{{
                                    wasteType.name
                                }}</TableCell>
                                <TableCell class="font-mono text-xs">{{
                                    wasteType.code
                                }}</TableCell>
                                <TableCell>{{
                                    wasteType.category?.name || '-'
                                }}</TableCell>
                                <TableCell>
                                    <Badge
                                        v-if="wasteType.characteristic"
                                        :class="
                                            getHazardousBadgeClass(
                                                wasteType.characteristic
                                                    .is_hazardous,
                                            )
                                        "
                                    >
                                        {{ wasteType.characteristic.name }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground"
                                        >-</span
                                    >
                                </TableCell>
                                <TableCell
                                    >{{
                                        wasteType.storage_period_days
                                    }}
                                    hari</TableCell
                                >
                                <TableCell>{{
                                    parseFloat(
                                        String(wasteType.transport_cost),
                                    ).toFixed(2)
                                }}</TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                        :class="
                                            wasteType.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        "
                                    >
                                        {{
                                            wasteType.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditDialog(wasteType)"
                                    >
                                        Ubah
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="deleteWasteType(wasteType)"
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
