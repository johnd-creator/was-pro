<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import VendorsController from '@/actions/App/Http/Controllers/WasteManagement/MasterData/VendorsController';
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
import VendorAgreements from '@/components/VendorAgreements.vue';
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import { usePermissions } from '@/composables/usePermissions';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { vendorExpiryStatus } from '@/lib/vendor-license';
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
    license_expiry_day: string | null;
    is_active: boolean;
    is_transporter?: boolean | null;
    is_processor?: boolean | null;
    is_utilizer?: boolean | null;
    faba_movements_count: number;
    created_at: string;
    updated_at: string;
}

type Props = {
    vendors: Vendor[];
    licenseReferenceDate: string;
    agreementsReady: boolean;
    agreements: InstanceType<typeof VendorAgreements>['$props']['agreements'];
};

const props = defineProps<Props>();
const { hasPermission } = usePermissions();
const page = usePage();
const processing = ref(false);

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
    is_transporter: null as boolean | null,
    is_processor: null as boolean | null,
    is_utilizer: null as boolean | null,
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
        is_transporter: null as boolean | null,
        is_processor: null as boolean | null,
        is_utilizer: null as boolean | null,
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
        license_expiry_date: vendor.license_expiry_day || '',
        is_active: vendor.is_active,
        is_transporter: vendor.is_transporter ?? null,
        is_processor: vendor.is_processor ?? null,
        is_utilizer: vendor.is_utilizer ?? null,
    };
    dialogOpen.value = true;
}

function submit() {
    if (processing.value) return;
    processing.value = true;
    const payload = props.agreementsReady
        ? formData.value
        : Object.fromEntries(
              Object.entries(formData.value).filter(
                  ([key]) =>
                      ![
                          'is_transporter',
                          'is_processor',
                          'is_utilizer',
                      ].includes(key),
              ),
          );
    if (editingVendor.value) {
        router.put(
            scopedUrl(VendorsController.update(editingVendor.value.id)),
            payload,
            {
                onFinish: () => {
                    processing.value = false;
                },
                onSuccess: () => {
                    dialogOpen.value = false;
                },
            },
        );
    } else {
        router.post(scopedUrl(VendorsController.store()), payload, {
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                dialogOpen.value = false;
            },
        });
    }
}

function deleteVendor(vendor: Vendor) {
    if (confirm(`Hapus vendor ${vendor.name}?`)) {
        router.delete(scopedUrl(VendorsController.destroy(vendor.id)));
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
    return vendorExpiryStatus(licenseExpiryDate, props.licenseReferenceDate);
}
const { scopedUrl } = useOrganizationContext();
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Vendor">
        <Head title="Vendor - Waste Management" />

        <div class="relative min-w-0 overflow-x-hidden p-4">
            <div class="space-y-4">
                <section class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
                        <div class="space-y-4">
                            <div class="space-y-3">
                                <Heading title="Vendor dan MoU" />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
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
                            </div>
                        </div>
                        <div
                            class="wm-surface-panel space-y-3 rounded-[28px] p-5"
                        >
                            <Button
                                class="w-full justify-between"
                                v-if="hasPermission('vendors.create')"
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
                                Perusahaan, peran, kontak, lisensi, dan status
                                vendor.
                            </p>
                        </div>
                        <div
                            class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                        >
                            {{ props.vendors.length }} vendor
                        </div>
                    </div>
                    <Dialog v-model:open="dialogOpen">
                        <DialogTrigger
                            v-if="hasPermission('vendors.create')"
                            as-child
                        >
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

                                <fieldset
                                    v-if="agreementsReady"
                                    class="rounded-md border p-3"
                                >
                                    <legend class="px-1 text-sm font-medium">
                                        Peran vendor
                                    </legend>
                                    <p
                                        class="mb-2 text-xs text-muted-foreground"
                                    >
                                        Boleh lebih dari satu. Kosong berarti
                                        belum ditetapkan.
                                    </p>
                                    <div class="flex flex-wrap gap-4">
                                        <label
                                            class="flex items-center gap-2 text-sm"
                                            ><input
                                                v-model="
                                                    formData.is_transporter
                                                "
                                                type="checkbox"
                                            />Pengangkut</label
                                        ><label
                                            class="flex items-center gap-2 text-sm"
                                            ><input
                                                v-model="formData.is_processor"
                                                type="checkbox"
                                            />Pengelola</label
                                        ><label
                                            class="flex items-center gap-2 text-sm"
                                            ><input
                                                v-model="formData.is_utilizer"
                                                type="checkbox"
                                            />Pemanfaat</label
                                        >
                                    </div>
                                </fieldset>
                                <ul
                                    v-if="
                                        Object.keys(page.props.errors ?? {})
                                            .length
                                    "
                                    role="alert"
                                    class="text-sm text-destructive"
                                >
                                    <li
                                        v-for="(error, key) in page.props
                                            .errors"
                                        :key="key"
                                    >
                                        {{ error }}
                                    </li>
                                </ul>
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
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                    >
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
                                <TableHead>Peran</TableHead
                                ><TableHead>Kode</TableHead>
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
                                    :colspan="9"
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
                                <TableCell
                                    ><div class="flex flex-wrap gap-1">
                                        <Badge v-if="vendor.is_transporter"
                                            >Pengangkut</Badge
                                        ><Badge v-if="vendor.is_processor"
                                            >Pengelola</Badge
                                        ><Badge v-if="vendor.is_utilizer"
                                            >Pemanfaat</Badge
                                        ><span
                                            v-if="
                                                !vendor.is_transporter &&
                                                !vendor.is_processor &&
                                                !vendor.is_utilizer
                                            "
                                            class="text-xs text-muted-foreground"
                                            >Belum ditetapkan</span
                                        >
                                    </div></TableCell
                                >
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
                                                vendor.license_expiry_day,
                                            ).class
                                        "
                                    >
                                        {{
                                            getLicenseStatusBadge(
                                                vendor.license_expiry_day,
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
                                        v-if="hasPermission('vendors.edit')"
                                        @click="openEditDialog(vendor)"
                                    >
                                        Ubah
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        v-if="hasPermission('vendors.delete')"
                                        @click="deleteVendor(vendor)"
                                    >
                                        Hapus
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </section>
                <VendorAgreements
                    :vendors="vendors"
                    :agreements="agreements"
                    :ready="agreementsReady"
                />
            </div>
        </div>
    </WasteManagementLayout>
</template>
