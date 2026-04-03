<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import VendorsController from '@/actions/App/Http/Controllers/WasteManagement/MasterData/VendorsController';
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

interface Vendor {
    id: string;
    name: string;
    code: string;
    description: string | null;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    license_number: string | null;
    license_expiry_date: string | null;
    is_active: boolean;
    transportations_count: number;
    created_at: string;
    updated_at: string;
}

type Props = {
    vendors: Vendor[];
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Master Data',
        href: '/waste-management/master-data',
    },
    {
        title: 'Vendor',
        href: '/waste-management/master-data/vendors',
    },
];

const dialogOpen = ref(false);
const editingVendor = ref<Vendor | null>(null);
const formData = ref({
    name: '',
    code: '',
    description: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    license_number: '',
    license_expiry_date: '',
    is_active: true,
});

function openCreateDialog() {
    editingVendor.value = null;
    formData.value = {
        name: '',
        code: '',
        description: '',
        contact_person: '',
        phone: '',
        email: '',
        address: '',
        license_number: '',
        license_expiry_date: '',
        is_active: true,
    };
    dialogOpen.value = true;
}

function openEditDialog(vendor: Vendor) {
    editingVendor.value = vendor;
    formData.value = {
        name: vendor.name,
        code: vendor.code,
        description: vendor.description || '',
        contact_person: vendor.contact_person || '',
        phone: vendor.phone || '',
        email: vendor.email || '',
        address: vendor.address || '',
        license_number: vendor.license_number || '',
        license_expiry_date: vendor.license_expiry_date || '',
        is_active: vendor.is_active,
    };
    dialogOpen.value = true;
}

function submit() {
    if (editingVendor.value) {
        router.put(
            VendorsController.update(editingVendor.value.id),
            formData.value,
            {
                onSuccess: () => {
                    dialogOpen.value = false;
                },
            },
        );
    } else {
        router.post(VendorsController.store(), formData.value, {
            onSuccess: () => {
                dialogOpen.value = false;
            },
        });
    }
}

function deleteVendor(vendor: Vendor) {
    if (confirm(`Are you sure you want to delete ${vendor.name}?`)) {
        router.delete(VendorsController.destroy(vendor.id));
    }
}

function formatDate(dateString: string | null) {
    if (!dateString) return '-';
    try {
        return format(new Date(dateString), 'MMM dd, yyyy');
    } catch {
        return dateString;
    }
}

