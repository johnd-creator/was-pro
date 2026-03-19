<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    Search,
    Shield,
    ShieldOff,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UsersController from '@/actions/App/Http/Controllers/Admin/UsersController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Organization {
    id: string;
    name: string;
    code: string;
}

interface Role {
    id: string;
    name: string;
    slug: string;
}

interface User {
    id: string;
    name: string;
    email: string;
    organization_id: string | null;
    role_id: string | null;
    is_super_admin: boolean;
    organization: Organization | null;
    role: Role | null;
}

type Props = {
    users: User[];
    organizations: Organization[];
    roles: Role[];
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Manajemen Pengguna', href: '/admin/users' },
];

const statusMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const dialogOpen = ref(false);
const passwordDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const editingUser = ref<User | null>(null);
const deletingUser = ref<User | null>(null);
const searchQuery = ref('');
const organizationFilter = ref('all');
const roleFilter = ref('all');
const adminFilter = ref<'all' | 'super' | 'standard'>('all');
const sortKey = ref<'name' | 'email' | 'organization'>('name');
const currentPage = ref(1);
const perPage = 10;

const hasAnyUsers = computed(() => props.users.length > 0);
const hasActiveFilters = computed(
    () =>
        searchQuery.value.trim().length > 0 ||
        organizationFilter.value !== 'all' ||
        roleFilter.value !== 'all' ||
        adminFilter.value !== 'all' ||
        sortKey.value !== 'name',
);

const userForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    organization_id: '',
    role_id: '',
    is_super_admin: false,
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const deleteForm = useForm({});

const filteredUsers = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return [...props.users]
        .filter((user) => {
            const matchesQuery =
                query.length === 0 ||
                user.name.toLowerCase().includes(query) ||
                user.email.toLowerCase().includes(query) ||
                user.organization?.name?.toLowerCase().includes(query) ||
                user.role?.name?.toLowerCase().includes(query);

            const matchesOrganization =
                organizationFilter.value === 'all' ||
                user.organization_id === organizationFilter.value;

            const matchesRole =
                roleFilter.value === 'all' || user.role_id === roleFilter.value;

            const matchesAdmin =
                adminFilter.value === 'all' ||
                (adminFilter.value === 'super' && user.is_super_admin) ||
                (adminFilter.value === 'standard' && !user.is_super_admin);

            return (
                matchesQuery &&
                matchesOrganization &&
                matchesRole &&
                matchesAdmin
            );
        })
        .sort((left, right) => {
            if (sortKey.value === 'email') {
                return left.email.localeCompare(right.email);
            }

            if (sortKey.value === 'organization') {
                return (left.organization?.name ?? '').localeCompare(
                    right.organization?.name ?? '',
                );
            }

            return left.name.localeCompare(right.name);
        });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredUsers.value.length / perPage)),
);

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * perPage;

    return filteredUsers.value.slice(start, start + perPage);
});

const pageRangeLabel = computed(() => {
    if (filteredUsers.value.length === 0) {
        return '0 dari 0 pengguna';
    }

    const start = (currentPage.value - 1) * perPage + 1;
    const end = Math.min(start + perPage - 1, filteredUsers.value.length);

    return `${start}-${end} dari ${filteredUsers.value.length} pengguna`;
});

function resetMessages(): void {
    statusMessage.value = null;
    errorMessage.value = null;
}

function resetPage(): void {
    currentPage.value = 1;
}

function resetFilters(): void {
    searchQuery.value = '';
    organizationFilter.value = 'all';
    roleFilter.value = 'all';
    adminFilter.value = 'all';
    sortKey.value = 'name';
    resetPage();
}

function openCreateDialog() {
    resetMessages();
    editingUser.value = null;
    userForm.reset();
    userForm.clearErrors();
    userForm.is_super_admin = false;
    dialogOpen.value = true;
}

function openEditDialog(user: User) {
    resetMessages();
    editingUser.value = user;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.organization_id = user.organization_id ?? '';
    userForm.role_id = user.role_id ?? '';
    userForm.is_super_admin = user.is_super_admin;
    userForm.clearErrors();
    dialogOpen.value = true;
}

function openPasswordDialog(user: User) {
    resetMessages();
    editingUser.value = user;
    passwordForm.reset();
    passwordForm.clearErrors();
    passwordDialogOpen.value = true;
}

