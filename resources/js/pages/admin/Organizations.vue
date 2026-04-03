<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Database,
    Building2,
    CircleCheck,
    CircleX,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import OrganizationsController from '@/actions/App/Http/Controllers/Admin/OrganizationsController';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Organization {
    id: string;
    name: string;
    code: string;
    schema_name: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

type Props = {
    organizations: Organization[];
};

const props = defineProps<Props>();

const page = usePage();

const flash = computed(
    () => page.props.flash as { success?: string; error?: string } | undefined,
);
const activeOrganizationsCount = computed(
    () =>
        props.organizations.filter((organization) => organization.is_active)
            .length,
);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Organisasi', href: '/admin/organizations' },
];

// ── Sheet state ───────────────────────────────────────────────────────────────
const sheetOpen = ref(false);
const editingOrganization = ref<Organization | null>(null);

const form = useForm({
    name: '',
    code: '',
    description: '',
    address: '',
    phone: '',
    email: '',
    is_active: true,
});

function openCreateSheet() {
    editingOrganization.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
    sheetOpen.value = true;
}

function openEditSheet(org: Organization) {
    editingOrganization.value = org;
    form.name = org.name;
    form.code = org.code;
    form.description = org.description ?? '';
    form.address = org.address ?? '';
    form.phone = org.phone ?? '';
    form.email = org.email ?? '';
    form.is_active = org.is_active;
    form.clearErrors();
    sheetOpen.value = true;
}

function submitForm() {
    if (editingOrganization.value) {
        form.put(
            OrganizationsController.update(editingOrganization.value.id).url,
            {
                onSuccess: () => {
                    sheetOpen.value = false;
                },
            },
        );
    } else {
        form.post(OrganizationsController.store().url, {
            onSuccess: () => {
                sheetOpen.value = false;
            },
        });
    }
}

// ── Delete confirmation ───────────────────────────────────────────────────────
const deletingOrganization = ref<Organization | null>(null);
const deleteDialogOpen = ref(false);

function confirmDelete(org: Organization) {
    deletingOrganization.value = org;
    deleteDialogOpen.value = true;
}

