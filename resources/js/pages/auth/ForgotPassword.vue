<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Lupa kata sandi"
        description="Masukkan email akun Anda untuk menerima tautan reset akses."
    >
        <Head title="Lupa kata sandi" />

        <div
            v-if="status"
            class="rounded-2xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/35 dark:text-emerald-200"
        >
            {{ status }}
        </div>

        <div class="space-y-6">
            <Form v-bind="email.form()" v-slot="{ errors, processing }">
                <div class="grid gap-2.5">
                    <Label
                        for="email"
                        class="text-[13px] font-semibold tracking-[0.01em] text-slate-700 dark:text-slate-200"
                    >
                        Alamat email
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        autofocus
                        placeholder="email@example.com"
                        class="h-12 rounded-2xl border-slate-200/80 bg-white px-4 text-[15px] shadow-[0_8px_18px_-16px_rgba(15,23,42,0.55)] dark:border-slate-800/80 dark:bg-slate-950/70"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="mt-6 flex items-center justify-start">
                    <Button
                        class="h-12 w-full rounded-2xl bg-[linear-gradient(135deg,#0f172a_0%,#0f766e_100%)] text-sm font-semibold text-white shadow-[0_18px_40px_-18px_rgba(15,23,42,0.45)] transition-transform duration-200 hover:-translate-y-0.5 hover:bg-[linear-gradient(135deg,#0f172a_0%,#115e59_100%)] dark:bg-[linear-gradient(135deg,#e2e8f0_0%,#a5f3fc_100%)] dark:text-slate-950 dark:hover:bg-[linear-gradient(135deg,#f8fafc_0%,#cffafe_100%)]"
                        :disabled="processing"
                        size="lg"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" />
                        Kirim tautan reset kata sandi
                    </Button>
                </div>
            </Form>

            <div
                class="space-x-1 text-center text-sm text-slate-500 dark:text-slate-400"
            >
                <span>Atau kembali ke</span>
                <TextLink
                    :href="login()"
                    class="font-medium text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-slate-100"
                >
                    halaman masuk
                </TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
