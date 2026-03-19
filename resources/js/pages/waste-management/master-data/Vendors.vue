<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { format } from 'date-fns';
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

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Master Data',
        href: '/waste-management/master-data',
    },
    {
        title: 'Vendors',
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
            text: 'No License',
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
            text: 'Expired',
        };
    } else if (daysUntilExpiry <= 30) {
        return {
            class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            text: `Expiring Soon (${daysUntilExpiry}d)`,
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
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Vendors">
        <Head title="Vendors - Waste Management" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <Heading
                    title="Vendors"
                    description="Manage waste transportation vendors"
                />
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button @click="openCreateDialog">Add Vendor</Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-[600px]">
                        <DialogHeader>
                            <DialogTitle>
                                {{
                                    editingVendor
                                        ? 'Edit Vendor'
                                        : 'Create Vendor'
                                }}
                            </DialogTitle>
                            <DialogDescription>
                                {{
                                    editingVendor
                                        ? 'Update vendor details.'
                                        : 'Add a new vendor to the system.'
                                }}
                            </DialogDescription>
                        </DialogHeader>
                        <form
                            @submit.prevent="submit"
                            class="max-h-[600px] space-y-4 overflow-y-auto"
                        >
                            <div class="grid gap-2">
                                <Label for="name">Company Name *</Label>
                                <Input
                                    id="name"
                                    v-model="formData.name"
                                    required
                                    placeholder="Vendor company name"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="code">Code *</Label>
                                <Input
                                    id="code"
                                    v-model="formData.code"
                                    required
                                    placeholder="VENDOR_CODE"
                                    :disabled="!!editingVendor"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="formData.description"
                                    placeholder="Vendor description"
                                    rows="2"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="contact_person"
                                        >Contact Person</Label
                                    >
                                    <Input
                                        id="contact_person"
                                        v-model="formData.contact_person"
                                        placeholder="Full name"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        v-model="formData.phone"
                                        type="tel"
                                        placeholder="Phone number"
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
                                <Label for="address">Address</Label>
                                <Textarea
                                    id="address"
                                    v-model="formData.address"
                                    placeholder="Complete address"
                                    rows="2"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="license_number"
                                        >License Number</Label
                                    >
                                    <Input
                                        id="license_number"
                                        v-model="formData.license_number"
                                        placeholder="License number"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="license_expiry_date"
                                        >License Expiry Date</Label
                                    >
                                    <Input
                                        id="license_expiry_date"
                                        v-model="formData.license_expiry_date"
                                        type="date"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch
                                    id="is_active"
                                    v-model:checked="formData.is_active"
                                />
                                <Label for="is_active">Active</Label>
                            </div>

                            <DialogFooter>
                                <Button type="submit">
                                    {{ editingVendor ? 'Update' : 'Create' }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Company</TableHead>
                            <TableHead>Code</TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead>Phone</TableHead>
                            <TableHead>License</TableHead>
                            <TableHead>License Status</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="vendors.length === 0">
                            <TableCell
                                :colspan="8"
                                class="text-center text-muted-foreground"
                            >
                                No vendors found.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="vendor in vendors" :key="vendor.id">
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
                                    <div class="text-xs text-muted-foreground">
                                        {{ vendor.email || '-' }}
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>{{ vendor.phone || '-' }}</TableCell>
                            <TableCell class="text-sm">
                                <div>{{ vendor.license_number || '-' }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ formatDate(vendor.license_expiry_date) }}
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
                                        vendor.is_active ? 'Active' : 'Inactive'
                                    }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openEditDialog(vendor)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    @click="deleteVendor(vendor)"
                                >
                                    Delete
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </WasteManagementLayout>
</template>