function executeDelete() {
    if (!deletingOrganization.value) return;
    form.delete(
        OrganizationsController.destroy(deletingOrganization.value.id).url,
        {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                deletingOrganization.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Organisasi" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[360px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-cyan-200/18 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-20 right-0 -z-10 h-64 w-64 rounded-full bg-amber-200/12 blur-3xl"
            />

            <div class="space-y-8">
                <!-- Flash messages -->
                <Alert
                    v-if="flash?.success"
                    class="border-green-200 bg-green-50 text-green-800"
                >
                    <CircleCheck class="h-4 w-4 !text-green-600" />
                    <AlertTitle>Berhasil</AlertTitle>
                    <AlertDescription>{{ flash.success }}</AlertDescription>
                </Alert>

                <Alert v-if="flash?.error" variant="destructive">
                    <CircleX class="h-4 w-4" />
                    <AlertTitle>Gagal</AlertTitle>
                    <AlertDescription>{{ flash.error }}</AlertDescription>
                </Alert>

                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] to-cyan-50/20 dark:to-cyan-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-cyan-700/70 uppercase"
                                >
                                    Tenant Registry
                                </p>
                                <Heading
                                    title="Organisasi"
                                    description="Kelola organisasi, schema tenant, dan status operasional dari satu registry yang lebih rapi."
                                />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Total Organisasi
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ organizations.length }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-neutral"
                                        >
                                            <Building2 class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-emerald"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-emerald-700/80 uppercase"
                                            >
                                                Aktif
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ activeOrganizationsCount }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-emerald"
                                        >
                                            <CircleCheck class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-violet"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-violet-700/80 uppercase"
                                            >
                                                Tenant Schema
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ organizations.length }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-violet"
                                        >
                                            <Database class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="wm-surface-panel space-y-3 rounded-[28px] p-5"
                        >
                            <Button
                                class="w-full justify-between"
                                @click="openCreateSheet"
                            >
                                <span>Tambah organisasi</span>
                                <Plus class="h-4 w-4" />
                            </Button>
                            <div class="wm-surface-subtle rounded-[22px] p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Catatan
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Perubahan organisasi memengaruhi tenant
                                    schema, kontak, dan status operasional
                                    lintas aplikasi.
                                </p>
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
                                Registry Organisasi
                            </p>
                            <p
                                class="text-sm text-slate-600 dark:text-slate-300"
                            >
                                Daftar tenant, schema, kontak, dan status
                                organisasi.
                            </p>
                        </div>
                        <div
                            class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                        >
                            {{ organizations.length }} organisasi
                        </div>
                    </div>
                    <Table>
                        <TableHeader
                            class="bg-slate-50/90 dark:bg-slate-900/80"
                        >
                            <TableRow
                                class="border-slate-200/80 dark:border-slate-800/80"
                            >
                                <TableHead>Organisasi</TableHead>
                                <TableHead>Kode</TableHead>
                                <TableHead>Schema</TableHead>
                                <TableHead>Kontak</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="w-28 text-right"
                                    >Aksi</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="props.organizations.length === 0">
                                <TableCell :colspan="6">
                                    <div
                                        class="flex flex-col items-center justify-center py-12 text-muted-foreground"
                                    >
                                        <Building2
                                            class="mb-3 h-10 w-10 opacity-30"
                                        />
                                        <p class="text-sm font-medium">
                                            Belum ada organisasi
                                        </p>
                                        <p class="text-xs">
                                            Klik "Tambah organisasi" untuk
                                            membuat entri pertama.
                                        </p>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <TableRow
                                v-for="org in props.organizations"
                                :key="org.id"
                                class="group border-slate-200/70 dark:border-slate-800/70"
                            >
                                <TableCell>
                                    <div>
                                        <p class="font-semibold">
                                            {{ org.name }}
                                        </p>
                                        <p
                                            v-if="org.description"
                                            class="mt-0.5 line-clamp-1 text-xs text-muted-foreground"
                                        >
                                            {{ org.description }}
                                        </p>
                                    </div>
                                </TableCell>

                                <TableCell>
                                    <code
                                        class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs"
                                    >
                                        {{ org.code }}
                                    </code>
                                </TableCell>

                                <TableCell>
                                    <code
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {{ org.schema_name }}
                                    </code>
                                </TableCell>

                                <TableCell>
                                    <div class="space-y-0.5 text-sm">
                                        <p v-if="org.email">{{ org.email }}</p>
                                        <p
                                            v-if="org.phone"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ org.phone }}
                                        </p>
                                        <span
                                            v-if="!org.email && !org.phone"
                                            class="text-xs text-muted-foreground"
                                            >—</span
                                        >
                                    </div>
                                </TableCell>

                                <TableCell>
                                    <Badge
                                        :variant="
                                            org.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        :class="
                                            org.is_active
                                                ? 'bg-green-100 text-green-800 hover:bg-green-100'
                                                : ''
                                        "
                                    >
                                        {{
                                            org.is_active ? 'Aktif' : 'Nonaktif'
                                        }}
                                    </Badge>
                                </TableCell>

                                <TableCell class="text-right">
                                    <div
                                        class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8"
                                            title="Edit"
                                            @click="openEditSheet(org)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-destructive hover:text-destructive"
                                            title="Delete"
                                            @click="confirmDelete(org)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </section>

                <!-- Summary row -->
                <p
                    v-if="props.organizations.length > 0"
                    class="text-xs text-muted-foreground"
                >
                    Menampilkan {{ props.organizations.length }} organisasi
                </p>
            </div>
        </div>

        <!-- ── Create / Edit Sheet ──────────────────────────────────────────── -->
        <Sheet v-model:open="sheetOpen">
            <SheetContent class="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {{
                            editingOrganization
                                ? 'Ubah organisasi'
                                : 'Organisasi baru'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            editingOrganization
                                ? 'Perbarui detail organisasi di bawah ini.'
                                : 'Lengkapi detail organisasi baru. Schema tenant akan dibuat otomatis.'
                        }}
                    </SheetDescription>
                </SheetHeader>

                <form class="mt-6 space-y-5" @submit.prevent="submitForm">
                    <!-- Name -->
                    <div class="grid gap-1.5">
                        <Label for="org-name"
                            >Nama <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="org-name"
                            v-model="form.name"
                            placeholder="PT. Contoh Jaya"
                            :class="
                                form.errors.name ? 'border-destructive' : ''
                            "
                        />
                        <p
                            v-if="form.errors.name"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Code -->
                    <div class="grid gap-1.5">
                        <Label for="org-code">
                            Kode <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="org-code"
                            v-model="form.code"
                            placeholder="CONTOH"
                            class="font-mono uppercase"
                            :disabled="!!editingOrganization"
                            :class="
                                form.errors.code ? 'border-destructive' : ''
                            "
                        />
                        <p
                            v-if="form.errors.code"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.code }}
                        </p>
                        <p v-else class="text-xs text-muted-foreground">
                            {{
                                editingOrganization
                                    ? 'Kode tidak dapat diubah setelah organisasi dibuat.'
                                    : 'Identifier unik yang dipakai untuk membentuk nama schema tenant.'
                            }}
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-1.5">
                        <Label for="org-description">Deskripsi</Label>
                        <Textarea
                            id="org-description"
                            v-model="form.description"
                            placeholder="Brief description of the organization"
                            :rows="3"
                        />
                        <p
                            v-if="form.errors.description"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <!-- Address -->
                    <div class="grid gap-1.5">
                        <Label for="org-address">Alamat</Label>
                        <Input
                            id="org-address"
                            v-model="form.address"
                            placeholder="Jl. Contoh No. 1, Jakarta"
                        />
                    </div>

                    <!-- Phone + Email -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label for="org-phone">Telepon</Label>
                            <Input
                                id="org-phone"
                                v-model="form.phone"
                                placeholder="+62 21 000 0000"
                            />
                            <p
                                v-if="form.errors.phone"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.phone }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="org-email">Email</Label>
                            <Input
                                id="org-email"
                                v-model="form.email"
                                type="email"
                                placeholder="admin@contoh.co.id"
                            />
                            <p
                                v-if="form.errors.email"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>
                    </div>

                    <!-- Active toggle -->
                    <div
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <div>
                            <p class="text-sm font-medium">Aktif</p>
                            <p class="text-xs text-muted-foreground">
                                Organisasi nonaktif disembunyikan dari mayoritas
                                tampilan operasional.
                            </p>
                        </div>
                        <Switch
                            id="org-is-active"
                            v-model:checked="form.is_active"
                        />
                    </div>

                    <SheetFooter class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="flex-1"
                            @click="sheetOpen = false"
                        >
                            <X class="mr-2 h-4 w-4" />
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            class="flex-1"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing
                                    ? 'Menyimpan...'
                                    : editingOrganization
                                      ? 'Simpan perubahan'
                                      : 'Buat organisasi'
                            }}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>

        <!-- ── Delete Confirmation Dialog ────────────────────────────────────── -->
        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Hapus organisasi?</DialogTitle>
                    <DialogDescription>
                        Anda akan menghapus permanen
                        <strong>{{ deletingOrganization?.name }}</strong
                        >. Tindakan ini juga akan menghapus schema tenant dan
                        seluruh data terkait. Tindakan ini
                        <strong>tidak dapat dibatalkan</strong>.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-2 pt-2">
                    <DialogClose as-child>
                        <Button variant="outline" class="flex-1">Batal</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        class="flex-1"
                        :disabled="form.processing"
                        @click="executeDelete"
                    >
                        {{
                            form.processing
                                ? 'Menghapus...'
                                : 'Hapus organisasi'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
