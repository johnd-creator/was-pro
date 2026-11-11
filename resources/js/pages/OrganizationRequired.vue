<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit } from '@/routes/profile';
const { context } = useOrganizationContext();
</script>

<template>
    <Head title="Organisasi belum dipilih" />
    <AppLayout>
        <main class="mx-auto w-full max-w-2xl px-6 py-16">
            <h1 class="text-2xl font-semibold">
                {{
                    context?.can_select
                        ? 'Pilih organisasi untuk melanjutkan'
                        : 'Akun belum terhubung ke organisasi'
                }}
            </h1>
            <p class="mt-3 leading-relaxed text-slate-600 dark:text-slate-300">
                {{
                    context?.can_select
                        ? 'Pilih organisasi melalui Pengaturan Profil atau pilihan di atas. Dashboard, daftar Limbah, FABA, formulir, dan ekspor akan menggunakan organisasi yang Anda pilih.'
                        : 'Hubungi administrator untuk menetapkan organisasi akun Anda. Data operasional belum dapat ditampilkan sampai penugasan selesai.'
                }}
            </p>
            <Button v-if="context?.can_select" as-child class="mt-6"
                ><Link :href="edit.url()"
                    >Pilih organisasi di Pengaturan Profil</Link
                ></Button
            >
        </main>
    </AppLayout>
</template>
