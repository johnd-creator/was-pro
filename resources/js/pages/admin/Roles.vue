<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    RotateCcw,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import RolesController from '@/actions/App/Http/Controllers/Admin/RolesController';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Permission {
    id: string;
    name: string;
    slug: string;
    module: string;
}

interface Role {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    level: number;
    is_active: boolean;
    permissions: Pick<Permission, 'id' | 'slug'>[];
}

type Props = {
    roles: Role[];
    permissions: Permission[];
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Akses Peran', href: '/admin/roles' },
];

const permissionsByModule = computed(() => {
    const groups: Record<string, Permission[]> = {};

    for (const permission of props.permissions) {
        const module = permission.module ?? 'general';

        if (!groups[module]) {
            groups[module] = [];
        }

        groups[module].push(permission);
    }

    return groups;
});

const modules = computed(() => Object.keys(permissionsByModule.value).sort());

const statusMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const selectedPermissions = ref<Record<string, Set<string>>>(
    Object.fromEntries(
        props.roles.map((role) => [
            role.id,
            new Set(role.permissions.map((p) => p.id)),
        ]),
    ),
);

const initialPermissions = ref<Record<string, Set<string>>>(
    Object.fromEntries(
        props.roles.map((role) => [
            role.id,
            new Set(role.permissions.map((permission) => permission.id)),
        ]),
    ),
);

const savingRoleId = ref<string | null>(null);

function hasPermission(roleId: string, permissionId: string): boolean {
    return selectedPermissions.value[roleId]?.has(permissionId) ?? false;
}

function togglePermission(roleId: string, permissionId: string): void {
    const current = selectedPermissions.value[roleId] ?? new Set<string>();

    if (current.has(permissionId)) {
        current.delete(permissionId);
    } else {
        current.add(permissionId);
    }

    selectedPermissions.value[roleId] = new Set(current);
}

function isDirty(roleId: string): boolean {
    const current = selectedPermissions.value[roleId] ?? new Set<string>();
    const initial = initialPermissions.value[roleId] ?? new Set<string>();

    if (current.size !== initial.size) {
        return true;
    }

    return [...current].some((permissionId) => !initial.has(permissionId));
}

function changedPermissionCount(roleId: string): number {
    const current = selectedPermissions.value[roleId] ?? new Set<string>();
    const initial = initialPermissions.value[roleId] ?? new Set<string>();

    let changes = 0;

    current.forEach((permissionId) => {
        if (!initial.has(permissionId)) {
            changes += 1;
        }
    });

    initial.forEach((permissionId) => {
        if (!current.has(permissionId)) {
            changes += 1;
        }
    });

    return changes;
}

function resetRole(roleId: string): void {
    selectedPermissions.value[roleId] = new Set(
        initialPermissions.value[roleId] ?? [],
    );
}

function countSelectedPermissions(roleId: string): number {
    return selectedPermissions.value[roleId]?.size ?? 0;
}

function countModulePermissions(roleId: string, module: string): number {
    return permissionsByModule.value[module].filter((permission) =>
        hasPermission(roleId, permission.id),
    ).length;
}

function selectAllPermissions(roleId: string): void {
    selectedPermissions.value[roleId] = new Set(
        props.permissions.map((permission) => permission.id),
    );
}

function clearAllPermissions(roleId: string): void {
    selectedPermissions.value[roleId] = new Set();
}

function selectModule(roleId: string, module: string): void {
    const next = new Set(selectedPermissions.value[roleId] ?? []);

    permissionsByModule.value[module].forEach((permission) => {
        next.add(permission.id);
    });

    selectedPermissions.value[roleId] = next;
}

function clearModule(roleId: string, module: string): void {
    const next = new Set(selectedPermissions.value[roleId] ?? []);

    permissionsByModule.value[module].forEach((permission) => {
        next.delete(permission.id);
    });

    selectedPermissions.value[roleId] = next;
}

function saveRole(role: Role): void {
    statusMessage.value = null;
    errorMessage.value = null;
    savingRoleId.value = role.id;

    router.put(
        RolesController.update(role.id),
        { permissions: Array.from(selectedPermissions.value[role.id] ?? []) },
        {
            preserveScroll: true,
            onSuccess: () => {
                initialPermissions.value[role.id] = new Set(
                    selectedPermissions.value[role.id] ?? [],
                );
                statusMessage.value = `Hak akses untuk ${role.name} berhasil diperbarui.`;
            },
            onError: () => {
                errorMessage.value = `Hak akses untuk ${role.name} gagal diperbarui.`;
            },
            onFinish: () => {
                savingRoleId.value = null;
            },
        },
    );
}

