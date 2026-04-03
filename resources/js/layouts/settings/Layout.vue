<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { KeyRound, Palette, ShieldCheck, UserRound } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import type { NavItemWithRequiredHref } from '@/types';

type SettingsNavItem = NavItemWithRequiredHref & {
    description: string;
    icon: typeof UserRound;
};

const sidebarNavItems: SettingsNavItem[] = [
    {
        title: 'Profil',
        description: 'Perbarui identitas dan alamat email akun Anda.',
        icon: UserRound,
        href: editProfile.url(),
    },
    {
        title: 'Kata sandi',
        description: 'Jaga keamanan akun dengan kata sandi yang kuat.',
        icon: KeyRound,
        href: editPassword.url(),
    },
    {
        title: 'Verifikasi dua langkah',
        description: 'Tambahkan lapisan keamanan saat proses masuk.',
        icon: ShieldCheck,
        href: show.url(),
    },
    {
        title: 'Tampilan',
        description: 'Sesuaikan preferensi tampilan sesuai kebutuhan.',
        icon: Palette,
        href: editAppearance.url(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="relative space-y-8 overflow-x-hidden px-4 py-6">
        <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[320px] bg-linear-to-b from-slate-50 via-white to-transparent dark:from-slate-950 dark:via-slate-950" />
        <div class="pointer-events-none absolute -top-10 left-1/4 -z-10 h-56 w-56 rounded-full bg-cyan-200/18 blur-3xl dark:bg-cyan-500/10" />
        <div class="pointer-events-none absolute top-24 right-0 -z-10 h-64 w-64 rounded-full bg-amber-200/12 blur-3xl dark:bg-amber-500/8" />

        <div class="rounded-[30px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:border-slate-800/80 dark:bg-slate-950/88 dark:shadow-[0_22px_45px_-32px_rgba(2,6,23,0.8)]">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <Heading
                    title="Pengaturan Akun"
                    description="Kelola profil, keamanan, dan preferensi akun Anda dari satu tempat."
                />
                <Badge variant="secondary" class="w-fit">
                    Area akun & keamanan
                </Badge>
            </div>
            <p class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                Gunakan panel ini untuk memperbarui informasi dasar, mengamankan
                proses masuk, dan menyesuaikan tampilan aplikasi sesuai
                preferensi kerja Anda.
            </p>
        </div>

        <div class="grid gap-8 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="space-y-4">
                <div class="rounded-[26px] border border-slate-200/80 bg-white/90 p-3 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.24)] dark:border-slate-800/80 dark:bg-slate-950/88 dark:shadow-[0_22px_45px_-32px_rgba(2,6,23,0.8)]">
                    <nav class="space-y-2" aria-label="Pengaturan akun">
                        <Link
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            :href="item.href"
                            :class="[
                                'group flex rounded-[18px] border px-4 py-3 transition-colors',
                                isCurrentOrParentUrl(item.href)
                                    ? 'border-cyan-200/80 bg-cyan-50/90 text-foreground dark:border-cyan-800/80 dark:bg-cyan-950/40'
                                    : 'border-transparent hover:border-slate-200/80 hover:bg-slate-50/70 dark:hover:border-slate-800/80 dark:hover:bg-slate-900/70',
                            ]"
                        >
                            <div
                                class="mt-0.5 mr-3 rounded-lg p-2"
                                :class="
                                    isCurrentOrParentUrl(item.href)
                                        ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/60 dark:text-cyan-200'
                                        : 'bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-400'
                                "
                            >
                                <component :is="item.icon" class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <p class="text-sm font-medium">
                                        {{ item.title }}
                                    </p>
                                    <span
                                        v-if="isCurrentOrParentUrl(item.href)"
                                        class="h-2 w-2 rounded-full bg-primary"
                                    ></span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground dark:text-slate-400">
                                    {{ item.description }}
                                </p>
                            </div>
                        </Link>
                    </nav>
                </div>
            </aside>

            <Separator class="lg:hidden" />

            <div class="min-w-0">
                <section class="max-w-3xl space-y-10">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
