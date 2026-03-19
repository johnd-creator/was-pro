<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Master Data',
        href: '/waste-management/master-data',
    },
    {
        title: 'Waste Types',
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
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Waste Types">
        <Head title="Waste Types - Waste Management" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <Heading
                    title="Waste Types"
                    description="Manage waste types with categories and characteristics"
                />
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button @click="openCreateDialog"
                            >Add Waste Type</Button
                        >
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-[600px]">
                        <DialogHeader>
                            <DialogTitle>
                                {{
                                    editingWasteType
                                        ? 'Edit Waste Type'
                                        : 'Create Waste Type'
                                }}
                            </DialogTitle>
                            <DialogDescription>
                                {{
                                    editingWasteType
                                        ? 'Update waste type details.'
                                        : 'Add a new waste type to the system.'
                                }}
                            </DialogDescription>
                        </DialogHeader>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="name">Name *</Label>
                                <Input
                                    id="name"
                                    v-model="formData.name"
                                    required
                                    placeholder="Waste type name"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="code">Code *</Label>
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
                                    <Label for="category_id">Category *</Label>
                                    <Select
                                        v-model="formData.category_id"
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="Select category"
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
                                        >Characteristic *</Label
                                    >
                                    <Select
                                        v-model="formData.characteristic_id"
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="Select characteristic"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="characteristic in characteristics"
                                                :key="characteristic.id"
                                                :value="characteristic.id"
                                            >
                                                {{ characteristic.name }} ({{
                                                    characteristic.code
                                                }})
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="formData.description"
                                    placeholder="Waste type description"
                                    rows="2"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="storage_period_days"
                                        >Storage Period (Days) *</Label
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
                                    <p class="text-xs text-muted-foreground">
                                        Maximum storage period in days
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="transport_cost"
                                        >Transport Cost *</Label
                                    >
                                    <Input
                                        id="transport_cost"
                                        v-model.number="formData.transport_cost"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        required
                                        placeholder="0.00"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Standard cost per unit
                                    </p>
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
                                    {{ editingWasteType ? 'Update' : 'Create' }}
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
                            <TableHead>Name</TableHead>
                            <TableHead>Code</TableHead>
                            <TableHead>Category</TableHead>
                            <TableHead>Characteristic</TableHead>
                            <TableHead>Storage</TableHead>
                            <TableHead>Cost</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="wasteTypes.length === 0">
                            <TableCell
                                :colspan="8"
                                class="text-center text-muted-foreground"
                            >
                                No waste types found.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="wasteType in wasteTypes"
                            :key="wasteType.id"
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
                                days</TableCell
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
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openEditDialog(wasteType)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    @click="deleteWasteType(wasteType)"
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