function getLicenseStatusBadge(licenseExpiryDate: string | null) {
    if (!licenseExpiryDate) {
        return {
            class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
            text: 'Tidak ada lisensi',
        };
    }

    const expiryDate = new Date(licenseExpiryDate);
    const today = new Date();
    const daysUntilExpiry = Math.ceil(
        (expiryDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );

    if (daysUntilExpiry < 0) {
        return {
            class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            text: 'Kedaluwarsa',
        };
    } else if (daysUntilExpiry <= 30) {
        return {
            class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            text: `Segera berakhir (${daysUntilExpiry}h)`,
        };
    } else {
        return {
            class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            text: 'Valid',
        };
    }
}
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Vendor">
        <Head title="Vendor - Waste Management" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[320px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/4 -z-10 h-56 w-56 rounded-full bg-emerald-200/18 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] to-emerald-50/20 dark:to-emerald-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_300px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-emerald-700/70 uppercase"
                                >
                                    Vendor Registry
                                </p>
                                <Heading
                                    title="Vendor Angkutan"
                                    description="Kelola vendor transportasi limbah, status lisensi, dan data kontak dari satu registry operasional."
                                />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Vendor
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ props.vendors.length }}
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-emerald"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-emerald-700/80 uppercase"
                                    >
                                        Aktif
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            props.vendors.filter(
                                                (vendor) => vendor.is_active,
                                            ).length
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-amber"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-amber-700/80 uppercase"
                                    >
                                        Transportasi
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            props.vendors.reduce(
                                                (total, vendor) =>
                                                    total +
                                                    vendor.transportations_count,
                                                0,
                                            )
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
                                Tambah vendor
                                <Plus class="h-4 w-4" />
                            </Button>
                            <div
                                class="wm-surface-subtle rounded-[22px] p-4 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Audit vendor aktif dan status lisensi sebelum
                                dipakai untuk pengangkutan.
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
                                Daftar Vendor
                            </p>
                            <p
                                class="text-sm text-slate-600 dark:text-slate-300"
                            >
                                Perusahaan, kontak, lisensi, dan status vendor
                                transportasi.
                            </p>
                        </div>
                        <div
                            class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                        >
                            {{ props.vendors.length }} vendor
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
                                        editingVendor
                                            ? 'Ubah vendor'
                                            : 'Buat vendor'
                                    }}
                                </DialogTitle>
                                <DialogDescription>
                                    {{
                                        editingVendor
                                            ? 'Perbarui detail vendor.'
                                            : 'Tambahkan vendor baru ke sistem.'
                                    }}
                                </DialogDescription>
                            </DialogHeader>
                            <form
                                @submit.prevent="submit"
                                class="max-h-[600px] space-y-4 overflow-y-auto"
                            >
                                <div class="grid gap-2">
                                    <Label for="name">Nama Perusahaan *</Label>
                                    <Input
                                        id="name"
                                        v-model="formData.name"
                                        required
                                        placeholder="Nama perusahaan vendor"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="code">Kode *</Label>
                                    <Input
                                        id="code"
                                        v-model="formData.code"
                                        required
                                        placeholder="VENDOR_CODE"
                                        :disabled="!!editingVendor"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="description">Deskripsi</Label>
                                    <Textarea
                                        id="description"
                                        v-model="formData.description"
                                        placeholder="Deskripsi vendor"
                                        rows="2"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label for="contact_person">PIC</Label>
                                        <Input
                                            id="contact_person"
                                            v-model="formData.contact_person"
                                            placeholder="Nama lengkap"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="phone">Telepon</Label>
                                        <Input
                                            id="phone"
                                            v-model="formData.phone"
                                            type="tel"
                                            placeholder="Nomor telepon"
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="email">Email</Label>
                                    <Input
                                        id="email"
                                        v-model="formData.email"
                                        type="email"
                                        placeholder="Email address"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="address">Alamat</Label>
                                    <Textarea
                                        id="address"
                                        v-model="formData.address"
                                        placeholder="Alamat lengkap"
                                        rows="2"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label for="license_number"
                                            >Nomor Lisensi</Label
                                        >
                                        <Input
                                            id="license_number"
                                            v-model="formData.license_number"
                                            placeholder="Nomor lisensi"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="license_expiry_date"
                                            >Tanggal Berakhir Lisensi</Label
                                        >
                                        <Input
                                            id="license_expiry_date"
                                            v-model="
                                                formData.license_expiry_date
                                            "
                                            type="date"
                                        />
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
                                            editingVendor
                                                ? 'Simpan perubahan'
                                                : 'Buat vendor'
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
                                <TableHead>Perusahaan</TableHead>
                                <TableHead>Kode</TableHead>
                                <TableHead>Kontak</TableHead>
                                <TableHead>Telepon</TableHead>
                                <TableHead>Lisensi</TableHead>
                                <TableHead>Status Lisensi</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="vendors.length === 0">
                                <TableCell
                                    :colspan="8"
                                    class="text-center text-muted-foreground"
                                >
                                    Belum ada vendor.
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="vendor in vendors"
                                :key="vendor.id"
                                class="border-slate-200/70 dark:border-slate-800/70"
                            >
                                <TableCell class="font-medium">{{
                                    vendor.name
                                }}</TableCell>
                                <TableCell class="font-mono text-xs">{{
                                    vendor.code
                                }}</TableCell>
                                <TableCell>
                                    <div class="text-sm">
                                        <div class="font-medium">
                                            {{ vendor.contact_person || '-' }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ vendor.email || '-' }}
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>{{ vendor.phone || '-' }}</TableCell>
                                <TableCell class="text-sm">
                                    <div>
                                        {{ vendor.license_number || '-' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            formatDate(
                                                vendor.license_expiry_date,
                                            )
                                        }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :class="
                                            getLicenseStatusBadge(
                                                vendor.license_expiry_date,
                                            ).class
                                        "
                                    >
                                        {{
                                            getLicenseStatusBadge(
                                                vendor.license_expiry_date,
                                            ).text
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                        :class="
                                            vendor.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        "
                                    >
                                        {{
                                            vendor.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditDialog(vendor)"
                                    >
                                        Ubah
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="deleteVendor(vendor)"
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
