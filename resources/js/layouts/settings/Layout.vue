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
    <div class="space-y-8 px-4 py-6">
        <div class="rounded-2xl border bg-card/80 p-5 shadow-sm">
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
            <p class="max-w-3xl text-sm text-muted-foreground">
                Gunakan panel ini untuk memperbarui informasi dasar, mengamankan
                proses masuk, dan menyesuaikan tampilan aplikasi sesuai
                preferensi kerja Anda.
            </p>
        </div>

        <div class="grid gap-8 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="space-y-4">
                <div class="rounded-2xl border bg-card p-3 shadow-sm">
                    <nav class="space-y-2" aria-label="Pengaturan akun">
                        <Link
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            :href="item.href"
                            :class="[
                                'group flex rounded-xl border px-4 py-3 transition-colors',
                                isCurrentOrParentUrl(item.href)
                                    ? 'border-primary/30 bg-primary/10 text-foreground'
                                    : 'border-transparent hover:border-border hover:bg-muted/50',
                            ]"
                        >
                            <div
                                class="mt-0.5 mr-3 rounded-lg p-2"
                                :class="
                                    isCurrentOrParentUrl(item.href)
                                        ? 'bg-primary/15 text-primary'
                                        : 'bg-muted text-muted-foreground'
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
                                <p class="mt-1 text-xs text-muted-foreground">
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
