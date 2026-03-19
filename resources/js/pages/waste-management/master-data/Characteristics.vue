<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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

defineProps<Props>();

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

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <Heading
                    title="Waste Characteristics"
                    description="Manage waste characteristics (hazardous/non-hazardous)"
                />
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button @click="openCreateDialog"
                            >Add Characteristic</Button
                        >
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-[500px]">
                        <DialogHeader>
                            <DialogTitle>
                                {{
                                    editingCharacteristic
                                        ? 'Edit Characteristic'
                                        : 'Create Characteristic'
                                }}
                            </DialogTitle>
                            <DialogDescription>
                                {{
                                    editingCharacteristic
                                        ? 'Update characteristic details.'
                                        : 'Add a new waste characteristic to the system.'
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
                                    placeholder="Characteristic name"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="code">Code *</Label>
                                <Input
                                    id="code"
                                    v-model="formData.code"
                                    required
                                    placeholder="CHAR_CODE"
                                    :disabled="!!editingCharacteristic"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="description">Description</Label>
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
                                        v-model:checked="formData.is_hazardous"
                                    />
                                    <Label
                                        for="is_hazardous"
                                        class="cursor-pointer"
                                    >
                                        Hazardous Material
                                        <span
                                            class="block text-xs text-muted-foreground"
                                        >
                                            Mark as hazardous/dangerous waste
                                        </span>
                                    </Label>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <Switch
                                        id="is_active"
                                        v-model:checked="formData.is_active"
                                    />
                                    <Label for="is_active">Active</Label>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button type="submit">
                                    {{
                                        editingCharacteristic
                                            ? 'Update'
                                            : 'Create'
                                    }}
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
                            <TableHead>Description</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Waste Types</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="characteristics.length === 0">
                            <TableCell
                                :colspan="7"
                                class="text-center text-muted-foreground"
                            >
                                No characteristics found.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="characteristic in characteristics"
                            :key="characteristic.id"
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
                                            ? 'Hazardous'
                                            : 'Non-Hazardous'
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
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openEditDialog(characteristic)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    @click="
                                        deleteCharacteristic(characteristic)
                                    "
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
