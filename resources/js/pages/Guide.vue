<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BookOpen,
    CheckCircle2,
    ChevronDown,
    Compass,
    Search,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { index as fabaMastersIndex } from '@/actions/App/Http/Controllers/WasteManagement/MasterData/FabaMastersController';
import { index as auditIndex } from '@/actions/App/Http/Controllers/WasteManagement/OperationalAuditController';
import { index as inboxIndex } from '@/actions/App/Http/Controllers/WasteManagement/OperationalInboxController';
import { index as plansIndex } from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingPlansController';
import GuideScreenshot from '@/components/GuideScreenshot.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    canOpenGuideAction,
    filterGuideCases,
    guideCases,
    guideRoles,
    matchingGuideRole,
} from '@/lib/application-guide';
import type { GuideAction } from '@/lib/application-guide';
import { guideScreenshots } from '@/lib/guide-screenshots';
import { guide } from '@/routes';
import admin from '@/routes/admin';
import { edit as profile } from '@/routes/profile';
import waste from '@/routes/waste-management';

interface Props {
    guideRole: { slug: string | null; name: string };
    guideTenantReady: boolean;
    guideModules: {
        waste: boolean;
        faba: boolean;
        audit: boolean;
        fabaMasters: boolean;
    };
}
const props = defineProps<Props>();
const page = usePage();
const { context, scopedUrl } = useOrganizationContext();
const query = ref('');
const role = ref('all');
const category = ref('all');
const categories = [...new Set(guideCases.map((item) => item.category))];
const results = computed(() =>
    filterGuideCases(query.value, role.value, category.value),
);
const selectedRole = computed(() =>
    guideRoles.find((item) => item.slug === role.value),
);
const ownRole = computed(() =>
    matchingGuideRole(
        props.guideRole.slug,
        page.props.auth.user.is_super_admin === true,
    ),
);
const access = computed(() => ({
    permissions: page.props.auth.user.permissions ?? [],
    isSuperAdmin: page.props.auth.user.is_super_admin === true,
    tenantReady: props.guideTenantReady,
    modules: props.guideModules,
    plans: page.props.hauling_plans_available === true,
    inbox: page.props.operational_inbox_available === true,
}));
const actions: Record<GuideAction, { label: string; url: () => string }> = {
    masterTypes: {
        label: 'Jenis Limbah',
        url: waste.masterData.types.index.url,
    },
    fabaMasters: {
        label: 'Tujuan Internal FABA',
        url: () => fabaMastersIndex.url('destinations'),
    },
    stockBalance: {
        label: 'Saldo TPS FABA',
        url: waste.faba.recaps.balance.url,
    },
    profile: { label: 'Pengaturan Profil', url: profile.url },
    records: { label: 'Catatan Limbah', url: waste.records.index.url },
    balance: {
        label: 'Neraca Operasional Limbah',
        url: waste.balance.index.url,
    },
    plans: { label: 'Rencana Angkut', url: plansIndex.url },
    haulings: { label: 'Pengangkutan Limbah', url: waste.haulings.index.url },
    production: {
        label: 'Produksi FABA',
        url: waste.faba.production.index.url,
    },
    utilization: {
        label: 'Pemanfaatan FABA',
        url: waste.faba.utilization.index.url,
    },
    adjustments: {
        label: 'Adjustment / Koreksi',
        url: waste.faba.adjustments.index.url,
    },
    recaps: { label: 'Rekap FABA', url: waste.faba.recaps.monthly.url },
    closing: {
        label: 'Closing & Approval',
        url: waste.faba.approvals.index.url,
    },
    reports: { label: 'Laporan FABA', url: waste.faba.reports.index.url },
    vendors: { label: 'Vendors', url: waste.masterData.vendors.index.url },
    audit: { label: 'Riwayat Audit B3', url: auditIndex.url },
    inbox: { label: 'Notifikasi Operasional', url: inboxIndex.url },
    users: { label: 'Pengguna', url: admin.users.index.url },
    roles: { label: 'Hak Akses', url: admin.roles.index.url },
    organizations: { label: 'Organisasi', url: admin.organizations.index.url },
};
function resetFilters() {
    query.value = '';
    role.value = 'all';
    category.value = 'all';
}
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Panduan', href: guide.url() }]">
        <Head title="Panduan Aplikasi" />
        <main class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
            <section
                class="relative overflow-hidden rounded-2xl border border-emerald-200 bg-linear-to-br from-emerald-50 via-background to-teal-50 p-6 md:p-8 dark:border-emerald-900 dark:from-emerald-950/40 dark:to-teal-950/30"
            >
                <div
                    class="flex items-center gap-2 text-sm font-semibold text-emerald-800 dark:text-emerald-300"
                >
                    <BookOpen class="size-5" aria-hidden="true" /> PUSAT PANDUAN
                    WAS-PRO
                </div>
                <h1
                    class="mt-4 max-w-2xl text-3xl font-semibold tracking-tight md:text-4xl"
                >
                    Dari kasus sehari-hari,<br class="hidden sm:block" />
                    ke langkah yang tepat.
                </h1>
                <p class="mt-4 max-w-2xl leading-relaxed text-muted-foreground">
                    Cari alur pekerjaan, pahami hasilnya, dan ketahui apa yang
                    perlu dilakukan saat pengajuan ditolak atau terhambat.
                </p>
                <div class="mt-6 flex flex-wrap gap-3 text-sm">
                    <span class="rounded-full border bg-background/80 px-3 py-2"
                        >{{ guideCases.length }} panduan kasus</span
                    >
                    <span class="rounded-full border bg-background/80 px-3 py-2"
                        >Role akun: <strong>{{ guideRole.name }}</strong></span
                    >
                    <span
                        class="rounded-full border bg-background/80 px-3 py-2"
                        >{{
                            context?.organization?.name ??
                            'Belum ada organisasi aktif'
                        }}</span
                    >
                </div>
            </section>

            <section
                class="flex gap-3 rounded-xl border bg-card p-4 text-sm leading-relaxed"
                aria-label="Tentang akses panduan"
            >
                <ShieldCheck
                    class="mt-0.5 size-5 shrink-0 text-emerald-700 dark:text-emerald-400"
                    aria-hidden="true"
                />
                <div>
                    <p class="font-semibold">
                        Membaca panduan tidak mengubah hak akses.
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        Profil role di bawah menggambarkan template standar.
                        Permission akun, kepemilikan transaksi, status dan
                        kesiapan fitur tetap menentukan tindakan sebenarnya.
                        Tautan hanya membuka menu yang tersedia untuk akun Anda;
                        bukan memberi izin membuat atau menyetujui data.
                    </p>
                    <p v-if="!guideTenantReady" class="mt-2 font-medium">
                        {{
                            context?.organization
                                ? 'Data organisasi belum siap. Panduan tetap tersedia; hubungi administrator sebelum bekerja dengan data operasional.'
                                : 'Pilih organisasi jika Anda Super Admin, atau minta administrator menetapkan organisasi akun Anda. Panduan tetap dapat dibaca.'
                        }}
                    </p>
                </div>
            </section>

            <div
                class="grid items-start gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]"
            >
                <aside
                    class="space-y-5 rounded-xl border bg-card p-5 lg:sticky lg:top-6"
                    aria-label="Filter panduan"
                >
                    <div>
                        <label for="guide-search" class="text-sm font-semibold"
                            >Cari kasus atau kendala</label
                        >
                        <div class="relative mt-2">
                            <Search
                                class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                                aria-hidden="true"
                            /><Input
                                id="guide-search"
                                v-model="query"
                                type="search"
                                placeholder="Mis. ditolak, stok, closing"
                                class="min-h-11 pl-9"
                            />
                        </div>
                    </div>
                    <div>
                        <label for="guide-role" class="text-sm font-semibold"
                            >Panduan menurut role</label
                        ><select
                            id="guide-role"
                            v-model="role"
                            class="mt-2 min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="all">Semua role</option>
                            <option
                                v-for="item in guideRoles"
                                :key="item.slug"
                                :value="item.slug"
                            >
                                {{ item.name }}
                            </option>
                        </select>
                        <Button
                            v-if="ownRole"
                            variant="ghost"
                            class="mt-2 min-h-11 w-full justify-start px-2"
                            @click="role = ownRole"
                            >Lihat panduan role saya</Button
                        >
                        <p
                            v-else
                            class="mt-2 text-xs leading-relaxed text-muted-foreground"
                        >
                            Role khusus atau belum ditetapkan. Baca semua
                            panduan; tautan tetap mengikuti hak aktual Anda.
                        </p>
                    </div>
                    <div>
                        <label
                            for="guide-category"
                            class="text-sm font-semibold"
                            >Kelompok kasus</label
                        ><select
                            id="guide-category"
                            v-model="category"
                            class="mt-2 min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="all">Semua kelompok</option>
                            <option
                                v-for="item in categories"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </option>
                        </select>
                    </div>
                    <Button
                        variant="outline"
                        class="min-h-11 w-full"
                        @click="resetFilters"
                        >Reset filter</Button
                    >
                    <p
                        class="border-t pt-4 text-xs leading-relaxed text-muted-foreground"
                    >
                        Butuh bantuan? Catat menu, nomor referensi, status dan
                        pesan kesalahan untuk administrator. Jangan membagikan
                        kata sandi.
                    </p>
                </aside>

                <section class="min-w-0 space-y-4" aria-label="Daftar panduan">
                    <div
                        v-if="selectedRole"
                        class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-5 dark:border-emerald-900 dark:bg-emerald-950/20"
                    >
                        <h2 class="font-semibold">
                            Fokus {{ selectedRole.name }}
                        </h2>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ selectedRole.summary }}
                        </p>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold">Pilih kasus Anda</h2>
                        <p
                            class="text-sm text-muted-foreground"
                            role="status"
                            aria-live="polite"
                        >
                            {{ results.length }} kasus ditemukan
                        </p>
                    </div>
                    <div
                        v-if="results.length === 0"
                        class="rounded-xl border border-dashed p-10 text-center"
                    >
                        <Compass
                            class="mx-auto size-9 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <h3 class="mt-4 font-semibold">
                            Belum ada kasus yang cocok
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Coba kata yang lebih singkat atau ubah filter role
                            dan kelompok.
                        </p>
                        <Button
                            class="mt-5 min-h-11"
                            variant="outline"
                            @click="resetFilters"
                            >Tampilkan semua panduan</Button
                        >
                    </div>
                    <details
                        v-for="item in results"
                        :id="item.id"
                        :key="item.id"
                        class="group rounded-xl border bg-card open:shadow-sm"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-start gap-4 rounded-xl p-5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                        >
                            <div class="min-w-0 flex-1">
                                <span
                                    class="text-xs font-semibold tracking-wide text-emerald-700 dark:text-emerald-400"
                                    >{{ item.category }}</span
                                >
                                <h3
                                    class="mt-1 text-base font-semibold md:text-lg"
                                >
                                    {{ item.title }}
                                </h3>
                                <p
                                    class="mt-2 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ item.summary }}
                                </p>
                            </div>
                            <ChevronDown
                                class="mt-1 size-5 shrink-0 text-muted-foreground group-open:rotate-180"
                                aria-hidden="true"
                            />
                        </summary>
                        <div class="space-y-6 border-t p-5 md:p-6">
                            <GuideScreenshot
                                v-if="guideScreenshots[item.id]"
                                :screenshot="guideScreenshots[item.id]"
                                :title="item.title"
                            />
                            <div>
                                <h4 class="text-sm font-semibold">
                                    Sebelum mulai
                                </h4>
                                <ul
                                    class="mt-2 list-disc space-y-2 pl-5 text-sm leading-relaxed text-muted-foreground"
                                >
                                    <li
                                        v-for="line in item.prerequisites"
                                        :key="line"
                                    >
                                        {{ line }}
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold">
                                    Langkah kerja
                                </h4>
                                <ol class="mt-3 space-y-3">
                                    <li
                                        v-for="(line, index) in item.steps"
                                        :key="line"
                                        class="flex gap-3 text-sm leading-relaxed"
                                    >
                                        <span
                                            class="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold"
                                            >{{ index + 1 }}</span
                                        ><span>{{ line }}</span>
                                    </li>
                                </ol>
                            </div>
                            <div
                                class="rounded-lg bg-emerald-50 p-4 dark:bg-emerald-950/25"
                            >
                                <h4
                                    class="flex items-center gap-2 text-sm font-semibold"
                                >
                                    <CheckCircle2
                                        class="size-4 text-emerald-700 dark:text-emerald-400"
                                        aria-hidden="true"
                                    />
                                    Hasil yang diharapkan
                                </h4>
                                <p class="mt-2 text-sm leading-relaxed">
                                    {{ item.outcome }}
                                </p>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold">
                                    Jika ditolak atau terhambat
                                </h4>
                                <p
                                    class="mt-2 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ item.recovery }}
                                </p>
                            </div>
                            <Button
                                v-if="canOpenGuideAction(item.action, access)"
                                as-child
                                variant="outline"
                                class="min-h-11 max-w-full text-left whitespace-normal"
                                ><Link
                                    :href="
                                        scopedUrl(actions[item.action].url())
                                    "
                                    >Buka {{ actions[item.action].label
                                    }}<ArrowUpRight
                                        class="ml-2 size-4 shrink-0"
                                        aria-hidden="true" /></Link
                            ></Button>
                            <p
                                v-else
                                class="rounded-lg border border-dashed p-3 text-xs leading-relaxed text-muted-foreground"
                            >
                                Tautan {{ actions[item.action].label }} tidak
                                tersedia: periksa organisasi, hak akses dan
                                kesiapan fitur bersama administrator. Anda tetap
                                dapat membaca panduannya.
                            </p>
                        </div>
                    </details>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