function formatModuleName(module: string): string {
    return module
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Akses Peran" />

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
                <CircleAlert class="h-4 w-4" />
                <AlertTitle>Pembaruan gagal</AlertTitle>
                <AlertDescription>{{ errorMessage }}</AlertDescription>
            </Alert>

            <Heading
                title="Akses Peran"
                description="Tinjau dan perbarui hak akses untuk setiap peran secara bertahap"
            />

            <div
                v-if="props.roles.length === 0"
                class="rounded-lg border p-10 text-center text-muted-foreground"
            >
                <ShieldCheck class="mx-auto mb-3 h-10 w-10 opacity-30" />
                <p class="text-sm font-medium text-foreground">
                    Belum ada peran
                </p>
                <p class="text-xs">
                    Buat peran terlebih dahulu sebelum mengatur hak akses.
                </p>
            </div>

            <div
                v-else-if="modules.length === 0"
                class="rounded-lg border p-10 text-center text-muted-foreground"
            >
                <ShieldCheck class="mx-auto mb-3 h-10 w-10 opacity-30" />
                <p class="text-sm font-medium text-foreground">
                    Belum ada hak akses
                </p>
                <p class="text-xs">
                    Tambahkan hak akses terlebih dahulu sebelum mengubah akses
                    peran.
                </p>
            </div>

            <div v-else class="space-y-6">
                <Card
                    v-for="role in props.roles"
                    :key="role.id"
                    class="border-border/80 shadow-sm"
                >
                    <CardHeader
                        class="sticky top-0 z-10 gap-4 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/85"
                    >
                        <div
                            class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                        >
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <CardTitle>{{ role.name }}</CardTitle>
                                    <Badge variant="secondary">{{
                                        role.slug
                                    }}</Badge>
                                    <Badge
                                        :variant="
                                            isDirty(role.id)
                                                ? 'default'
                                                : 'outline'
                                        "
                                    >
                                        {{
                                            isDirty(role.id)
                                                ? `${changedPermissionCount(role.id)} perubahan belum disimpan`
                                                : 'Tersimpan'
                                        }}
                                    </Badge>
                                </div>
                                <CardDescription>
                                    {{
                                        role.description ||
                                        'Belum ada deskripsi untuk peran ini.'
                                    }}
                                </CardDescription>
                                <div class="text-sm text-muted-foreground">
                                    {{ countSelectedPermissions(role.id) }} dari
                                    {{ props.permissions.length }} hak akses
                                    aktif
                                </div>
                                <div
                                    v-if="isDirty(role.id)"
                                    class="text-sm text-amber-700"
                                >
                                    {{ changedPermissionCount(role.id) }}
                                    hak akses berubah dari konfigurasi awal
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="selectAllPermissions(role.id)"
                                >
                                    Pilih semua
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="clearAllPermissions(role.id)"
                                >
                                    Kosongkan semua
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="
                                        !isDirty(role.id) ||
                                        savingRoleId === role.id
                                    "
                                    @click="resetRole(role.id)"
                                >
                                    <RotateCcw class="mr-2 h-4 w-4" />
                                    Atur ulang
                                </Button>
                                <Button
                                    size="sm"
                                    :disabled="
                                        !isDirty(role.id) ||
                                        savingRoleId === role.id
                                    "
                                    @click="saveRole(role)"
                                >
                                    <Spinner
                                        v-if="savingRoleId === role.id"
                                        class="mr-2"
                                    />
                                    {{
                                        savingRoleId === role.id
                                            ? 'Menyimpan...'
                                            : 'Simpan perubahan'
                                    }}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-5 p-6">
                        <section
                            v-for="module in modules"
                            :key="`${role.id}-${module}`"
                            class="rounded-lg border border-border/70"
                        >
                            <div
                                class="flex flex-col gap-3 border-b bg-muted/30 px-4 py-3 md:flex-row md:items-center md:justify-between"
                            >
                                <div>
                                    <h3 class="font-medium">
                                        {{ formatModuleName(module) }}
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            countModulePermissions(
                                                role.id,
                                                module,
                                            )
                                        }}
                                        dari
                                        {{ permissionsByModule[module].length }}
                                        dipilih
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="selectModule(role.id, module)"
                                    >
                                        Pilih semua
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="clearModule(role.id, module)"
                                    >
                                        Kosongkan
                                    </Button>
                                </div>
                            </div>

                            <div
                                class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3"
                            >
                                <label
                                    v-for="permission in permissionsByModule[
                                        module
                                    ]"
                                    :key="permission.id"
                                    :for="`${role.id}-${permission.id}`"
                                    class="flex items-start gap-3 rounded-md border border-border/70 p-3 transition-colors hover:bg-muted/40"
                                >
                                    <Checkbox
                                        :id="`${role.id}-${permission.id}`"
                                        :checked="
                                            hasPermission(
                                                role.id,
                                                permission.id,
                                            )
                                        "
                                        @update:checked="
                                            togglePermission(
                                                role.id,
                                                permission.id,
                                            )
                                        "
                                    />
                                    <div class="space-y-1">
                                        <div class="text-sm font-medium">
                                            {{ permission.name }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ permission.slug }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </section>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
