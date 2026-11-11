<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { dashboard } from '@/routes';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pengaturan profil',
        href: edit.url(),
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const { context, scopedUrl } = useOrganizationContext();
const selectedOrganization = ref(context.value?.organization?.id ?? '');
const switchingOrganization = ref(false);
const organizationError = ref('');

watch(
    () => context.value?.organization?.id,
    (id) => {
        selectedOrganization.value = id ?? '';
    },
);

function switchOrganization(): void {
    if (switchingOrganization.value) return;
    organizationError.value = '';
    router.get(
        edit.url(),
        { organization_id: selectedOrganization.value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                switchingOrganization.value = true;
            },
            onError: (errors) => {
                organizationError.value =
                    errors.organization_id ??
                    'Organisasi tidak dapat dipilih. Coba lagi.';
            },
            onFinish: () => {
                switchingOrganization.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Pengaturan profil" />

        <h1 class="sr-only">Pengaturan profil</h1>

        <SettingsLayout>
            <div
                class="wm-surface-elevated flex flex-col space-y-6 rounded-[28px] p-6"
            >
                <Heading
                    variant="small"
                    title="Informasi profil"
                    description="Perbarui nama dan alamat email yang digunakan pada akun ini"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Nama lengkap"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Alamat email</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Alamat email"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Alamat email Anda belum terverifikasi.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Klik di sini untuk mengirim ulang email
                                verifikasi.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            Tautan verifikasi baru telah dikirim ke alamat email
                            Anda.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Simpan</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Tersimpan.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <section
                v-if="context?.can_select"
                aria-labelledby="organization-settings-heading"
                class="wm-surface-elevated space-y-5 rounded-[28px] p-6"
            >
                <div class="space-y-2">
                    <h2
                        id="organization-settings-heading"
                        class="text-lg font-semibold"
                    >
                        Organisasi operasional
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Akun superadmin bersifat global dan tidak terikat pada
                        organisasi. Pilih organisasi untuk membuka data Limbah
                        dan FABA. Pilihan ini tidak mengubah profil atau
                        keanggotaan akun.
                    </p>
                </div>
                <form class="space-y-4" @submit.prevent="switchOrganization">
                    <div class="grid gap-2">
                        <Label for="profile-organization"
                            >Organisasi yang ingin dikelola</Label
                        >
                        <select
                            id="profile-organization"
                            v-model="selectedOrganization"
                            :disabled="switchingOrganization"
                            class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm"
                            aria-describedby="organization-selection-help"
                        >
                            <option value="">
                                Global (tanpa organisasi operasional)
                            </option>
                            <option
                                v-for="organization in context.choices"
                                :key="organization.id"
                                :value="organization.id"
                            >
                                {{ organization.name }} ({{
                                    organization.code
                                }})
                            </option>
                        </select>
                        <p
                            id="organization-selection-help"
                            class="text-xs text-muted-foreground"
                        >
                            Administrasi pengguna dan organisasi tetap bersifat
                            global. Data operasional hanya dibuka untuk
                            organisasi yang dipilih.
                        </p>
                        <InputError :message="organizationError" />
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            :disabled="
                                switchingOrganization ||
                                selectedOrganization ===
                                    (context.organization?.id ?? '')
                            "
                        >
                            {{
                                switchingOrganization
                                    ? 'Mengganti...'
                                    : 'Gunakan organisasi'
                            }}
                        </Button>
                        <Button
                            v-if="context.organization"
                            variant="outline"
                            as-child
                        >
                            <Link :href="scopedUrl(dashboard.url())"
                                >Buka dashboard organisasi</Link
                            >
                        </Button>
                    </div>
                </form>
                <p class="text-sm" role="status">
                    {{
                        context.organization
                            ? `Aktif: ${context.organization.name}`
                            : 'Konteks global. Pilih organisasi sebelum mencatat transaksi.'
                    }}
                </p>
            </section>
            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
