<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
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

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Organizations', href: '/admin/organizations' },
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
        <Head title="Organizations" />

        <div class="space-y-6 px-6 py-6">
            <!-- Flash messages -->
            <Alert
                v-if="flash?.success"
                class="border-green-200 bg-green-50 text-green-800"
            >
                <CircleCheck class="h-4 w-4 !text-green-600" />
                <AlertTitle>Success</AlertTitle>
                <AlertDescription>{{ flash.success }}</AlertDescription>
            </Alert>

            <Alert v-if="flash?.error" variant="destructive">
                <CircleX class="h-4 w-4" />
                <AlertTitle>Error</AlertTitle>
                <AlertDescription>{{ flash.error }}</AlertDescription>
            </Alert>

            <!-- Header -->
            <div class="flex items-center justify-between">
                <Heading
                    title="Organizations"
                    description="Manage organizations and their tenant schemas"
                />
                <Button @click="openCreateSheet">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Organization
                </Button>
            </div>

            <!-- Table -->
            <div class="rounded-lg border shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-muted/40">
                            <TableHead>Organization</TableHead>
                            <TableHead>Code</TableHead>
                            <TableHead>Schema</TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="w-28 text-right"
                                >Actions</TableHead
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
                                        No organizations yet
                                    </p>
                                    <p class="text-xs">
                                        Click "Add Organization" to create the
                                        first one.
                                    </p>
                                </div>
                            </TableCell>
                        </TableRow>

                        <TableRow
                            v-for="org in props.organizations"
                            :key="org.id"
                            class="group"
                        >
                            <TableCell>
                                <div>
                                    <p class="font-semibold">{{ org.name }}</p>
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
                                        org.is_active ? 'default' : 'secondary'
                                    "
                                    :class="
                                        org.is_active
                                            ? 'bg-green-100 text-green-800 hover:bg-green-100'
                                            : ''
                                    "
                                >
                                    {{ org.is_active ? 'Active' : 'Inactive' }}
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
            </div>

            <!-- Summary row -->
            <p
                v-if="props.organizations.length > 0"
                class="text-xs text-muted-foreground"
            >
                Showing {{ props.organizations.length }} organization{{
                    props.organizations.length !== 1 ? 's' : ''
                }}
            </p>
        </div>

        <!-- ── Create / Edit Sheet ──────────────────────────────────────────── -->
        <Sheet v-model:open="sheetOpen">
            <SheetContent class="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {{
                            editingOrganization
                                ? 'Edit Organization'
                                : 'New Organization'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            editingOrganization
                                ? 'Update the organization details below.'
                                : 'Fill in the details to create a new organization. A database schema will be provisioned automatically.'
                        }}
                    </SheetDescription>
                </SheetHeader>

                <form class="mt-6 space-y-5" @submit.prevent="submitForm">
                    <!-- Name -->
                    <div class="grid gap-1.5">
                        <Label for="org-name"
                            >Name <span class="text-destructive">*</span></Label
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
                            Code <span class="text-destructive">*</span>
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
                                    ? 'Code cannot be changed after creation.'
                                    : 'Unique identifier, used to generate the database schema name.'
                            }}
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-1.5">
                        <Label for="org-description">Description</Label>
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
                        <Label for="org-address">Address</Label>
                        <Input
                            id="org-address"
                            v-model="form.address"
                            placeholder="Jl. Contoh No. 1, Jakarta"
                        />
                    </div>

                    <!-- Phone + Email -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label for="org-phone">Phone</Label>
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
                            <p class="text-sm font-medium">Active</p>
                            <p class="text-xs text-muted-foreground">
                                Inactive organizations are hidden from most
                                views.
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
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="flex-1"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing
                                    ? 'Saving…'
                                    : editingOrganization
                                      ? 'Update'
                                      : 'Create'
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
                    <DialogTitle>Delete Organization?</DialogTitle>
                    <DialogDescription>
                        You are about to permanently delete
                        <strong>{{ deletingOrganization?.name }}</strong
                        >. This will also drop its database schema and all
                        associated data. This action
                        <strong>cannot be undone</strong>.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-2 pt-2">
                    <DialogClose as-child>
                        <Button variant="outline" class="flex-1">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        class="flex-1"
                        :disabled="form.processing"
                        @click="executeDelete"
                    >
                        {{ form.processing ? 'Deleting…' : 'Yes, Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
