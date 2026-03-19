<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import CategoriesController from '@/actions/App/Http/Controllers/WasteManagement/MasterData/CategoriesController';
import Heading from '@/components/Heading.vue';
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

interface Category {
    id: string;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    waste_types_count: number;
    created_at: string;
    updated_at: string;
}

type Props = {
    categories: Category[];
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Master Data',
        href: '/waste-management/master-data',
    },
    {
        title: 'Categories',
        href: '/waste-management/master-data/categories',
    },
];

const dialogOpen = ref(false);
const editingCategory = ref<Category | null>(null);
const formData = ref({
    name: '',
    code: '',
    description: '',
    is_active: true,
});

function openCreateDialog() {
    editingCategory.value = null;
    formData.value = {
        name: '',
        code: '',
        description: '',
        is_active: true,
    };
    dialogOpen.value = true;
}

function openEditDialog(category: Category) {
    editingCategory.value = category;
    formData.value = {
        name: category.name,
        code: category.code,
        description: category.description || '',
        is_active: category.is_active,
    };
    dialogOpen.value = true;
}

function submit() {
    if (editingCategory.value) {
        router.put(
            CategoriesController.update(editingCategory.value.id),
            formData.value,
            {
                onSuccess: () => {
                    dialogOpen.value = false;
                },
            },
        );
    } else {
        router.post(CategoriesController.store(), formData.value, {
            onSuccess: () => {
                dialogOpen.value = false;
            },
        });
    }
}

function deleteCategory(category: Category) {
    if (confirm(`Are you sure you want to delete ${category.name}?`)) {
        router.delete(CategoriesController.destroy(category.id));
    }
}
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Categories">
        <Head title="Categories - Waste Management" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <Heading
                    title="Waste Categories"
                    description="Manage waste categories in the system"
                />
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button @click="openCreateDialog">Add Category</Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-[500px]">
                        <DialogHeader>
                            <DialogTitle>
                                {{
                                    editingCategory
                                        ? 'Edit Category'
                                        : 'Create Category'
                                }}
                            </DialogTitle>
                            <DialogDescription>
                                {{
                                    editingCategory
                                        ? 'Update category details.'
                                        : 'Add a new waste category to the system.'
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
                                    placeholder="Category name"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="code">Code *</Label>
                                <Input
                                    id="code"
                                    v-model="formData.code"
                                    required
                                    placeholder="CAT_CODE"
                                    :disabled="!!editingCategory"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="formData.description"
                                    placeholder="Category description"
                                    rows="3"
                                />
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
                                    {{ editingCategory ? 'Update' : 'Create' }}
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
                            <TableHead>Waste Types</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="categories.length === 0">
                            <TableCell
                                :colspan="6"
                                class="text-center text-muted-foreground"
                            >
                                No categories found.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="category in categories"
                            :key="category.id"
                        >
                            <TableCell class="font-medium">{{
                                category.name
                            }}</TableCell>
                            <TableCell class="font-mono text-xs">{{
                                category.code
                            }}</TableCell>
                            <TableCell class="max-w-xs truncate">{{
                                category.description || '-'
                            }}</TableCell>
                            <TableCell>{{
                                category.waste_types_count
                            }}</TableCell>
                            <TableCell>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                    :class="
                                        category.is_active
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-red-100 text-red-800'
                                    "
                                >
                                    {{
                                        category.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openEditDialog(category)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    @click="deleteCategory(category)"
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
