<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    Bell,
    Check,
    Mail,
} from 'lucide-vue-next';
import { ref } from 'vue';
import {
    index,
    update,
} from '@/actions/App/Http/Controllers/WasteManagement/OperationalInboxController';
import Heading from '@/components/Heading.vue';
import Link from '@/components/OrganizationLink.vue';
import { Button } from '@/components/ui/button';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import type {
    NotificationContext,
    OperationalAlert,
} from '@/types/notifications';

interface InboxNotification {
    id: string;
    event: string;
    label: string;
    occurred_at: string;
    read_at: string | null;
    current_status: string | null;
    reference: string;
    source_date: string | null;
    source_url: string;
}

const props = defineProps<{
    currentAlerts: OperationalAlert[];
    notificationContext: NotificationContext | null;
    available: boolean;
    notifications: InboxNotification[];
    unreadCount: number;
    page: number;
    hasMore: boolean;
}>();
const { scopedUrl } = useOrganizationContext();
const busyId = ref<string | null>(null);
const errorMessage = ref('');
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Notifikasi Operasional', href: index.url() },
];

function setRead(notification: InboxNotification): void {
    if (busyId.value) return;
    busyId.value = notification.id;
    errorMessage.value = '';
    router.patch(
        scopedUrl(update.url(notification.id)),
        {
            read: notification.read_at === null,
            page: props.page,
            month: props.notificationContext?.month,
        },
        {
            preserveScroll: true,
            onError: () => {
                errorMessage.value =
                    'Status baca belum tersimpan. Muat ulang dan coba lagi.';
            },
            onFinish: () => {
                busyId.value = null;
            },
        },
    );
}

function formatDate(value: string): string {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date);
}

function statusLabel(status: string | null): string {
    if (!status) return 'Tidak tersedia';
    const labels: Record<string, string> = {
        draft: 'Draf',
        submitted: 'Diajukan',
        pending_review: 'Menunggu peninjauan',
        pending_approval: 'Menunggu persetujuan',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        cancelled: 'Dibatalkan',
    };
    return labels[status] ?? 'Status diperbarui';
}
</script>

