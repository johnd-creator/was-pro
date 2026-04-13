<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <AuthBase
        title="Masuk"
        description="Gunakan akun Anda untuk mengakses operasional limbah dan FABA."
    >
        <Head title="Masuk" />

        <div
            v-if="status"
            class="rounded-2xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/35 dark:text-emerald-200"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-5">
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
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                        class="h-12 rounded-2xl border-slate-200/80 bg-white px-4 text-[15px] shadow-[0_8px_18px_-16px_rgba(15,23,42,0.55)] dark:border-slate-800/80 dark:bg-slate-950/70"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2.5">
                    <div class="flex items-center justify-between gap-3">
                        <Label
                            for="password"
                            class="text-[13px] font-semibold tracking-[0.01em] text-slate-700 dark:text-slate-200"
                        >
                            Kata sandi
                        </Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm font-medium text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
                            :tabindex="5"
                        >
                            Lupa kata sandi?
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Kata sandi"
                        class="h-12 rounded-2xl border-slate-200/80 bg-white px-4 text-[15px] shadow-[0_8px_18px_-16px_rgba(15,23,42,0.55)] dark:border-slate-800/80 dark:bg-slate-950/70"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between gap-3 pt-1">
                    <Label
                        for="remember"
                        class="flex items-center gap-3 text-sm font-medium text-slate-600 dark:text-slate-300"
                    >
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Ingat saya</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-1 h-12 w-full rounded-2xl bg-[linear-gradient(135deg,#0f172a_0%,#0f766e_100%)] text-sm font-semibold text-white shadow-[0_18px_40px_-18px_rgba(15,23,42,0.45)] transition-transform duration-200 hover:-translate-y-0.5 hover:bg-[linear-gradient(135deg,#0f172a_0%,#115e59_100%)] dark:bg-[linear-gradient(135deg,#e2e8f0_0%,#a5f3fc_100%)] dark:text-slate-950 dark:hover:bg-[linear-gradient(135deg,#f8fafc_0%,#cffafe_100%)]"
                    size="lg"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Masuk
                </Button>
            </div>
        </Form>
    </AuthBase>
</template>