function submit(): void {
    resetMessages();

    if (editingUser.value) {
        userForm.put(UsersController.update(editingUser.value.id).url, {
            onSuccess: () => {
                dialogOpen.value = false;
                statusMessage.value = `${editingUser.value?.name ?? 'Pengguna'} berhasil diperbarui.`;
            },
            onError: () => {
                errorMessage.value =
                    'Periksa kembali field yang ditandai lalu coba lagi.';
            },
        });
    } else {
        userForm.post(UsersController.store().url, {
            onSuccess: () => {
                dialogOpen.value = false;
                statusMessage.value = `${userForm.name} berhasil dibuat.`;
            },
            onError: () => {
                errorMessage.value =
                    'Periksa kembali field yang ditandai lalu coba lagi.';
            },
        });
    }
}

function submitPassword(): void {
    if (!editingUser.value) {
        return;
    }

    resetMessages();
    passwordForm.put(UsersController.updatePassword(editingUser.value.id).url, {
        onSuccess: () => {
            passwordDialogOpen.value = false;
            statusMessage.value = `Kata sandi untuk ${editingUser.value?.name ?? 'pengguna'} berhasil diperbarui.`;
        },
        onError: () => {
            errorMessage.value =
                'Pembaruan kata sandi gagal. Periksa formulir lalu coba lagi.';
        },
    });
}

function confirmDelete(user: User): void {
    resetMessages();
    deletingUser.value = user;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    if (!deletingUser.value) {
        return;
    }

    deleteForm.delete(UsersController.destroy(deletingUser.value.id).url, {
        onSuccess: () => {
            statusMessage.value = `${deletingUser.value?.name ?? 'Pengguna'} berhasil dihapus.`;
            deleteDialogOpen.value = false;
            deletingUser.value = null;
        },
        onError: () => {
            errorMessage.value = 'Pengguna tidak dapat dihapus saat ini.';
        },
    });
}