<template>
    <WasteManagementLayout
        title="Notifikasi Operasional"
        :breadcrumbs="breadcrumbs"
    >
        <Head title="Notifikasi Operasional" />
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <Heading
                title="Notifikasi Operasional"
                description="Indikator yang perlu ditindaklanjuti dan riwayat pengajuan dalam satu halaman."
            />
            <section
                class="space-y-4"
                aria-labelledby="active-notifications-title"
            >
                <div>
                    <h2
                        id="active-notifications-title"
                        class="text-lg font-semibold"
                    >
                        Perlu ditindaklanjuti
                    </h2>
                    <p
                        v-if="notificationContext"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Snapshot {{ notificationContext.as_of }}. Jumlah ini
                        sama dengan indikator pada dashboard dan lonceng
                        notifikasi untuk periode yang sama.
                    </p>
                </div>
                <ul
                    v-if="currentAlerts.length"
                    class="grid gap-3 md:grid-cols-2"
                    aria-label="Indikator operasional aktif"
                >
                    <li v-for="alert in currentAlerts" :key="alert.id">
                        <Link
                            :href="alert.source_url"
                            class="flex h-full items-start gap-3 rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        >
                            <AlertTriangle
                                class="mt-0.5 size-5 shrink-0"
                                :class="
                                    alert.tone === 'red'
                                        ? 'text-destructive'
                                        : 'text-amber-600'
                                "
                                aria-hidden="true"
                            />
                            <div class="min-w-0 flex-1 space-y-1">
                                <h3 class="font-semibold">
                                    {{ alert.count }} {{ alert.title }}
                                </h3>
                                <p class="text-sm text-muted-foreground">
                                    {{ alert.description }}
                                </p>
                                <span
                                    class="inline-flex items-center gap-1 text-sm font-medium text-primary"
                                    >Tindak lanjuti
                                    <ArrowUpRight
                                        class="size-4"
                                        aria-hidden="true"
                                /></span>
                            </div>
                        </Link>
                    </li>
                </ul>
                <p
                    v-else
                    class="rounded-xl border border-dashed border-border p-6 text-sm text-muted-foreground"
                >
                    Tidak ada indikator aktif untuk organisasi, periode, dan
                    akses Anda.
                </p>
            </section>
            <h2 class="text-lg font-semibold">Riwayat notifikasi</h2>
            <div
                v-if="!available"
                class="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground"
            >
                Riwayat kejadian belum tersedia untuk organisasi ini. Indikator
                aktif tetap ditampilkan di atas.
            </div>
            <template v-else>
                <div
                    class="flex items-center gap-3 rounded-xl border border-border bg-card p-4"
                >
                    <Bell
                        class="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p class="text-sm">
                        <strong>{{ unreadCount }}</strong> notifikasi belum
                        dibaca
                    </p>
                </div>
                <p class="text-sm text-muted-foreground">
                    Notifikasi mencatat kejadian sebelumnya. Status saat ini
                    dapat berubah setelah diproses petugas lain. Menandai dibaca
                    tidak menyetujui atau menolak pengajuan.
                </p>
                <p
                    v-if="errorMessage"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ errorMessage }}
                </p>
                <div
                    v-if="notifications.length === 0"
                    class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
                >
                    Belum ada riwayat kejadian yang dapat ditampilkan. Indikator
                    aktif di atas tidak bergantung pada riwayat ini.
                </div>
                <ul
                    v-else
                    class="space-y-3"
                    aria-label="Riwayat notifikasi operasional"
                >
                    <li
                        v-for="notification in notifications"
                        :key="notification.id"
                        class="rounded-xl border border-border bg-card p-4"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="!notification.read_at"
                                        class="size-2 shrink-0 rounded-full bg-primary"
                                        aria-hidden="true"
                                    />
                                    <p class="font-medium">
                                        {{ notification.label }}
                                    </p>
                                </div>
                                <p class="text-sm font-medium">
                                    {{ notification.reference }}
                                </p>
                                <p
                                    v-if="notification.source_date"
                                    class="text-sm text-muted-foreground"
                                >
                                    Tanggal sumber:
                                    <time
                                        :datetime="notification.source_date"
                                        >{{ notification.source_date }}</time
                                    >
                                </p>
                                <Link
                                    :href="notification.source_url"
                                    class="inline-flex text-sm font-medium text-primary underline underline-offset-4 hover:no-underline"
                                >
                                    Lihat sumber
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    <time
                                        :datetime="notification.occurred_at"
                                        >{{
                                            formatDate(notification.occurred_at)
                                        }}</time
                                    >
                                    -
                                    {{
                                        notification.read_at
                                            ? 'Sudah dibaca'
                                            : 'Belum dibaca'
                                    }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Status saat ini:
                                    {{
                                        statusLabel(notification.current_status)
                                    }}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="busyId !== null"
                                @click="setRead(notification)"
                            >
                                <Mail
                                    v-if="notification.read_at"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <Check
                                    v-else
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{
                                    notification.read_at
                                        ? 'Tandai belum dibaca'
                                        : 'Tandai dibaca'
                                }}
                            </Button>
                        </div>
                    </li>
                </ul>
                <nav
                    class="flex items-center justify-between gap-3"
                    aria-label="Halaman notifikasi"
                >
                    <Button
                        v-if="page > 1"
                        as-child
                        variant="outline"
                        size="sm"
                    >
                        <Link
                            :href="
                                index.url({
                                    query: {
                                        page: page - 1,
                                        month: notificationContext?.month,
                                    },
                                })
                            "
                            >Sebelumnya</Link
                        >
                    </Button>
                    <span v-else />
                    <span class="text-sm text-muted-foreground"
                        >Halaman {{ props.page }}</span
                    >
                    <Button v-if="hasMore" as-child variant="outline" size="sm">
                        <Link
                            :href="
                                index.url({
                                    query: {
                                        page: page + 1,
                                        month: notificationContext?.month,
                                    },
                                })
                            "
                            >Berikutnya</Link
                        >
                    </Button>
                    <span v-else />
                </nav>
            </template>
        </div>
    </WasteManagementLayout>
</template>
