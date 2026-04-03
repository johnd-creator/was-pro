<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
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

const props = defineProps<Props>();

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

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[320px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/4 -z-10 h-56 w-56 rounded-full bg-blue-200/18 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] to-blue-50/20 dark:to-blue-950/20"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_300px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-blue-700/70 uppercase"
                                >
                                    Reference Layer
                                </p>
                                <Heading
                                    title="Kategori Limbah"
                                    description="Kelola kategori utama limbah sebagai fondasi klasifikasi dan pelaporan operasional."
                                />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
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
                                            props.categories.filter(
                                                (category) =>
                                                    category.is_active,
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
                                Tambah kategori
                                <Plus class="h-4 w-4" />
                            </Button>
                            <div
                                class="wm-surface-subtle rounded-[22px] p-4 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Gunakan kategori untuk mengelompokkan jenis
                                limbah pada level paling atas.
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
                                Daftar Kategori
                            </p>
                            <p
                                class="text-sm text-slate-600 dark:text-slate-300"
                            >
                                Daftar kode, deskripsi, relasi tipe, dan status
                                aktif kategori.
                            </p>
                        </div>
                        <div
                            class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                        >
                            {{ props.categories.length }} kategori
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
                                        editingCategory
                                            ? 'Ubah kategori'
                                            : 'Buat kategori'
                                    }}
                                </DialogTitle>
                                <DialogDescription>
                                    {{
                                        editingCategory
                                            ? 'Perbarui detail kategori.'
                                            : 'Tambahkan kategori limbah baru ke sistem.'
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
                                        placeholder="Category name"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="code">Kode *</Label>
                                    <Input
                                        id="code"
                                        v-model="formData.code"
                                        required
                                        placeholder="CAT_CODE"
                                        :disabled="!!editingCategory"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="description">Deskripsi</Label>
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
                                    <Label for="is_active">Aktif</Label>
                                </div>

                                <DialogFooter>
                                    <Button type="submit">
                                        {{
                                            editingCategory
                                                ? 'Simpan perubahan'
                                                : 'Buat kategori'
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
                                <TableHead>Jenis Limbah</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="categories.length === 0">
                                <TableCell
                                    :colspan="6"
                                    class="text-center text-muted-foreground"
                                >
                                    Belum ada kategori.
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="category in categories"
                                :key="category.id"
                                class="border-slate-200/70 dark:border-slate-800/70"
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
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditDialog(category)"
                                    >
                                        Ubah
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="deleteCategory(category)"
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