function changePage(page: number): void {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Manajemen Pengguna" />

        <div class="space-y-6 px-6 py-6">
            <Alert
                v-if="statusMessage"
                class="border-green-200 bg-green-50 text-green-800"
            >
                <CircleCheck class="h-4 w-4 !text-green-600" />
                <AlertTitle>Berhasil</AlertTitle>
                <AlertDescription>{{ statusMessage }}</AlertDescription>
            </Alert>

            <Alert v-if="errorMessage" variant="destructive">
                <CircleX class="h-4 w-4" />
                <AlertTitle>Aksi gagal</AlertTitle>
                <AlertDescription>{{ errorMessage }}</AlertDescription>
            </Alert>

            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <Heading
                    title="Manajemen Pengguna"
                    description="Kelola pengguna dan aksesnya di dalam sistem"
                />
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button @click="openCreateDialog"
                            >Tambah pengguna</Button
                        >
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-[600px]">
                        <DialogHeader>
                            <DialogTitle>
                                {{
                                    editingUser
                                        ? 'Ubah pengguna'
                                        : 'Tambah pengguna'
                                }}
                            </DialogTitle>
                            <DialogDescription>
                                {{
                                    editingUser
                                        ? 'Perbarui detail pengguna yang sudah ada.'
                                        : 'Tambahkan pengguna baru ke dalam sistem.'
                                }}
                            </DialogDescription>
                        </DialogHeader>
                        <form @submit.prevent="submit" class="space-y-4">
                            <AlertError
                                v-if="userForm.hasErrors"
                                :errors="Object.values(userForm.errors)"
                                title="Pengguna gagal disimpan"
                            />

                            <div class="grid gap-2">
                                <Label for="name">Nama *</Label>
                                <Input
                                    id="name"
                                    v-model="userForm.name"
                                    required
                                    placeholder="Nama lengkap"
                                />
                                <InputError :message="userForm.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">Email *</Label>
                                <Input
                                    id="email"
                                    v-model="userForm.email"
                                    type="email"
                                    required
                                    placeholder="email@example.com"
                                />
                                <InputError :message="userForm.errors.email" />
                            </div>

                            <template v-if="!editingUser">
                                <div class="grid gap-2">
                                    <Label for="password">Kata sandi *</Label>
                                    <Input
                                        id="password"
                                        v-model="userForm.password"
                                        type="password"
                                        required
                                        placeholder="Minimal 8 karakter"
                                    />
                                    <InputError
                                        :message="userForm.errors.password"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="password_confirmation"
                                        >Konfirmasi kata sandi *</Label
                                    >
                                    <Input
                                        id="password_confirmation"
                                        v-model="userForm.password_confirmation"
                                        type="password"
                                        required
                                        placeholder="Ulangi kata sandi"
                                    />
                                </div>
                            </template>

                            <div class="grid gap-2">
                                <Label for="organization_id"
                                    >Organisasi *</Label
                                >
                                <Select
                                    v-model="userForm.organization_id"
                                    required
                                >
                                    <SelectTrigger id="organization_id">
                                        <SelectValue
                                            placeholder="Pilih organisasi"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="org in props.organizations"
                                            :key="org.id"
                                            :value="org.id"
                                        >
                                            {{ org.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="userForm.errors.organization_id"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Pilih organisasi utama tempat pengguna ini
                                    bekerja.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="role_id">Peran *</Label>
                                <Select v-model="userForm.role_id" required>
                                    <SelectTrigger id="role_id">
                                        <SelectValue
                                            placeholder="Pilih peran"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="role in props.roles"
                                            :key="role.id"
                                            :value="role.id"
                                        >
                                            {{ role.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="userForm.errors.role_id"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Peran menentukan hak akses dasar yang akan
                                    dimiliki pengguna.
                                </p>
                            </div>

                            <div
                                class="flex items-center justify-between rounded-md border px-3 py-2"
                            >
                                <div>
                                    <p class="text-sm font-medium">
                                        Akses super admin
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Memberikan akses lintas organisasi dan
                                        melewati pembatasan berbasis peran.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        userForm.is_super_admin =
                                            !userForm.is_super_admin
                                    "
                                >
                                    <component
                                        :is="
                                            userForm.is_super_admin
                                                ? Shield
                                                : ShieldOff
                                        "
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{
                                        userForm.is_super_admin
                                            ? 'Aktif'
                                            : 'Nonaktif'
                                    }}
                                </Button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Gunakan status ini hanya untuk akun dengan
                                tanggung jawab administrasi penuh.
                            </p>

                            <DialogFooter>
                                <Button
                                    type="submit"
                                    :disabled="userForm.processing"
                                >
                                    <Spinner
                                        v-if="userForm.processing"
                                        class="mr-2"
                                    />
                                    {{
                                        userForm.processing
                                            ? 'Menyimpan...'
                                            : editingUser
                                              ? 'Simpan perubahan'
                                              : 'Buat pengguna'
                                    }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Password Dialog -->
            <Dialog v-model:open="passwordDialogOpen">
                <DialogContent class="sm:max-w-[400px]">
                    <DialogHeader>
                        <DialogTitle>Ubah kata sandi</DialogTitle>
                        <DialogDescription>
                            Tetapkan kata sandi baru untuk
                            {{ editingUser?.name }}.
                        </DialogDescription>
                    </DialogHeader>
                    <form @submit.prevent="submitPassword" class="space-y-4">
                        <AlertError
                            v-if="passwordForm.hasErrors"
                            :errors="Object.values(passwordForm.errors)"
                            title="Kata sandi gagal diperbarui"
                        />

                        <div class="grid gap-2">
                            <Label for="new_password">Kata sandi baru *</Label>
                            <Input
                                id="new_password"
                                v-model="passwordForm.password"
                                type="password"
                                required
                                placeholder="Minimal 8 karakter"
                            />
                            <InputError
                                :message="passwordForm.errors.password"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="new_password_confirmation"
                                >Konfirmasi kata sandi *</Label
                            >
                            <Input
                                id="new_password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                required
                                placeholder="Ulangi kata sandi"
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                :disabled="passwordForm.processing"
                            >
                                <Spinner
                                    v-if="passwordForm.processing"
                                    class="mr-2"
                                />
                                {{
                                    passwordForm.processing
                                        ? 'Menyimpan...'
                                        : 'Perbarui kata sandi'
                                }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent class="sm:max-w-[420px]">
                    <DialogHeader>
                        <DialogTitle>Hapus pengguna</DialogTitle>
                        <DialogDescription>
                            Tindakan ini akan menghapus permanen
                            <span class="font-medium text-foreground">{{
                                deletingUser?.name
                            }}</span>
                            dari sistem.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="deleteDialogOpen = false"
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="deleteForm.processing"
                            @click="executeDelete"
                        >
                            <Spinner
                                v-if="deleteForm.processing"
                                class="mr-2"
                            />
                            {{
                                deleteForm.processing
                                    ? 'Menghapus...'
                                    : 'Hapus pengguna'
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div
                class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-2 xl:grid-cols-5"
            >
                <div class="space-y-2 xl:col-span-2">
                    <Label for="user-search">Cari pengguna</Label>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            id="user-search"
                            v-model="searchQuery"
                            class="pl-9"
                            placeholder="Cari nama, email, organisasi, atau peran"
                            @update:model-value="resetPage"
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label>Organisasi</Label>
                    <Select
                        v-model="organizationFilter"
                        @update:model-value="resetPage"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Semua organisasi" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all"
                                >Semua organisasi</SelectItem
                            >
                            <SelectItem
                                v-for="organization in organizations"
                                :key="organization.id"
                                :value="organization.id"
                            >
                                {{ organization.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label>Peran</Label>
                    <Select
                        v-model="roleFilter"
                        @update:model-value="resetPage"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Semua peran" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua peran</SelectItem>
                            <SelectItem
                                v-for="role in roles"
                                :key="role.id"
                                :value="role.id"
                            >
                                {{ role.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-3 md:grid-cols-3 xl:col-span-2">
                    <div class="space-y-2">
                        <Label>Tipe akses</Label>
                        <Select
                            v-model="adminFilter"
                            @update:model-value="resetPage"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua tipe akses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >Semua tipe akses</SelectItem
                                >
                                <SelectItem value="super"
                                    >Super admin</SelectItem
                                >
                                <SelectItem value="standard"
                                    >Pengguna standar</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Urutkan</Label>
                        <Select
                            v-model="sortKey"
                            @update:model-value="resetPage"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih urutan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="name">Nama A-Z</SelectItem>
                                <SelectItem value="email">Email A-Z</SelectItem>
                                <SelectItem value="organization"
                                    >Organisasi A-Z</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-end">
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full"
                            :disabled="!hasActiveFilters"
                            @click="resetFilters"
                        >
                            Atur ulang filter
                        </Button>
                    </div>
                </div>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Organisasi</TableHead>
                            <TableHead>Peran</TableHead>
                            <TableHead>Super admin</TableHead>
                            <TableHead class="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="paginatedUsers.length === 0">
                            <TableCell :colspan="6" class="py-10 text-center">
                                <div
                                    class="flex flex-col items-center gap-2 text-muted-foreground"
                                >
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{
                                            hasAnyUsers
                                                ? 'Tidak ada pengguna yang cocok'
                                                : 'Belum ada pengguna'
                                        }}
                                    </p>
                                    <p class="text-xs">
                                        {{
                                            hasAnyUsers
                                                ? 'Ubah pencarian atau filter untuk melihat hasil lain.'
                                                : 'Tambahkan pengguna baru untuk mulai mengelola akses.'
                                        }}
                                    </p>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="user in paginatedUsers" :key="user.id">
                            <TableCell class="font-medium">{{
                                user.name
                            }}</TableCell>
                            <TableCell>{{ user.email }}</TableCell>
                            <TableCell>{{
                                user.organization?.name ?? '-'
                            }}</TableCell>
                            <TableCell>{{ user.role?.name ?? '-' }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        user.is_super_admin
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    :class="
                                        user.is_super_admin
                                            ? 'bg-blue-100 text-blue-800 hover:bg-blue-100'
                                            : ''
                                    "
                                >
                                    {{
                                        user.is_super_admin
                                            ? 'Super admin'
                                            : 'Standar'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell class="space-x-1 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openEditDialog(user)"
                                >
                                    Ubah
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openPasswordDialog(user)"
                                >
                                    Kata sandi
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    @click="confirmDelete(user)"
                                >
                                    Hapus
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div
                class="flex flex-col gap-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between"
            >
                <p>{{ pageRangeLabel }}</p>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage === 1"
                        @click="changePage(currentPage - 1)"
                    >
                        Sebelumnya
                    </Button>
                    <span>Halaman {{ currentPage }} dari {{ totalPages }}</span>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage === totalPages"
                        @click="changePage(currentPage + 1)"
                    >
                        Berikutnya
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
